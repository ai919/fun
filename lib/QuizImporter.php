<?php

declare(strict_types=1);

/**
 * QuizImporter
 *
 * 将符合 JSON Schema 的测验描述导入数据库，可用于 CLI 或后台管理。
 */
class QuizImporter
{
    private const DEFAULT_SCORE_VALUE = 0;

    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * @param array $payload   JSON 解析后的数组
     * @param bool  $overwrite 是否允许覆盖同 slug 的测验
     * @param bool  $dryRun    是否只做模拟导入
     * @return array 导入结果
     */
    public function import(array $payload, bool $overwrite = false, bool $dryRun = false): array
    {
        $this->validatePayload($payload);

        $existingTest = $this->findTestBySlug($payload['test']['slug']);
        if ($existingTest && !$overwrite) {
            throw new RuntimeException(sprintf('测验 slug "%s" 已存在，请勾选“允许覆盖”后重试。', $payload['test']['slug']));
        }

        $action = $existingTest ? 'update' : 'create';

        if ($dryRun) {
            return $this->buildDryRunResult($payload, $action, $existingTest['id'] ?? null);
        }

        $this->pdo->beginTransaction();
        try {
            $testId = $this->upsertTest($payload, $existingTest['id'] ?? null);
            $this->replaceResults($testId, $payload['results']);
            $this->replaceQuestions($testId, $payload);

            $this->pdo->commit();

            return [
                'action' => $action,
                'test_id' => $testId,
                'test_slug' => $payload['test']['slug'],
                'results_count' => count($payload['results']),
                'questions_count' => count($payload['questions']),
            ];
        } catch (Throwable $e) {
            if ($this->pdo->inTransaction()) {
                $this->pdo->rollBack();
            }
            throw $e;
        }
    }

    private function buildDryRunResult(array $payload, string $action, ?int $existingId): array
    {
        return [
            'action' => $action,
            'dry_run' => true,
            'test_id' => $existingId,
            'test_slug' => $payload['test']['slug'],
            'preview' => [
                'title' => $payload['test']['title'],
                'status' => $payload['test']['status'],
                'tags' => $this->normalizeTags($payload['test']['tags'] ?? null, true),
            ],
            'results_count' => count($payload['results']),
            'questions_count' => count($payload['questions']),
        ];
    }

    private function findTestBySlug(string $slug): ?array
    {
        $stmt = $this->pdo->prepare('SELECT id FROM tests WHERE slug = ? LIMIT 1');
        $stmt->execute([$slug]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        return $row ?: null;
    }

    private function upsertTest(array $payload, ?int $existingId): int
    {
        $test = $payload['test'];
        $tags = $this->normalizeTags($test['tags'] ?? null);
        
        // 自动识别评分模式（如果未指定或为默认值）
        $detected = $this->detectScoringMode($payload);
        $scoringMode = $test['scoring_mode'] ?? $detected['mode'];
        $scoringConfig = isset($test['scoring_config']) 
            ? json_encode($test['scoring_config'], JSON_UNESCAPED_UNICODE) 
            : ($detected['config'] ? json_encode($detected['config'], JSON_UNESCAPED_UNICODE) : null);
        $displayMode = $test['display_mode'] ?? 'single_page';
        $titleColor = $test['title_color'] ?? '#4f46e5';
        $playCountBeautified = isset($test['play_count_beautified']) ? (int)$test['play_count_beautified'] : null;
        $sortOrder = isset($test['sort_order']) ? (int)$test['sort_order'] : 0;

        if ($existingId) {
            $stmt = $this->pdo->prepare(
                'UPDATE tests
                 SET title = ?, subtitle = ?, description = ?, title_color = ?, tags = ?,
                     status = ?, sort_order = ?, scoring_mode = ?, scoring_config = ?,
                     display_mode = ?, play_count_beautified = ?
                 WHERE id = ?'
            );
            $stmt->execute([
                $test['title'],
                $test['subtitle'] ?? null,
                $test['description'],
                $titleColor,
                $tags,
                $test['status'],
                $sortOrder,
                $scoringMode,
                $scoringConfig,
                $displayMode,
                $playCountBeautified,
                $existingId,
            ]);
            return $existingId;
        }

        $stmt = $this->pdo->prepare(
            'INSERT INTO tests
                (slug, title, subtitle, description, title_color, tags, status, sort_order,
                 scoring_mode, scoring_config, display_mode, play_count_beautified)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            $test['slug'],
            $test['title'],
            $test['subtitle'] ?? null,
            $test['description'],
            $titleColor,
            $tags,
            $test['status'],
            $sortOrder,
            $scoringMode,
            $scoringConfig,
            $displayMode,
            $playCountBeautified,
        ]);

        return (int)$this->pdo->lastInsertId();
    }

