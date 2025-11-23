<?php
/**
 * 通用测验评分引擎（重构版）
 *
 * 统一入口：
 *   $detail = ScoreEngine::score($testRow, $answers, $pdo);
 *
 * - $testRow 来自 tests 表
 * - $answers 形如 [question_id => option_id, ...]（即 $_POST['q']）
 * - 返回：
 *   [
 *     'total_score'      => float,          // simple 模式总分
 *     'dimension_scores' => [dim => score], // dimensions 模式维度分布
 *     'result'           => array|null,     // 对应 results 表中的一行
 *   ]
 *
 * 支持模式：
 *   - simple     : 单维度加总，按 min_score / max_score 出结果
 *   - dimensions : 多维度，根据 scoring_config.weights 累加，取最高维度对应 code 的结果
 */
class ScoreEngine
{
    /** @var array|null */
    protected static $lastDetail = null;

    /**
     * 主入口
     *
     * @param array $test   tests 表的整行
     * @param array $answers [question_id => option_id, ...]
     * @param \PDO  $pdo
     * @return array
     */
    public static function score(array $test, array $answers, \PDO $pdo): array
    {
        self::$lastDetail = null;

        $testId      = isset($test['id']) ? (int)$test['id'] : 0;
        require_once __DIR__ . '/Constants.php';
        $scoringMode = $test['scoring_mode'] ?? Constants::SCORING_MODE_SIMPLE;
        $configRaw   = $test['scoring_config'] ?? null;
        $config      = null;

        // 解析 scoring_config JSON
        if (is_string($configRaw) && $configRaw !== '') {
            try {
                $decoded = json_decode($configRaw, true);
                if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                    $config = $decoded;
                }
            } catch (\Throwable $e) {
                // 忽略解析错误，后面再 fallback
            }
        } elseif (is_array($configRaw)) {
            $config = $configRaw;
        }

        // 规范化 answers：只保留 int question_id => int option_id
        $normalizedAnswers = [];
        foreach ($answers as $qId => $optId) {
            $qId   = (int)$qId;
            $optId = (int)$optId;
            if ($qId > 0 && $optId > 0) {
                $normalizedAnswers[$qId] = $optId;
            }
        }

        if (empty($normalizedAnswers) || $testId <= 0) {
            // 记录警告日志，便于调试
            if (class_exists('ErrorHandler')) {
                ErrorHandler::logWarning(
                    'ScoreEngine: 无效输入',
                    [
                        'testId' => $testId,
                        'answersCount' => count($answers),
                        'normalizedCount' => count($normalizedAnswers),
                    ]
                );
            }
            
            return [
                'total_score'      => 0.0,
                'dimension_scores' => [],
                'result'           => null,
            ];
        }

        // 预加载当前测验涉及到的所有选项
        $questionIds = array_keys($normalizedAnswers);
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

        $optStmt = $pdo->prepare(
            "SELECT * FROM question_options WHERE question_id IN ($placeholders)"
        );
        $optStmt->execute($questionIds);
        $options = $optStmt->fetchAll(\PDO::FETCH_ASSOC);

        // 构建 [question_id][option_id] => row
        $optionsByQuestion = [];
        foreach ($options as $optRow) {
            $qid = (int)$optRow['question_id'];
            $oid = (int)$optRow['id'];
            if (!isset($optionsByQuestion[$qid])) {
                $optionsByQuestion[$qid] = [];
            }
            $optionsByQuestion[$qid][$oid] = $optRow;
        }

        // 根据 scoring_mode 分派
        $mode = strtolower($scoringMode ?: Constants::SCORING_MODE_SIMPLE);
        switch ($mode) {
            case Constants::SCORING_MODE_DIMENSIONS:
                $detail = self::scoreDimensions($testId, $normalizedAnswers, $optionsByQuestion, $config, $pdo);
                break;

            case Constants::SCORING_MODE_SIMPLE:
            case Constants::SCORING_MODE_RANGE:
            case Constants::SCORING_MODE_CUSTOM:
            default:
                // 目前 range / custom 也先退化为 simple，保证系统不会炸
                $detail = self::scoreSimple($testId, $normalizedAnswers, $optionsByQuestion, $pdo);
                break;
        }

