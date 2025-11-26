<?php
/**
 * 检查特定测验的详细配置，找出"无论怎么选都是同一结果"的原因
 */

require __DIR__ . '/lib/db_connect.php';

$testId = isset($argv[1]) ? (int)$argv[1] : 7;

echo "=== 检查测验 #{$testId} ===\n\n";

$stmt = $pdo->prepare("SELECT id, slug, title, scoring_mode, scoring_config FROM tests WHERE id = ?");
$stmt->execute([$testId]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    echo "测验不存在\n";
    exit(1);
}

echo "测验: {$test['title']} ({$test['slug']})\n";
echo "计分模式: {$test['scoring_mode']}\n\n";

if ($test['scoring_mode'] !== 'dimensions') {
    echo "不是 dimensions 模式，无需检查\n";
    exit(0);
}

$config = json_decode($test['scoring_config'], true);
if (!$config) {
    echo "配置解析失败\n";
    exit(1);
}

$dimensions = $config['dimensions'] ?? [];
$weights = $config['weights'] ?? [];
$mapping = $config['mapping'] ?? null;

echo "维度: " . implode(', ', $dimensions) . "\n\n";

$configSource = !empty($weights) ? $weights : $mapping;
if (empty($configSource)) {
    echo "配置为空\n";
    exit(1);
}

// 检查是否使用顺序键
$firstKey = array_key_first($configSource);
$useOrderKeys = false;
$useNumericKeys = false;
if (is_string($firstKey)) {
    if (preg_match('/^q\d+$/i', $firstKey)) {
        $useOrderKeys = true;
    } elseif (preg_match('/^\d+$/', $firstKey)) {
        $useOrderKeys = true;
        $useNumericKeys = true;
    }
}

echo "使用顺序键: " . ($useOrderKeys ? "是" : "否") . "\n";
if ($useOrderKeys) {
    echo "键格式: " . ($useNumericKeys ? "纯数字" : "q1/q2") . "\n";
}
echo "\n";

// 获取题目
$orderField = null;
$orderFields = ['sort_order', 'order_number', 'display_order'];
foreach ($orderFields as $field) {
    try {
        $checkStmt = $pdo->query("SHOW COLUMNS FROM questions LIKE '{$field}'");
        if ($checkStmt->rowCount() > 0) {
            $orderField = $field;
            break;
        }
    } catch (Exception $e) {
    }
}

$allowedFields = ['sort_order', 'order_number', 'display_order'];
if ($orderField && in_array($orderField, $allowedFields, true)) {
    $orderSql = "ORDER BY `{$orderField}` ASC, id ASC";
} else {
    $orderSql = "ORDER BY id ASC";
}

$qStmt = $pdo->prepare("SELECT id FROM questions WHERE test_id = ? {$orderSql}");
$qStmt->execute([$testId]);
$questionIds = $qStmt->fetchAll(PDO::FETCH_COLUMN);

echo "题目数量: " . count($questionIds) . "\n";
echo "配置键数量: " . count($configSource) . "\n\n";

// 分析每个题目的选项权重
$allOptionWeights = [];
$dimensionTotals = [];
foreach ($dimensions as $dim) {
    $dimensionTotals[$dim] = 0;
}

$questionIndex = 0;
foreach ($questionIds as $qId) {
    $questionIndex++;
    $qKey = null;
    
    if ($useOrderKeys) {
        $qKey = $useNumericKeys ? (string)$questionIndex : ('q' . $questionIndex);
    } else {
        $qKey = (string)$qId;
    }
    
    if (!isset($configSource[$qKey])) {
        echo "题目 #{$qId} (索引 {$questionIndex}, 键 {$qKey}): 缺少配置\n";
        continue;
    }
    
    $qConfig = $configSource[$qKey];
    if (!is_array($qConfig)) {
        echo "题目 #{$qId}: 配置不是数组\n";
        continue;
    }
    
    // 获取该题目的所有选项
    $optStmt = $pdo->prepare("SELECT id, option_key FROM question_options WHERE question_id = ? ORDER BY id ASC");
    $optStmt->execute([$qId]);
    $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "题目 #{$qId} (键 {$qKey}):\n";
    foreach ($options as $opt) {
        $optKey = isset($opt['option_key']) ? (string)$opt['option_key'] : '';
        if ($optKey === '') {
            continue;
        }
        
        if (!isset($qConfig[$optKey])) {
            echo "  选项 {$optKey}: 缺少配置\n";
        } else {
            $optWeights = $qConfig[$optKey];
            if (!is_array($optWeights)) {
                echo "  选项 {$optKey}: 权重不是数组\n";
            } else {
                echo "  选项 {$optKey}: " . json_encode($optWeights, JSON_UNESCAPED_UNICODE) . "\n";
                $allOptionWeights[] = $optWeights;
                
                // 累加维度总权重
                foreach ($optWeights as $dim => $weight) {
                    if (isset($dimensionTotals[$dim])) {
                        $dimensionTotals[$dim] += (float)$weight;
                    }
                }
            }
        }
    }
    echo "\n";
}

echo "=== 维度总权重 ===\n";
foreach ($dimensionTotals as $dim => $total) {
    echo "{$dim}: {$total}\n";
}
echo "\n";

// 检查所有选项的权重是否完全相同
if (count($allOptionWeights) > 1) {
    $firstWeights = $allOptionWeights[0];
    $allSame = true;
    foreach ($allOptionWeights as $weights) {
        if (count($firstWeights) !== count($weights)) {
            $allSame = false;
            break;
        }
        foreach ($firstWeights as $dim => $weight) {
            if (!isset($weights[$dim]) || abs((float)$weights[$dim] - (float)$weight) > 0.001) {
                $allSame = false;
                break 2;
            }
        }
    }
    
    if ($allSame) {
        echo "❌ 严重问题: 所有选项的权重配置完全相同！\n";
        echo "   这会导致无论怎么选都是同一结果\n";
    } else {
        // 检查维度总权重是否相同
        $totalValues = array_values($dimensionTotals);
        if (count($totalValues) > 1) {
            $firstTotal = $totalValues[0];
            $allTotalsSame = true;
            foreach ($totalValues as $total) {
                if (abs($total - $firstTotal) > 0.001) {
                    $allTotalsSame = false;
                    break;
                }
            }
            
            if ($allTotalsSame) {
                echo "⚠️  警告: 所有维度的总权重相同\n";
                echo "   这可能导致在某些选择组合下，所有维度得分相同\n";
                echo "   当所有维度得分相同时，会返回第一个维度（" . $dimensions[0] . "）的结果\n";
            }
        }
    }
}