    private function replaceResults(int $testId, array $results): void
    {
        $this->pdo->prepare('DELETE FROM results WHERE test_id = ?')->execute([$testId]);

        $stmt = $this->pdo->prepare(
            'INSERT INTO results
                (test_id, code, title, description, image_url, min_score, max_score)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );

        foreach ($results as $result) {
            $stmt->execute([
                $testId,
                $result['code'],
                $result['title'],
                $result['description'],
                $result['image_url'] ?? null,
                isset($result['min_score']) ? (float)$result['min_score'] : 0,
                isset($result['max_score']) ? (float)$result['max_score'] : 0,
            ]);
        }
    }

    private function replaceQuestions(int $testId, array $payload): void
    {
        $this->pdo->prepare('DELETE FROM questions WHERE test_id = ?')->execute([$testId]);

        $insertQuestion = $this->pdo->prepare(
            'INSERT INTO questions (test_id, question_text, sort_order) VALUES (?, ?, ?)'
        );
        $insertOption = $this->pdo->prepare(
            'INSERT INTO question_options
                (question_id, option_key, option_text, map_result_code, score_value)
             VALUES (?, ?, ?, ?, ?)'
        );

        foreach ($payload['questions'] as $index => $question) {
            $insertQuestion->execute([
                $testId,
                $question['text'],
                $index + 1,
            ]);
            $questionId = (int)$this->pdo->lastInsertId();

            foreach ($question['options'] as $option) {
                $insertOption->execute([
                    $questionId,
                    $option['key'],
                    $option['text'],
                    $option['map_result_code'] ?? null,
                    $this->resolveScore($option, $payload['test']['scoring_config'] ?? null),
                ]);
            }
        }
    }

    private function resolveScore(array $option, ?array $scoringConfig): int
    {
        if (isset($option['score_override']) && is_numeric($option['score_override'])) {
            return (int)$option['score_override'];
        }

        $optionScores = $scoringConfig['option_scores'] ?? null;
        if (is_array($optionScores)) {
            $key = $option['key'];
            if (isset($optionScores[$key]) && is_numeric($optionScores[$key])) {
                return (int)$optionScores[$key];
            }
        }

        return self::DEFAULT_SCORE_VALUE;
    }

    private function normalizeTags($tags, bool $asArray = false)
    {
        if ($tags === null || $tags === '') {
            return $asArray ? [] : null;
        }

        if (is_string($tags)) {
            $tagsList = array_map('trim', explode(',', $tags));
        } elseif (is_array($tags)) {
            $tagsList = array_map(static function ($tag) {
                return trim((string)$tag);
            }, $tags);
        } else {
            throw new InvalidArgumentException('test.tags 必须是字符串或字符串数组');
        }

        $tagsList = array_values(array_filter($tagsList, static function ($tag) {
            return $tag !== '';
        }));

        if ($asArray) {
            return $tagsList;
        }

        return empty($tagsList) ? null : implode(',', $tagsList);
    }

