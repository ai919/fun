<?php
/**
 * 诊断脚本：检查所有测验的 scoring_config 配置
 * 找出可能导致"无论怎么选都是同一结果"的问题
 */

require __DIR__ . '/lib/db_connect.php';

echo "=== 测验配置诊断 ===\n\n";

// 查询所有已发布的测验
$stmt = $pdo->query("
    SELECT id, slug, title, scoring_mode, scoring_config
    FROM tests
    WHERE status = 'published'
    ORDER BY id ASC
");

$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$issues = [];

foreach ($tests as $test) {
    $testId = $test['id'];
    $slug = $test['slug'];
    $title = $test['title'];
    $scoringMode = $test['scoring_mode'];
    $configJson = $test['scoring_config'];
    
    echo "测验 #{$testId}: {$title} ({$slug})\n";
    echo "  计分模式: {$scoringMode}\n";
    
    if ($scoringMode === 'dimensions') {
        if (empty($configJson)) {
            echo "  ❌ 问题: scoring_config 为空\n";
            $issues[] = [
                'test_id' => $testId,
                'title' => $title,
                'issue' => 'scoring_config 为空',
            ];
            continue;
        }
        
        $config = json_decode($configJson, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            echo "  ❌ 问题: scoring_config JSON 解析失败: " . json_last_error_msg() . "\n";
            $issues[] = [
                'test_id' => $testId,
                'title' => $title,
                'issue' => 'JSON 解析失败: ' . json_last_error_msg(),
            ];
            continue;
        }
        
        // 检查 dimensions
        $dimensions = $config['dimensions'] ?? [];
        if (empty($dimensions) || !is_array($dimensions)) {
            echo "  ❌ 问题: dimensions 为空或不是数组\n";
            $issues[] = [
                'test_id' => $testId,
                'title' => $title,
                'issue' => 'dimensions 为空或不是数组',
            ];
            continue;
        }
        
        echo "  维度数量: " . count($dimensions) . "\n";
        echo "  维度列表: " . implode(', ', $dimensions) . "\n";
        
        // 检查 weights 或 mapping
        $weights = $config['weights'] ?? [];
        $mapping = $config['mapping'] ?? null;
        
        if (empty($weights) && empty($mapping)) {
            echo "  ❌ 问题: weights 和 mapping 都为空\n";
            $issues[] = [
                'test_id' => $testId,
                'title' => $title,
                'issue' => 'weights 和 mapping 都为空',
            ];
            continue;
        }
        
        // 检查题目数量
        $questionStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM questions WHERE test_id = ?");
        $questionStmt->execute([$testId]);
        $questionCount = $questionStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
        echo "  题目数量: {$questionCount}\n";
        
        // 检查配置中的键
        $configSource = !empty($weights) ? $weights : $mapping;
        $configKeys = array_keys($configSource);
        echo "  配置键数量: " . count($configKeys) . "\n";
        echo "  配置键示例: " . implode(', ', array_slice($configKeys, 0, 5)) . "\n";
        
        // 检查是否有题目没有配置
        if ($questionCount > 0) {
            // 获取所有题目ID
            $qStmt = $pdo->prepare("SELECT id FROM questions WHERE test_id = ? ORDER BY id ASC");
            $qStmt->execute([$testId]);
            $questionIds = $qStmt->fetchAll(PDO::FETCH_COLUMN);
            
            // 检查是否使用题目顺序键（q1, q2 或 "1", "2"）
            $useOrderKeys = false;
            $firstKey = $configKeys[0] ?? null;
            if ($firstKey && is_string($firstKey)) {
                if (preg_match('/^q\d+$/i', $firstKey) || preg_match('/^\d+$/', $firstKey)) {
                    $useOrderKeys = true;
                    echo "  使用题目顺序键: 是 ({$firstKey} 格式)\n";
                } else {
                    echo "  使用题目顺序键: 否 (使用 question_id)\n";
                }
            }
            
            // 检查每个题目的选项配置
            $missingQuestions = [];
            $missingOptions = [];
            $allQuestionWeights = []; // 收集所有题目的权重配置用于分析
            
            // 如果使用顺序键，需要构建映射关系
            if ($useOrderKeys) {
                // 检查配置键的数量是否匹配题目数量
                $configKeyCount = count($configKeys);
                if ($configKeyCount != $questionCount) {
                    echo "  ⚠️  警告: 配置键数量 ({$configKeyCount}) 与题目数量 ({$questionCount}) 不匹配\n";
                    $issues[] = [
                        'test_id' => $testId,
                        'title' => $title,
                        'issue' => "配置键数量 ({$configKeyCount}) 与题目数量 ({$questionCount}) 不匹配",
                    ];
                }
                
                // 获取题目顺序字段
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
                        // 忽略错误，继续检查下一个字段
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
                $orderedQuestionIds = $qStmt->fetchAll(PDO::FETCH_COLUMN);
                
                // 构建 question_id => qKey 映射
                $questionIdToQKey = [];
                $keyIndex = 0;
                foreach ($orderedQuestionIds as $qId) {
                    if ($keyIndex < count($configKeys)) {
                        $qKey = $configKeys[$keyIndex];
                        $questionIdToQKey[$qId] = $qKey;
                        $keyIndex++;
                    }
                }
                
                // 检查每个题目
                foreach ($orderedQuestionIds as $index => $qId) {
                    $qKey = $questionIdToQKey[$qId] ?? null;
                    if (!$qKey || !isset($configSource[$qKey])) {
                        $missingQuestions[] = $qId;
                        continue;
                    }
                    
                    $qConfig = $configSource[$qKey];
                    if (!is_array($qConfig)) {
                        $missingOptions[] = "题目 {$qId} (q" . ($index + 1) . "): 配置不是数组";
                        continue;
                    }
                    
                    // 获取该题目的所有选项
                    $optStmt = $pdo->prepare("SELECT id, option_key FROM question_options WHERE question_id = ?");
                    $optStmt->execute([$qId]);
                    $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);
                    
                    $questionWeights = [];
                    foreach ($options as $opt) {
                        $optKey = isset($opt['option_key']) ? (string)$opt['option_key'] : '';
                        if ($optKey === '') {
                            continue;
                        }
                        
                        if (!isset($qConfig[$optKey])) {
                            $missingOptions[] = "题目 {$qId} (q" . ($index + 1) . ") 选项 {$optKey}: 缺少配置";
                        } else {
                            $optWeights = $qConfig[$optKey];
                            if (!is_array($optWeights)) {
                                $missingOptions[] = "题目 {$qId} (q" . ($index + 1) . ") 选项 {$optKey}: 权重不是数组";
                            } else {
                                // 检查权重是否都是 0 或为空
                                $allZero = true;
                                foreach ($optWeights as $dim => $weight) {
                                    if ((float)$weight != 0) {
                                        $allZero = false;
                                        break;
                                    }
                                }
                                if ($allZero) {
                                    $missingOptions[] = "题目 {$qId} (q" . ($index + 1) . ") 选项 {$optKey}: 所有权重都是 0";
                                }
                                $questionWeights[$optKey] = $optWeights;
                            }
                        }
                    }
                    $allQuestionWeights[] = $questionWeights;
                }
            } else {
                // 检查直接使用 question_id 的情况
                foreach ($questionIds as $qId) {
                    $qKey = (string)$qId;
                    
                    if (isset($configSource[$qKey])) {
                        // 检查该题目的选项配置
                        $qConfig = $configSource[$qKey];
                        if (!is_array($qConfig)) {
                            $missingOptions[] = "题目 {$qId}: 配置不是数组";
                            continue;
                        }
                        
                        // 获取该题目的所有选项
                        $optStmt = $pdo->prepare("SELECT id, option_key FROM question_options WHERE question_id = ?");
                        $optStmt->execute([$qId]);
                        $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);
                        
                        $questionWeights = [];
                        foreach ($options as $opt) {
                            $optKey = isset($opt['option_key']) ? (string)$opt['option_key'] : '';
                            if ($optKey === '') {
                                continue;
                            }
                            
                            if (!isset($qConfig[$optKey])) {
                                $missingOptions[] = "题目 {$qId} 选项 {$optKey}: 缺少配置";
                            } else {
                                $optWeights = $qConfig[$optKey];
                                if (!is_array($optWeights)) {
                                    $missingOptions[] = "题目 {$qId} 选项 {$optKey}: 权重不是数组";
                                } else {
                                    // 检查权重是否都是 0 或为空
                                    $allZero = true;
                                    foreach ($optWeights as $dim => $weight) {
                                        if ((float)$weight != 0) {
                                            $allZero = false;
                                            break;
                                        }
                                    }
                                    if ($allZero) {
                                        $missingOptions[] = "题目 {$qId} 选项 {$optKey}: 所有权重都是 0";
                                    }
                                    $questionWeights[$optKey] = $optWeights;
                                }
                            }
                        }
                        $allQuestionWeights[] = $questionWeights;
                    } else {
                        // 如果不使用顺序键，但题目ID不在配置中
                        $missingQuestions[] = $qId;
                    }
                }
            }
            
            if (!empty($missingQuestions)) {
                echo "  ⚠️  警告: " . count($missingQuestions) . " 个题目缺少配置: " . implode(', ', array_slice($missingQuestions, 0, 5)) . "\n";
                $issues[] = [
                    'test_id' => $testId,
                    'title' => $title,
                    'issue' => count($missingQuestions) . ' 个题目缺少配置',
                ];
            }
            
            if (!empty($missingOptions)) {
                echo "  ⚠️  警告: " . count($missingOptions) . " 个选项配置有问题:\n";
                foreach (array_slice($missingOptions, 0, 10) as $msg) {
                    echo "      - {$msg}\n";
                }
                if (count($missingOptions) > 10) {
                    echo "      ... 还有 " . (count($missingOptions) - 10) . " 个问题\n";
                }
                $issues[] = [
                    'test_id' => $testId,
                    'title' => $title,
                    'issue' => count($missingOptions) . ' 个选项配置有问题',
                ];
            }
            
            // 检查可能导致"无论怎么选都是同一结果"的问题
            if (empty($missingOptions) && !empty($allQuestionWeights)) {
                // 1. 检查所有选项的权重是否完全相同
                $allWeightsSame = true;
                $firstWeights = null;
                $weightCount = 0;
                
                foreach ($allQuestionWeights as $questionWeights) {
                    foreach ($questionWeights as $optWeights) {
                        $weightCount++;
                        if ($firstWeights === null) {
                            $firstWeights = $optWeights;
                        } else {
                            // 比较两个权重数组是否完全相同
                            if (count($firstWeights) !== count($optWeights)) {
                                $allWeightsSame = false;
                                break 2;
                            }
                            foreach ($firstWeights as $dim => $weight) {
                                if (!isset($optWeights[$dim]) || (float)$optWeights[$dim] !== (float)$weight) {
                                    $allWeightsSame = false;
                                    break 3;
                                }
                            }
                        }
                    }
                }
                
                if ($allWeightsSame && $firstWeights !== null && $weightCount > 1) {
                    echo "  ❌ 严重问题: 所有选项的权重配置完全相同！这会导致无论怎么选都是同一结果\n";
                    $issues[] = [
                        'test_id' => $testId,
                        'title' => $title,
                        'issue' => '所有选项权重完全相同',
                    ];
                }
                
                // 2. 检查每个维度的总权重是否相同（这也会导致结果相同）
                if (!$allWeightsSame) {
                    $dimensionTotals = [];
                    foreach ($dimensions as $dim) {
                        $dimensionTotals[$dim] = 0;
                    }
                    
                    // 计算每个维度在所有选项中的总权重
                    foreach ($allQuestionWeights as $questionWeights) {
                        foreach ($questionWeights as $optWeights) {
                            foreach ($optWeights as $dim => $weight) {
                                if (isset($dimensionTotals[$dim])) {
                                    $dimensionTotals[$dim] += (float)$weight;
                                }
                            }
                        }
                    }
                    
                    // 检查所有维度的总权重是否相同
                    $totalValues = array_values($dimensionTotals);
                    if (count($totalValues) > 1) {
                        $firstTotal = $totalValues[0];
                        $allTotalsSame = true;
                        foreach ($totalValues as $total) {
                            if (abs($total - $firstTotal) > 0.001) { // 允许浮点数误差
                                $allTotalsSame = false;
                                break;
                            }
                        }
                        
                        if ($allTotalsSame) {
                            echo "  ⚠️  警告: 所有维度的总权重相同，可能导致结果偏向第一个维度\n";
                            echo "     维度总权重: " . json_encode($dimensionTotals, JSON_UNESCAPED_UNICODE) . "\n";
                        }
                    }
                }
            }
        }
    }
    
    echo "\n";
}

echo "\n=== 问题总结 ===\n";
if (empty($issues)) {
    echo "✅ 未发现明显问题\n";
} else {
    echo "发现 " . count($issues) . " 个问题:\n\n";
    foreach ($issues as $issue) {
        echo "测验 #{$issue['test_id']}: {$issue['title']}\n";
        echo "  问题: {$issue['issue']}\n\n";
    }
}

