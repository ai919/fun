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
        // 只选择需要的字段，避免 SELECT * 加载不必要的数据
        $questionIds = array_keys($normalizedAnswers);
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));

        $optStmt = $pdo->prepare(
            "SELECT id, question_id, option_key, option_text, score_value, map_result_code FROM question_options WHERE question_id IN ($placeholders)"
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

            case Constants::SCORING_MODE_RANGE:
                $detail = self::scoreRange($testId, $normalizedAnswers, $optionsByQuestion, $config, $pdo);
                break;

            case Constants::SCORING_MODE_CUSTOM:
                $detail = self::scoreCustom($testId, $normalizedAnswers, $optionsByQuestion, $config, $pdo);
                break;

            case Constants::SCORING_MODE_SIMPLE:
            default:
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

        $weightsUseOrderKeys = self::weightsUseQuestionOrderKeys($weights);

        // 如果需要按题目顺序映射（旧 mapping 或 weights 使用 q1/q2 或 "1"/"2" 写法），构建 question_id => qN 或 "N"
        $questionIdToQKey = null;
        if (($mapping && empty($weights)) || $weightsUseOrderKeys) {
            // 检测 mapping 或 weights 的键格式：如果使用纯数字字符串（"1", "2"），就用纯数字；否则用 "q1", "q2"
            $useNumericKeys = false;
            $sourceToCheck = !empty($weights) ? $weights : $mapping;
            if ($sourceToCheck && !empty($sourceToCheck)) {
                $firstKey = array_key_first($sourceToCheck);
                // 如果第一个键是纯数字字符串（如 "1", "2"），则使用纯数字格式
                if (is_string($firstKey) && preg_match('/^\d+$/', $firstKey)) {
                    $useNumericKeys = true;
                }
            }
            $questionIdToQKey = self::buildQuestionOrderMap($answers, $testId, $pdo, $useNumericKeys);
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
                // 使用新的 weights 格式（支持 question_id 或 q1/q2 写法）
                $qKeyById = (string)$qId;
                if (isset($weights[$qKeyById][$optionKey]) && is_array($weights[$qKeyById][$optionKey])) {
                    $dimWeights = $weights[$qKeyById][$optionKey];
                } elseif ($questionIdToQKey) {
                    $qKeyOrder = $questionIdToQKey[$qId] ?? null;
                    if ($qKeyOrder && isset($weights[$qKeyOrder][$optionKey]) && is_array($weights[$qKeyOrder][$optionKey])) {
                        $dimWeights = $weights[$qKeyOrder][$optionKey];
                    }
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
                // 支持小数权重，避免被 int 转换抹成 0
                $dims[$dimKey] += (float)$val;
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
            
            // 检查是否有多个维度得分相同（可能导致结果不稳定）
            $topScore = reset($dims);
            $topDims = [];
            foreach ($dims as $dim => $score) {
                if (abs($score - $topScore) < 0.001) { // 允许浮点数误差
                    $topDims[] = $dim;
                } else {
                    break; // 已经排序，后面的分数肯定更小
                }
            }
            
            // 如果有多个维度得分相同，记录警告
            if (count($topDims) > 1) {
                if (class_exists('ErrorHandler')) {
                    ErrorHandler::logWarning(
                        'ScoreEngine: 多个维度得分相同，可能影响结果准确性',
                        [
                            'testId' => $testId,
                            'topDims' => $topDims,
                            'score' => $topScore,
                            'allScores' => $dims,
                        ]
                    );
                }
            }
            
            // 选择第一个最高维度（保持原有逻辑，但如果有多个相同，可以考虑随机选择）
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
     * 检查 weights 是否使用了 q1/q2 或 "1"/"2" 这种题目顺序键
     */
    protected static function weightsUseQuestionOrderKeys($weights): bool
    {
        if (!is_array($weights) || empty($weights)) {
            return false;
        }
        foreach ($weights as $key => $value) {
            // 支持 "q1", "q2" 格式或纯数字字符串 "1", "2" 格式
            if (is_string($key) && (preg_match('/^q\d+$/i', $key) || preg_match('/^\d+$/', $key))) {
                return true;
            }
        }
        return false;
    }

    /**
     * 构建 question_id => qN 或 question_id => "N" 映射，便于按题目顺序读写配置
     * 
     * @param array $answers
     * @param int $testId
     * @param \PDO $pdo
     * @param bool $useNumericKeys 如果为 true，生成 "1", "2", "3"；否则生成 "q1", "q2", "q3"
     * @return array|null
     */
    protected static function buildQuestionOrderMap(array $answers, int $testId, \PDO $pdo, bool $useNumericKeys = false): ?array
    {
        $questionIds = array_keys($answers);
        if (empty($questionIds)) {
            return null;
        }

        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
        $qStmt = $pdo->prepare(
            "SELECT id, sort_order FROM questions 
             WHERE id IN ($placeholders) AND test_id = ?
             ORDER BY sort_order ASC, id ASC"
        );
        $qStmt->execute(array_merge($questionIds, [$testId]));
        $questions = $qStmt->fetchAll(\PDO::FETCH_ASSOC);

        if (!$questions) {
            return null;
        }

        $questionIdToQKey = [];
        $index = 1;
        foreach ($questions as $q) {
            $questionId = isset($q['id']) ? (int)$q['id'] : null;
            if ($questionId) {
                // 根据 useNumericKeys 参数决定键格式
                $questionIdToQKey[$questionId] = $useNumericKeys ? (string)$index : ('q' . $index);
                $index++;
            }
        }

        return $questionIdToQKey ?: null;
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
     * range 模式：类似 simple，但支持更灵活的区间配置
     */
    protected static function scoreRange(
        int $testId,
        array $answers,
        array $optionsByQuestion,
        ?array $config,
        \PDO $pdo
    ): array {
        // 计算总分（与 simple 模式相同）
        $total = 0;
        foreach ($answers as $qId => $optId) {
            if (!isset($optionsByQuestion[$qId][$optId])) {
                continue;
            }
            $optRow   = $optionsByQuestion[$qId][$optId];
            $scoreVal = isset($optRow['score_value']) ? (int)$optRow['score_value'] : 0;
            $total   += $scoreVal;
        }

        // 如果配置了自定义区间，优先使用配置的区间
        $result = null;
        if ($config && !empty($config['ranges']) && is_array($config['ranges'])) {
            foreach ($config['ranges'] as $range) {
                $min = isset($range['min']) ? (float)$range['min'] : null;
                $max = isset($range['max']) ? (float)$range['max'] : null;
                $code = isset($range['code']) ? (string)$range['code'] : null;
                
                if ($min !== null && $max !== null && $total >= $min && $total <= $max) {
                    if ($code) {
                        $result = self::findResultByCode($testId, $code, $pdo);
                    } else {
                        // 如果没有 code，使用分数区间查找
                        $result = self::findResultByScore($testId, (int)$total, $pdo);
                    }
                    break;
                }
            }
        }

        // 如果没有通过配置找到结果，回退到标准的分数区间查找
        if (!$result) {
            $result = self::findResultByScore($testId, (int)$total, $pdo);
        }

        return [
            'total_score'      => (float)$total,
            'dimension_scores' => [],
            'result'           => $result,
        ];
    }

    /**
     * custom 模式：支持多种自定义计算策略
     * 
     * 支持的策略：
     * - percentage: 百分比计算
     * - weighted_average: 加权平均
     * - conditional: 条件逻辑
     * - multi_result: 多结果组合
     * - vote: 投票/计数模式（按 map_result_code 计数）
     * - weighted_sum: 加权累加模式（每道题有不同权重）
     * - percentage_threshold: 百分比阈值模式（根据百分比阈值匹配结果）
     * - dimensions (fallback): 如果没有指定策略，使用 dimensions 模式
     */
    protected static function scoreCustom(
        int $testId,
        array $answers,
        array $optionsByQuestion,
        ?array $config,
        \PDO $pdo
    ): array {
        if (!$config || !is_array($config)) {
            // 配置为空，回退到 simple 模式
            if (class_exists('ErrorHandler')) {
                ErrorHandler::logWarning(
                    'ScoreEngine: custom 模式配置为空，退回 simple 模式',
                    ['testId' => $testId]
                );
            }
            return self::scoreSimple($testId, $answers, $optionsByQuestion, $pdo);
        }

        $strategy = isset($config['strategy']) ? strtolower((string)$config['strategy']) : 'dimensions';

        switch ($strategy) {
            case 'percentage':
                return self::scoreCustomPercentage($testId, $answers, $optionsByQuestion, $config, $pdo);
            
            case 'weighted_average':
                return self::scoreCustomWeightedAverage($testId, $answers, $optionsByQuestion, $config, $pdo);
            
            case 'conditional':
                return self::scoreCustomConditional($testId, $answers, $optionsByQuestion, $config, $pdo);
            
            case 'multi_result':
                return self::scoreCustomMultiResult($testId, $answers, $optionsByQuestion, $config, $pdo);
            
            case 'vote':
                return self::scoreCustomVote($testId, $answers, $optionsByQuestion, $config, $pdo);
            
            case 'weighted_sum':
                return self::scoreCustomWeightedSum($testId, $answers, $optionsByQuestion, $config, $pdo);
            
            case 'percentage_threshold':
                return self::scoreCustomPercentageThreshold($testId, $answers, $optionsByQuestion, $config, $pdo);
            
            case 'dimensions':
            default:
                // 默认使用 dimensions 模式
                return self::scoreDimensions($testId, $answers, $optionsByQuestion, $config, $pdo);
        }
    }

    /**
     * custom 策略：百分比模式
     * 计算各维度得分百分比，选择百分比最高的维度
     */
    protected static function scoreCustomPercentage(
        int $testId,
        array $answers,
        array $optionsByQuestion,
        ?array $config,
        \PDO $pdo
    ): array {
        // 先按 dimensions 模式计算原始分数
        $dimResult = self::scoreDimensions($testId, $answers, $optionsByQuestion, $config, $pdo);
        $dims = $dimResult['dimension_scores'] ?? [];

        if (empty($dims)) {
            return $dimResult;
        }

        // 计算总分
        $total = array_sum($dims);
        
        // 计算百分比
        $percentages = [];
        foreach ($dims as $dim => $score) {
            $percentages[$dim] = $total > 0 ? ($score / $total) * 100 : 0;
        }

        // 选择百分比最高的维度
        arsort($percentages);
        $topDim = array_key_first($percentages);
        $result = self::findResultByCode($testId, $topDim, $pdo);

        return [
            'total_score'      => $dimResult['total_score'],
            'dimension_scores' => $percentages, // 返回百分比而不是原始分数
            'result'           => $result,
        ];
    }

    /**
     * custom 策略：加权平均模式
     * 对维度分数进行加权平均，然后选择结果
     */
    protected static function scoreCustomWeightedAverage(
        int $testId,
        array $answers,
        array $optionsByQuestion,
        ?array $config,
        \PDO $pdo
    ): array {
        // 先按 dimensions 模式计算原始分数
        $dimResult = self::scoreDimensions($testId, $answers, $optionsByQuestion, $config, $pdo);
        $dims = $dimResult['dimension_scores'] ?? [];

        if (empty($dims)) {
            return $dimResult;
        }

        // 获取维度权重配置
        $dimensionWeights = $config['dimension_weights'] ?? [];
        
        // 如果没有配置维度权重，使用 dimensions 模式的默认逻辑
        if (empty($dimensionWeights)) {
            return $dimResult;
        }

        // 计算加权平均
        $weightedScores = [];
        $totalWeight = 0;
        foreach ($dims as $dim => $score) {
            $weight = isset($dimensionWeights[$dim]) ? (float)$dimensionWeights[$dim] : 1.0;
            $weightedScores[$dim] = $score * $weight;
            $totalWeight += $weight;
        }

        // 归一化（可选）
        if ($totalWeight > 0) {
            foreach ($weightedScores as $dim => $score) {
                $weightedScores[$dim] = $score / $totalWeight;
            }
        }

        // 选择加权分数最高的维度
        arsort($weightedScores);
        $topDim = array_key_first($weightedScores);
        $result = self::findResultByCode($testId, $topDim, $pdo);

        return [
            'total_score'      => $dimResult['total_score'],
            'dimension_scores' => $weightedScores,
            'result'           => $result,
        ];
    }

    /**
     * custom 策略：条件逻辑模式
     * 根据条件规则选择结果
     */
    protected static function scoreCustomConditional(
        int $testId,
        array $answers,
        array $optionsByQuestion,
        ?array $config,
        \PDO $pdo
    ): array {
        // 先按 dimensions 模式计算分数
        $dimResult = self::scoreDimensions($testId, $answers, $optionsByQuestion, $config, $pdo);
        $dims = $dimResult['dimension_scores'] ?? [];

        $rules = $config['rules'] ?? [];
        $defaultResultCode = $config['default_result_code'] ?? null;

        // 按顺序检查规则
        foreach ($rules as $rule) {
            $condition = $rule['condition'] ?? null;
            $resultCode = $rule['result_code'] ?? null;

            if (!$condition || !$resultCode) {
                continue;
            }

            $dim = $condition['dimension'] ?? null;
            $operator = $condition['operator'] ?? null;
            $value = $condition['value'] ?? null;

            if (!$dim || !$operator || $value === null) {
                continue;
            }

            $dimScore = $dims[$dim] ?? 0;
            $matched = false;

            switch ($operator) {
                case '>':
                    $matched = $dimScore > $value;
                    break;
                case '>=':
                    $matched = $dimScore >= $value;
                    break;
                case '<':
                    $matched = $dimScore < $value;
                    break;
                case '<=':
                    $matched = $dimScore <= $value;
                    break;
                case '==':
                case '=':
                    $matched = abs($dimScore - $value) < 0.001;
                    break;
                case '!=':
                    $matched = abs($dimScore - $value) >= 0.001;
                    break;
            }

            if ($matched) {
                $result = self::findResultByCode($testId, $resultCode, $pdo);
                return [
                    'total_score'      => $dimResult['total_score'],
                    'dimension_scores' => $dims,
                    'result'           => $result,
                ];
            }
        }

        // 没有匹配的规则，使用默认结果
        $result = null;
        if ($defaultResultCode) {
            $result = self::findResultByCode($testId, $defaultResultCode, $pdo);
        } else {
            // 如果没有默认结果，使用 dimensions 模式的结果
            $result = $dimResult['result'];
        }

        return [
            'total_score'      => $dimResult['total_score'],
            'dimension_scores' => $dims,
            'result'           => $result,
        ];
    }

    /**
     * custom 策略：多结果组合模式
     * 支持返回主结果和次结果（如：主要原型 + 次要原型）
     */
    protected static function scoreCustomMultiResult(
        int $testId,
        array $answers,
        array $optionsByQuestion,
        ?array $config,
        \PDO $pdo
    ): array {
        // 先按 dimensions 模式计算分数
        $dimResult = self::scoreDimensions($testId, $answers, $optionsByQuestion, $config, $pdo);
        $dims = $dimResult['dimension_scores'] ?? [];

        if (empty($dims)) {
            return $dimResult;
        }

        // 排序维度分数
        arsort($dims);
        $dimKeys = array_keys($dims);

        // 获取结果选择配置
        $resultSelection = $config['result_selection'] ?? 'top_one';
        $resultFormat = $config['result_format'] ?? 'PRIMARY';

        $primaryDim = $dimKeys[0] ?? null;
        $secondaryDim = isset($dimKeys[1]) ? $dimKeys[1] : null;

        // 选择主结果
        $result = null;
        if ($primaryDim) {
            $result = self::findResultByCode($testId, $primaryDim, $pdo);
        }

        // 如果需要次结果，可以在 result 中添加额外信息
        // 注意：当前返回结构只支持单个 result，如果需要返回多个结果，
        // 可以在 result 的 description 或其他字段中存储组合信息
        // 或者扩展返回结构（但这需要修改调用方）

        return [
            'total_score'      => $dimResult['total_score'],
            'dimension_scores' => $dims,
            'result'           => $result,
            // 可以添加额外信息
            'primary_dimension'   => $primaryDim,
            'secondary_dimension' => $secondaryDim,
        ];
    }

    /**
     * custom 策略：投票/计数模式
     * 根据选项的 map_result_code 直接计数，选择被选次数最多的结果
     */
    protected static function scoreCustomVote(
        int $testId,
        array $answers,
        array $optionsByQuestion,
        ?array $config,
        \PDO $pdo
    ): array {
        $votes = []; // [result_code => count]
        $totalVotes = 0;

        // 遍历所有答案，统计每个 result_code 的投票数
        foreach ($answers as $qId => $optId) {
            if (!isset($optionsByQuestion[$qId][$optId])) {
                continue;
            }
            $optRow = $optionsByQuestion[$qId][$optId];
            $resultCode = isset($optRow['map_result_code']) ? trim((string)$optRow['map_result_code']) : '';
            
            if ($resultCode !== '') {
                if (!isset($votes[$resultCode])) {
                    $votes[$resultCode] = 0;
                }
                $votes[$resultCode]++;
                $totalVotes++;
            }
        }

        // 如果没有投票，回退到 simple 模式
        if (empty($votes)) {
            if (class_exists('ErrorHandler')) {
                ErrorHandler::logWarning(
                    'ScoreEngine: vote 模式没有找到任何 map_result_code，退回 simple 模式',
                    ['testId' => $testId]
                );
            }
            return self::scoreSimple($testId, $answers, $optionsByQuestion, $pdo);
        }

        // 检查投票阈值（如果配置了）
        $voteThreshold = isset($config['vote_threshold']) ? (int)$config['vote_threshold'] : 0;
        if ($voteThreshold > 0) {
            $votes = array_filter($votes, function($count) use ($voteThreshold) {
                return $count >= $voteThreshold;
            });
        }

        if (empty($votes)) {
            // 所有投票都不满足阈值，回退到 simple 模式
            return self::scoreSimple($testId, $answers, $optionsByQuestion, $pdo);
        }

        // 排序，找出得票最多的 result_code
        arsort($votes);
        $topVotes = reset($votes);
        $topCodes = [];
        foreach ($votes as $code => $count) {
            if ($count === $topVotes) {
                $topCodes[] = $code;
            } else {
                break; // 已经排序，后面的肯定更少
            }
        }

        // 处理平局
        $tieBreaker = isset($config['tie_breaker']) ? strtolower((string)$config['tie_breaker']) : 'first';
        $selectedCode = null;

        if (count($topCodes) === 1) {
            $selectedCode = $topCodes[0];
        } else {
            // 多个代码得票相同
            if (class_exists('ErrorHandler')) {
                ErrorHandler::logWarning(
                    'ScoreEngine: vote 模式出现平局',
                    [
                        'testId' => $testId,
                        'topCodes' => $topCodes,
                        'votes' => $topVotes,
                    ]
                );
            }

            switch ($tieBreaker) {
                case 'random':
                    $selectedCode = $topCodes[array_rand($topCodes)];
                    break;
                case 'all':
                    // 返回所有平局的结果（这里只返回第一个，实际可能需要扩展返回结构）
                    $selectedCode = $topCodes[0];
                    break;
                case 'first':
                default:
                    $selectedCode = $topCodes[0];
                    break;
            }
        }

        // 查找结果
        $result = null;
        if ($selectedCode) {
            $result = self::findResultByCode($testId, $selectedCode, $pdo);
        }

        // 如果没有找到结果，回退到 simple 模式
        if (!$result) {
            return self::scoreSimple($testId, $answers, $optionsByQuestion, $pdo);
        }

        return [
            'total_score'      => (float)$totalVotes,
            'dimension_scores' => $votes, // 返回投票统计
            'result'           => $result,
        ];
    }

    /**
     * custom 策略：加权累加模式
     * 类似 Simple 模式，但每道题有不同的权重
     * 最终分数 = Σ(选项分数 × 题目权重)
     */
    protected static function scoreCustomWeightedSum(
        int $testId,
        array $answers,
        array $optionsByQuestion,
        ?array $config,
        \PDO $pdo
    ): array {
        $total = 0.0;
        $questionWeights = $config['question_weights'] ?? [];

        foreach ($answers as $qId => $optId) {
            if (!isset($optionsByQuestion[$qId][$optId])) {
                continue;
            }
            $optRow = $optionsByQuestion[$qId][$optId];
            $scoreVal = isset($optRow['score_value']) ? (float)$optRow['score_value'] : 0.0;
            
            // 获取题目权重（默认为 1.0）
            $qKey = (string)$qId;
            $weight = isset($questionWeights[$qKey]) ? (float)$questionWeights[$qKey] : 1.0;
            
            $total += $scoreVal * $weight;
        }

        // 根据总分找结果（与 simple 模式相同）
        $result = self::findResultByScore($testId, (int)round($total), $pdo);

        return [
            'total_score'      => $total,
            'dimension_scores' => [],
            'result'           => $result,
        ];
    }

    /**
     * custom 策略：百分比阈值模式
     * 计算各维度得分百分比，根据百分比阈值匹配结果（而不是简单的"最高维度"）
     * 可以识别"平衡型"结果
     */
    protected static function scoreCustomPercentageThreshold(
        int $testId,
        array $answers,
        array $optionsByQuestion,
        ?array $config,
        \PDO $pdo
    ): array {
        // 先按 dimensions 模式计算原始分数
        $dimResult = self::scoreDimensions($testId, $answers, $optionsByQuestion, $config, $pdo);
        $dims = $dimResult['dimension_scores'] ?? [];

        if (empty($dims)) {
            return $dimResult;
        }

        // 计算总分和百分比
        $total = array_sum($dims);
        $percentages = [];
        foreach ($dims as $dim => $score) {
            $percentages[$dim] = $total > 0 ? ($score / $total) * 100 : 0;
        }

        // 获取阈值配置
        $thresholds = $config['thresholds'] ?? [];
        if (empty($thresholds)) {
            // 如果没有配置阈值，回退到百分比模式（选择最高百分比）
            arsort($percentages);
            $topDim = array_key_first($percentages);
            $result = self::findResultByCode($testId, $topDim, $pdo);
            
            return [
                'total_score'      => $dimResult['total_score'],
                'dimension_scores'  => $percentages,
                'result'           => $result,
            ];
        }

        // 按顺序检查阈值规则
        $result = null;
        foreach ($thresholds as $resultCode => $threshold) {
            $matched = false;

            if (isset($threshold['dimension'])) {
                // 单个维度阈值检查
                $dim = (string)$threshold['dimension'];
                $dimPercentage = $percentages[$dim] ?? 0;
                
                $min = isset($threshold['min']) ? (float)$threshold['min'] : null;
                $max = isset($threshold['max']) ? (float)$threshold['max'] : null;
                
                if ($min !== null && $dimPercentage < $min) {
                    continue;
                }
                if ($max !== null && $dimPercentage > $max) {
                    continue;
                }
                $matched = true;
            } elseif (isset($threshold['all_dimensions'])) {
                // 所有维度阈值检查（用于"平衡型"）
                $allMatched = true;
                $dimConstraints = $threshold['all_dimensions'];
                
                foreach ($dimConstraints as $dim => $constraint) {
                    $dimPercentage = $percentages[$dim] ?? 0;
                    $min = isset($constraint['min']) ? (float)$constraint['min'] : null;
                    $max = isset($constraint['max']) ? (float)$constraint['max'] : null;
                    
                    if ($min !== null && $dimPercentage < $min) {
                        $allMatched = false;
                        break;
                    }
                    if ($max !== null && $dimPercentage > $max) {
                        $allMatched = false;
                        break;
                    }
                }
                $matched = $allMatched;
            } elseif (isset($threshold['min']) || isset($threshold['max'])) {
                // 通用阈值（所有维度都在范围内）
                $min = isset($threshold['min']) ? (float)$threshold['min'] : 0;
                $max = isset($threshold['max']) ? (float)$threshold['max'] : 100;
                
                $allInRange = true;
                foreach ($percentages as $dim => $pct) {
                    if ($pct < $min || $pct > $max) {
                        $allInRange = false;
                        break;
                    }
                }
                $matched = $allInRange;
            }

            if ($matched) {
                $result = self::findResultByCode($testId, (string)$resultCode, $pdo);
                break;
            }
        }

        // 如果没有匹配的阈值，使用默认逻辑（最高百分比）
        if (!$result) {
            $defaultResultCode = $config['default_result_code'] ?? null;
            if ($defaultResultCode) {
                $result = self::findResultByCode($testId, (string)$defaultResultCode, $pdo);
            } else {
                // 回退到最高百分比维度
                arsort($percentages);
                $topDim = array_key_first($percentages);
                $result = self::findResultByCode($testId, $topDim, $pdo);
            }
        }

        return [
            'total_score'      => $dimResult['total_score'],
            'dimension_scores' => $percentages, // 返回百分比
            'result'           => $result,
        ];
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