        self::$lastDetail = $detail;
        return $detail;
    }

    /**
     * 获取最近一次 score 的细节（可选）
     */
    public static function getLastDetail(): ?array
    {
        return self::$lastDetail;
    }

    /**
     * simple 模式：score_value 累加 + min/max_score 出结果
     */
    protected static function scoreSimple(
        int $testId,
        array $answers,
        array $optionsByQuestion,
        \PDO $pdo
    ): array {
        $total = 0;

        foreach ($answers as $qId => $optId) {
            if (!isset($optionsByQuestion[$qId][$optId])) {
                // 找不到选项，跳过
                continue;
            }
            $optRow   = $optionsByQuestion[$qId][$optId];
            $scoreVal = isset($optRow['score_value']) ? (int)$optRow['score_value'] : 0;
            $total   += $scoreVal;
        }

        // 根据 total 找结果
        $result = self::findResultByScore($testId, $total, $pdo);

        return [
            'total_score'      => (float)$total,
            'dimension_scores' => [],
            'result'           => $result,
        ];
    }

    /**
     * dimensions 模式：
     * - 扫描 scoring_config["dimensions"] 得到维度列表
     * - 用 scoring_config["weights"][question_id][option_key][dim] 累加分数
     * - 取分最高的维度 dim，按 code = dim 在 results 里找结果
     *
     * scoring_config 示例：
     * {
     *   "dimensions": ["CAT","DOG","FOX","DEER","OWL"],
     *   "weights": {
     *     "32": {
     *       "A": {"CAT":2},
     *       "B": {"DOG":2},
     *       "C": {"FOX":2},
     *       "D": {"DEER":2}
     *     },
     *     "33": {
     *       "A": {"CAT":1,"FOX":1},
     *       "B": {"DOG":1,"OWL":1}
     *     }
     *   }
     * }
     */
    protected static function scoreDimensions(
        int $testId,
        array $answers,
        array $optionsByQuestion,
        ?array $config,
        \PDO $pdo
    ): array {
        // 1. 初始化维度
        $dims = [];
        if ($config && !empty($config['dimensions']) && is_array($config['dimensions'])) {
            foreach ($config['dimensions'] as $dimKey) {
                $dimKey = (string)$dimKey;
                if ($dimKey !== '') {
                    $dims[$dimKey] = 0;
                }
            }
        }

        // 如果配置不完整，退回 simple 模式，避免直接炸掉
        if (!$dims) {
            // 记录警告日志
            if (class_exists('ErrorHandler')) {
                ErrorHandler::logWarning(
                    'ScoreEngine: dimensions 模式配置不完整，退回 simple 模式',
                    ['testId' => $testId]
                );
            }
            
            return self::scoreSimple($testId, $answers, $optionsByQuestion, $pdo);
        }

        $weights = $config['weights'] ?? [];
        $mapping = $config['mapping'] ?? null; // 支持旧的 mapping 格式

        // 如果使用旧的 mapping 格式，需要构建 question_id 到 q1, q2 的映射
        $questionIdToQKey = null;
        if ($mapping && empty($weights)) {
            // 获取所有问题的 ID 和排序，构建映射
            $questionIds = array_keys($answers);
            if (!empty($questionIds)) {
                $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
                $qStmt = $pdo->prepare(
                    "SELECT id, sort_order FROM questions 
                     WHERE id IN ($placeholders) AND test_id = ?
                     ORDER BY sort_order ASC, id ASC"
                );
                $qStmt->execute(array_merge($questionIds, [$testId]));
                $questions = $qStmt->fetchAll(\PDO::FETCH_ASSOC);
                
                $questionIdToQKey = [];
                $index = 1;
                foreach ($questions as $q) {
                    $questionIdToQKey[(int)$q['id']] = 'q' . $index;
                    $index++;
                }
            }
        }

        // 2. 遍历答案，根据 weights 或 mapping 累加各维度
        foreach ($answers as $qId => $optId) {
            if (!isset($optionsByQuestion[$qId][$optId])) {
                continue;
            }
            $optRow    = $optionsByQuestion[$qId][$optId];
            $optionKey = isset($optRow['option_key']) ? (string)$optRow['option_key'] : '';
            if ($optionKey === '') {
                continue;
            }

            // 确定使用哪个配置键
            $configKey = null;
            $dimWeights = null;

            if (!empty($weights)) {
                // 使用新的 weights 格式
                $qKey = (string)$qId;
                if (isset($weights[$qKey][$optionKey]) && is_array($weights[$qKey][$optionKey])) {
                    $dimWeights = $weights[$qKey][$optionKey];
                }
            } elseif ($mapping && $questionIdToQKey) {
                // 使用旧的 mapping 格式
                $qKey = $questionIdToQKey[$qId] ?? null;
                if ($qKey && isset($mapping[$qKey][$optionKey]) && is_array($mapping[$qKey][$optionKey])) {
                    $dimWeights = $mapping[$qKey][$optionKey];
                }
            }

            if (!$dimWeights) {
                continue;
            }

            // 累加维度分数
            foreach ($dimWeights as $dimKey => $val) {
                $dimKey = (string)$dimKey;
                if (!array_key_exists($dimKey, $dims)) {
                    // 未在 dimensions 声明的维度忽略
                    continue;
                }
                $dims[$dimKey] += (int)$val;
            }
        }

        // 3. 计算 total_score（所有维度和，仅作展示）
        $total = 0;
        foreach ($dims as $v) {
            $total += $v;
        }

        // 4. 选择最高维度，按 code 匹配 results
        $result = null;
        if ($dims) {
            arsort($dims); // 从大到小排序
            $topDim = array_key_first($dims);
            $result = self::findResultByCode($testId, $topDim, $pdo);
        }

        return [
            'total_score'      => (float)$total,
            'dimension_scores' => $dims,
            'result'           => $result,
        ];
    }

    /**
     * 按分数区间在 results 里查结果（simple 模式）
     */
    protected static function findResultByScore(int $testId, int $score, \PDO $pdo): ?array
    {
        try {
            // 先严格匹配 min <= score <= max
            // 注意：使用不同的参数名避免 PDO 参数绑定问题
            $stmt = $pdo->prepare(
                "SELECT * FROM results
                 WHERE test_id = :tid
                   AND min_score IS NOT NULL
                   AND max_score IS NOT NULL
                   AND min_score <= :score_min
                   AND max_score >= :score_max
                 ORDER BY id ASC
                 LIMIT 1"
            );
            $stmt->execute([
                ':tid'       => $testId,
                ':score_min' => $score,
                ':score_max' => $score,
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        } catch (\PDOException $e) {
            // 记录错误但继续尝试其他查询
            if (class_exists('ErrorHandler')) {
                ErrorHandler::logError(
                    'ScoreEngine::findResultByScore 第一次查询失败',
                    [
                        'testId' => $testId,
                        'score' => $score,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        try {
            // 退而求其次：选 min_score <= score 的最高一档
            $stmt = $pdo->prepare(
                "SELECT * FROM results
                 WHERE test_id = :tid
                   AND min_score IS NOT NULL
                   AND min_score <= :score
                 ORDER BY min_score DESC
                 LIMIT 1"
            );
            $stmt->execute([
                ':tid'   => $testId,
                ':score' => $score,
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        } catch (\PDOException $e) {
            // 记录错误但继续尝试最后一个查询
            if (class_exists('ErrorHandler')) {
                ErrorHandler::logError(
                    'ScoreEngine::findResultByScore 第二次查询失败',
                    [
                        'testId' => $testId,
                        'score' => $score,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        try {
            // 再不行就选该测验的第一条结果，避免完全没有结果
            $stmt = $pdo->prepare(
                "SELECT * FROM results
                 WHERE test_id = :tid
                 ORDER BY id ASC
                 LIMIT 1"
            );
            $stmt->execute([':tid' => $testId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\PDOException $e) {
            // 如果最后一个查询也失败，记录错误并返回 null
            if (class_exists('ErrorHandler')) {
                ErrorHandler::logError(
                    'ScoreEngine::findResultByScore 所有查询都失败',
                    [
                        'testId' => $testId,
                        'score' => $score,
                        'error' => $e->getMessage(),
                    ]
                );
            }
            return null;
        }
    }

    /**
     * 按 code 在 results 里查结果（dimensions 模式）
     */
    protected static function findResultByCode(int $testId, string $code, \PDO $pdo): ?array
    {
        try {
            $stmt = $pdo->prepare(
                "SELECT * FROM results
                 WHERE test_id = :tid AND code = :code
                 LIMIT 1"
            );
            $stmt->execute([
                ':tid'  => $testId,
                ':code' => $code,
            ]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);
            if ($row) {
                return $row;
            }
        } catch (\PDOException $e) {
            // 记录错误但继续尝试备用查询
            if (class_exists('ErrorHandler')) {
                ErrorHandler::logError(
                    'ScoreEngine::findResultByCode 第一次查询失败',
                    [
                        'testId' => $testId,
                        'code' => $code,
                        'error' => $e->getMessage(),
                    ]
                );
            }
        }

        try {
            // 找不到对应 code 时，退回该测验的第一条结果
            $stmt = $pdo->prepare(
                "SELECT * FROM results
                 WHERE test_id = :tid
                 ORDER BY id ASC
                 LIMIT 1"
            );
            $stmt->execute([':tid' => $testId]);
            $row = $stmt->fetch(\PDO::FETCH_ASSOC);

            return $row ?: null;
        } catch (\PDOException $e) {
            // 如果备用查询也失败，记录错误并返回 null
            if (class_exists('ErrorHandler')) {
                ErrorHandler::logError(
                    'ScoreEngine::findResultByCode 所有查询都失败',
                    [
                        'testId' => $testId,
                        'code' => $code,
                        'error' => $e->getMessage(),
                    ]
                );
            }
            return null;
        }
    }
}
