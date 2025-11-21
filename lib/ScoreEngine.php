<?php

class ScoreEngine
{
    private static array $lastDetail = [
        'total_score'      => 0,
        'dimension_scores' => [],
    ];

    public static function score(array $test, array $answers, PDO $pdo): ?string
    {
        self::$lastDetail = [
            'total_score'      => 0,
            'dimension_scores' => [],
        ];

        $mode = strtolower($test['scoring_mode'] ?? 'simple');
        return match ($mode) {
            'dimensions' => self::scoreDimensions($test, $answers, $pdo),
            'range'      => self::scoreRange($test, $answers, $pdo),
            'custom'     => self::scoreCustom($test, $answers, $pdo),
            default      => self::scoreSimple($test, $answers, $pdo),
        };
    }

    public static function getLastDetail(): array
    {
        return self::$lastDetail;
    }

    private static function scoreSimple(array $test, array $answers, PDO $pdo): ?string
    {
        $options = self::loadSelectedOptions($answers, (int)$test['id'], $pdo);
        if (!$options) {
            return null;
        }
        $scores = [];
        foreach ($options as $row) {
            $code = strtoupper(trim((string)$row['map_result_code']));
            if ($code === '') {
                continue;
            }
            $value = isset($row['score_value']) ? (float)$row['score_value'] : 1.0;
            if (!isset($scores[$code])) {
                $scores[$code] = 0.0;
            }
            $scores[$code] += $value;
        }
        if (!$scores) {
            return null;
        }
        $bestCode  = null;
        $bestScore = -INF;
        foreach ($scores as $code => $score) {
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCode  = $code;
            }
        }
        self::$lastDetail['total_score'] = $bestScore > 0 ? $bestScore : array_sum($scores);
        return $bestCode;
    }

    private static function scoreDimensions(array $test, array $answers, PDO $pdo): ?string
    {
        $calc = self::calculateDimensions($test, $answers, $pdo);
        self::$lastDetail['dimension_scores'] = $calc['dimensions'] ?? [];
        self::$lastDetail['total_score']      = isset($calc['dimensions']) ? array_sum($calc['dimensions']) : 0;
        return $calc['code'] ?? null;
    }

    private static function scoreRange(array $test, array $answers, PDO $pdo): ?string
    {
        // TODO: implement range-based scoring
        return null;
    }

    private static function scoreCustom(array $test, array $answers, PDO $pdo): ?string
    {
        // Placeholder for custom scripts
        return null;
    }

    private static function loadSelectedOptions(array $answers, int $testId, PDO $pdo): array
    {
        $optionIds = [];
        foreach ($answers as $value) {
            if (is_array($value)) {
                continue;
            }
            $optionIds[] = (int)$value;
        }
        $optionIds = array_values(array_unique(array_filter($optionIds, static fn($v) => $v > 0)));
        if (!$optionIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT qo.*, q.test_id
             FROM question_options qo
             JOIN questions q ON q.id = qo.question_id
             WHERE qo.id IN ($placeholders)"
        );
        $stmt->execute($optionIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows || count($rows) !== count($optionIds)) {
            return [];
        }
        foreach ($rows as $row) {
            if ((int)$row['test_id'] !== $testId) {
                return [];
            }
        }
        return $rows;
    }

    private static function decodeConfig($config): array
    {
        if (is_array($config)) {
            return $config;
        }
        if (is_string($config) && $config !== '') {
            $decoded = json_decode($config, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }

    public static function calculateDimensions(array $test, array $answers, PDO $pdo): array
    {
        $config = [];
        if (!empty($test['scoring_config'])) {
            $config = json_decode($test['scoring_config'], true);
        }
        if (!is_array($config)) {
            return [
                'code' => null,
                'dimensions' => [],
                'primary_dimension' => null,
                'secondary_dimension' => null,
                'lowest_dimension' => null,
                'raw_profile' => null,
            ];
        }

        $dimensions = [];
        if (!empty($config['dimensions']) && is_array($config['dimensions'])) {
            foreach ($config['dimensions'] as $dimCode) {
                $dimensions[$dimCode] = 0;
            }
        }
        if (empty($dimensions)) {
            return [
                'code' => null,
                'dimensions' => [],
                'primary_dimension' => null,
                'secondary_dimension' => null,
                'lowest_dimension' => null,
                'raw_profile' => null,
            ];
        }

        $mapping = [];
        if (!empty($config['mapping']) && is_array($config['mapping'])) {
            $mapping = $config['mapping'];
        }

        $questions = [];
        $qStmt = $pdo->prepare("SELECT id, sort_order FROM questions WHERE test_id = :tid ORDER BY sort_order ASC, id ASC");
        $qStmt->execute([':tid' => (int)$test['id']]);
        $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

        $sortedQuestions = $questions;
        usort($sortedQuestions, function ($a, $b) {
            $sa = isset($a['sort_order']) ? (int)$a['sort_order'] : 0;
            $sb = isset($b['sort_order']) ? (int)$b['sort_order'] : 0;
            return $sa <=> $sb;
        });

        $questionIndexById = [];
        $index = 1;
        foreach ($sortedQuestions as $q) {
            if (!isset($q['id'])) {
                continue;
            }
            $questionIndexById[$q['id']] = $index;
            $index++;
        }

        // Map question_id => option_key from submitted answers (option ids)
        $optionRows = self::loadSelectedOptions($answers, (int)$test['id'], $pdo);
        $answerOptionKey = [];
        foreach ($optionRows as $row) {
            $qid = (int)($row['question_id'] ?? 0);
            $ok  = isset($row['option_key']) ? strtoupper(trim((string)$row['option_key'])) : '';
            if ($qid > 0 && $ok !== '') {
                $answerOptionKey[$qid] = $ok;
            }
        }

        foreach ($answers as $qid => $value) {
            $qid = (int)$qid;
            if (!isset($questionIndexById[$qid])) {
                continue;
            }
            $qIndex = $questionIndexById[$qid];
            $qKey = 'q' . $qIndex;

            $optionKey = $answerOptionKey[$qid] ?? null;
            if (!$optionKey) {
                if (is_string($value)) {
                    $optionKey = strtoupper(trim($value));
                } elseif (is_array($value) && isset($value['option_key'])) {
                    $optionKey = strtoupper(trim((string)$value['option_key']));
                }
            }

            if (!$optionKey) {
                continue;
            }
            if (!isset($mapping[$qKey]) || !isset($mapping[$qKey][$optionKey])) {
                continue;
            }

            $dimAdd = $mapping[$qKey][$optionKey];
            if (!is_array($dimAdd)) {
                continue;
            }

            foreach ($dimAdd as $dimCode => $inc) {
                if (!isset($dimensions[$dimCode])) {
                    $dimensions[$dimCode] = 0;
                }
                $dimensions[$dimCode] += (int)$inc;
            }
        }

        $sortedDims = $dimensions;
        arsort($sortedDims);

        $codes = array_keys($sortedDims);
        $primary = $codes[0] ?? null;
        $secondary = $codes[1] ?? null;
        $lowest = $codes[count($codes) - 1] ?? null;

        $profiles = [];
        if (!empty($config['result_profiles']) && is_array($config['result_profiles'])) {
            $profiles = $config['result_profiles'];
        }

        $selectedProfile = null;
        if (!empty($profiles)) {
            foreach ($profiles as $profile) {
                if (empty($profile['code']) || empty($profile['rule']) || !is_array($profile['rule'])) {
                    continue;
                }
                $rule = $profile['rule'];

                if (!empty($rule['primary']) && $rule['primary'] !== $primary) {
                    continue;
                }

                if (!empty($rule['secondary_prefer']) && is_array($rule['secondary_prefer'])) {
                    if ($secondary === null || !in_array($secondary, $rule['secondary_prefer'], true)) {
                        continue;
                    }
                }

                if (!empty($rule['low_prefer']) && is_array($rule['low_prefer'])) {
                    if ($lowest === null || !in_array($lowest, $rule['low_prefer'], true)) {
                        continue;
                    }
                }

                $selectedProfile = $profile;
                break;
            }

            if ($selectedProfile === null) {
                foreach ($profiles as $profile) {
                    $rule = $profile['rule'] ?? [];
                    if (!empty($rule['primary']) && $rule['primary'] === $primary) {
                        $selectedProfile = $profile;
                        break;
                    }
                }
                if ($selectedProfile === null) {
                    $selectedProfile = $profiles[0];
                }
            }
        }

        $resultCode = $selectedProfile['code'] ?? null;

        return [
            'code' => $resultCode,
            'dimensions' => $dimensions,
            'primary_dimension' => $primary,
            'secondary_dimension' => $secondary,
            'lowest_dimension' => $lowest,
            'raw_profile' => $selectedProfile,
        ];
    }
}