    private function validatePayload(array &$payload): void
    {
        if (!isset($payload['test']) || !is_array($payload['test'])) {
            throw new InvalidArgumentException('payload.test 必须是对象。');
        }

        $payload['test']['slug'] = strtolower(trim((string)($payload['test']['slug'] ?? '')));
        $test = $payload['test'];

        foreach (['slug', 'title', 'description', 'status'] as $field) {
            if (!isset($test[$field]) || trim((string)$test[$field]) === '') {
                throw new InvalidArgumentException(sprintf('test.%s 不能为空。', $field));
            }
        }

        if (!preg_match('/^[a-z0-9][a-z0-9-]{2,}$/', $test['slug'])) {
            throw new InvalidArgumentException('slug 仅支持小写字母、数字与短横线，且长度需大于 2。');
        }

        if (isset($test['tags']) && !is_array($test['tags']) && !is_string($test['tags'])) {
            throw new InvalidArgumentException('test.tags 需为字符串或字符串数组。');
        }

        if (isset($test['scoring_config']) && !is_array($test['scoring_config'])) {
            throw new InvalidArgumentException('test.scoring_config 必须是对象。');
        }

        if (!isset($payload['results']) || !is_array($payload['results']) || count($payload['results']) === 0) {
            throw new InvalidArgumentException('results 不能为空。');
        }

        $payload['results'] = array_values($payload['results']);
        $resultCodes = [];
        foreach ($payload['results'] as $index => &$result) {
            foreach (['code', 'title', 'description'] as $field) {
                if (!isset($result[$field]) || trim((string)$result[$field]) === '') {
                    throw new InvalidArgumentException(sprintf('results[%d].%s 不能为空。', $index, $field));
                }
            }

            $result['code'] = strtoupper(trim((string)$result['code']));
            if (isset($resultCodes[$result['code']])) {
                throw new InvalidArgumentException(sprintf('结果 code "%s" 重复。', $result['code']));
            }
            $resultCodes[$result['code']] = true;
        }
        unset($result);

        if (!isset($payload['questions']) || !is_array($payload['questions']) || count($payload['questions']) === 0) {
            throw new InvalidArgumentException('questions 不能为空。');
        }

        $payload['questions'] = array_values($payload['questions']);
        foreach ($payload['questions'] as $qIndex => &$question) {
            if (!isset($question['text']) || trim((string)$question['text']) === '') {
                throw new InvalidArgumentException(sprintf('questions[%d].text 不能为空。', $qIndex));
            }
            if (!isset($question['options']) || !is_array($question['options']) || count($question['options']) === 0) {
                throw new InvalidArgumentException(sprintf('questions[%d].options 至少需要一个选项。', $qIndex));
            }

            $question['options'] = array_values($question['options']);
            $keys = [];
            foreach ($question['options'] as $oIndex => &$option) {
                if (!isset($option['key']) || trim((string)$option['key']) === '') {
                    throw new InvalidArgumentException(sprintf('questions[%d].options[%d].key 不能为空。', $qIndex, $oIndex));
                }
                if (!isset($option['text']) || trim((string)$option['text']) === '') {
                    throw new InvalidArgumentException(sprintf('questions[%d].options[%d].text 不能为空。', $qIndex, $oIndex));
                }
                $option['key'] = strtoupper(trim((string)$option['key']));
                if (isset($keys[$option['key']])) {
                    throw new InvalidArgumentException(sprintf('questions[%d] 中选项 key "%s" 重复。', $qIndex, $option['key']));
                }
                $keys[$option['key']] = true;

                if (isset($option['score_override']) && !is_numeric($option['score_override'])) {
                    throw new InvalidArgumentException(sprintf('questions[%d].options[%d].score_override 需为数字。', $qIndex, $oIndex));
                }
                if (isset($option['map_result_code']) && trim((string)$option['map_result_code']) === '') {
                    $option['map_result_code'] = null;
                }
            }
            unset($option);
        }
        unset($question);
    }

    /**
     * 自动识别评分模式
     * 
     * 根据 JSON 数据的特征自动推断应该使用哪种评分模式
     * 
     * @param array $payload 完整的导入数据
     * @return array ['mode' => string, 'config' => ?array] 识别的模式和配置
     */
    private function detectScoringMode(array $payload): array
    {
        $test = $payload['test'] ?? [];
        $questions = $payload['questions'] ?? [];
        $results = $payload['results'] ?? [];
        $existingConfig = $test['scoring_config'] ?? null;

        // 如果已经明确指定了 scoring_mode，且不是 'simple'，则使用指定的模式
        if (isset($test['scoring_mode']) && $test['scoring_mode'] !== 'simple') {
            return [
                'mode' => $test['scoring_mode'],
                'config' => $existingConfig
            ];
        }

        // 1. 检查是否是 dimensions 模式
        if (is_array($existingConfig)) {
            if (isset($existingConfig['dimensions']) && isset($existingConfig['weights'])) {
                return [
                    'mode' => 'dimensions',
                    'config' => $existingConfig
                ];
            }
            
            // 2. 检查是否是 custom 模式的子策略
            if (isset($existingConfig['strategy'])) {
                $strategy = $existingConfig['strategy'];
                if (in_array($strategy, ['vote', 'weighted_sum', 'percentage_threshold', 'percentage'], true)) {
                    return [
                        'mode' => 'custom',
                        'config' => $existingConfig
                    ];
                }
            }
            
            // 3. 检查是否是 weighted_sum 模式（通过 question_weights 识别）
            if (isset($existingConfig['question_weights'])) {
                return [
                    'mode' => 'custom',
                    'config' => array_merge($existingConfig, ['strategy' => 'weighted_sum'])
                ];
            }
            
            // 4. 检查是否是 percentage_threshold 模式（通过 thresholds 识别）
            if (isset($existingConfig['thresholds'])) {
                return [
                    'mode' => 'custom',
                    'config' => array_merge($existingConfig, ['strategy' => 'percentage_threshold'])
                ];
            }
        }

        // 5. 检查是否是 vote 模式（投票模式）
        // 特征：大部分选项都有 map_result_code，且结果通过 code 匹配
        $totalOptions = 0;
        $optionsWithMapCode = 0;
        $resultCodes = [];
        $hasScoreRanges = false;

        foreach ($results as $result) {
            $resultCodes[] = strtoupper(trim((string)($result['code'] ?? '')));
            if (isset($result['min_score']) || isset($result['max_score'])) {
                $minScore = isset($result['min_score']) ? (float)$result['min_score'] : 0;
                $maxScore = isset($result['max_score']) ? (float)$result['max_score'] : 0;
                if ($minScore > 0 || $maxScore > 0) {
                    $hasScoreRanges = true;
                }
            }
        }

        foreach ($questions as $question) {
            $options = $question['options'] ?? [];
            foreach ($options as $option) {
                $totalOptions++;
                if (isset($option['map_result_code']) && trim((string)$option['map_result_code']) !== '') {
                    $optionsWithMapCode++;
                }
            }
        }

        // 如果超过 70% 的选项有 map_result_code，且结果没有分数区间，可能是投票模式
        if ($totalOptions > 0 && ($optionsWithMapCode / $totalOptions) >= 0.7 && !$hasScoreRanges) {
            // 验证 map_result_code 是否与结果 code 匹配
            $matchedCodes = 0;
            foreach ($questions as $question) {
                foreach ($question['options'] ?? [] as $option) {
                    if (isset($option['map_result_code'])) {
                        $mapCode = strtoupper(trim((string)$option['map_result_code']));
                        if (in_array($mapCode, $resultCodes, true)) {
                            $matchedCodes++;
                        }
                    }
                }
            }
            
            // 如果匹配的代码数量足够，识别为投票模式
            if ($matchedCodes >= $optionsWithMapCode * 0.8) {
                return [
                    'mode' => 'custom',
                    'config' => [
                        'strategy' => 'vote',
                        'vote_threshold' => 0,
                        'tie_breaker' => 'first'
                    ]
                ];
            }
        }

        // 6. 检查是否是 range 模式
        // 特征：结果有 min_score/max_score 区间，且有 option_scores 或 score_override
        if ($hasScoreRanges) {
            $hasOptionScores = false;
            if (is_array($existingConfig) && isset($existingConfig['option_scores'])) {
                $hasOptionScores = true;
            } else {
                // 检查是否有 score_override
                foreach ($questions as $question) {
                    foreach ($question['options'] ?? [] as $option) {
                        if (isset($option['score_override']) && is_numeric($option['score_override'])) {
                            $hasOptionScores = true;
                            break 2;
                        }
                    }
                }
            }
            
            if ($hasOptionScores) {
                return [
                    'mode' => 'range',
                    'config' => $existingConfig
                ];
            }
        }

        // 7. 默认使用 simple 模式
        return [
            'mode' => 'simple',
            'config' => $existingConfig
        ];
    }
}


