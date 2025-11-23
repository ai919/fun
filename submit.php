<?php
require_once __DIR__ . '/lib/ErrorHandler.php';
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/ScoreEngine.php';
require_once __DIR__ . '/lib/user_auth.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/Constants.php';
require_once __DIR__ . '/lib/CacheHelper.php';

// 验证 CSRF token
if (!CSRF::validateToken()) {
    ErrorHandler::renderError(403, 'CSRF token 验证失败，请刷新页面后重试');
}

$testId = isset($_POST['test_id']) ? (int)$_POST['test_id'] : 0;
if ($testId <= 0) {
    ErrorHandler::renderError(400, '缺少 test_id');
}

// 只允许已发布测验
$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? AND (status = ? OR status = 1) LIMIT 1");
$testStmt->execute([$testId, Constants::TEST_STATUS_PUBLISHED]);
$test = $testStmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    ErrorHandler::renderError(404, '测验不存在或已下线。');
}

// 接收答案：q[question_id] = option_id
$answers = $_POST['q'] ?? [];
if (!$answers || !is_array($answers)) {
    $target = !empty($test['slug']) ? '/test.php?slug=' . urlencode($test['slug']) : '/test.php?id=' . $testId;
    header('Location: ' . $target);
    exit;
}

// 验证所有答案的 question_id 都属于当前测验，并且 option_id 属于对应的 question_id
$questionIds = array_keys($answers);
if (!empty($questionIds)) {
    // 过滤并转换为整数，确保都是有效的 question_id
    $questionIds = array_filter(array_map('intval', $questionIds), function($id) {
        return $id > 0;
    });
    
    if (!empty($questionIds)) {
        // 构建占位符并查询数据库，验证 question_id 属于当前测验
        $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM questions 
             WHERE id IN ($placeholders) AND test_id = ?"
        );
        $stmt->execute(array_merge($questionIds, [$testId]));
        $validCount = (int)$stmt->fetchColumn();
        
        // 如果有效题目数量不等于提交的题目数量，说明存在无效答案
        if ($validCount !== count($questionIds)) {
            http_response_code(400);
            die('提交的答案包含无效题目，请刷新页面后重试');
        }
        
        // 验证每个 option_id 是否属于对应的 question_id
        $optionIds = array_values(array_filter(array_map('intval', $answers), function($id) {
            return $id > 0;
        }));
        
        if (!empty($optionIds)) {
            // 查询所有选项，验证它们是否属于对应的题目
            $optPlaceholders = implode(',', array_fill(0, count($optionIds), '?'));
            $optStmt = $pdo->prepare(
                "SELECT question_id, id FROM question_options 
                 WHERE id IN ($optPlaceholders)"
            );
            $optStmt->execute($optionIds);
            $validOptions = $optStmt->fetchAll(PDO::FETCH_ASSOC);
            
            // 构建有效的 option_id => question_id 映射
            $optionToQuestion = [];
            foreach ($validOptions as $opt) {
                $optionToQuestion[(int)$opt['id']] = (int)$opt['question_id'];
            }
            
            // 验证每个提交的答案：option_id 必须属于对应的 question_id
            foreach ($answers as $qId => $optId) {
                $qId = (int)$qId;
                $optId = (int)$optId;
                
                // 如果 option_id 不在有效选项中，或者不属于对应的 question_id
                if (!isset($optionToQuestion[$optId]) || $optionToQuestion[$optId] !== $qId) {
                    http_response_code(400);
                    die('提交的答案包含无效选项，请刷新页面后重试');
                }
            }
        }
    }
}

// 交给通用评分引擎
$detail = ScoreEngine::score($test, $answers, $pdo);
if (!$detail || empty($detail['result'])) {
    // 算不出结果时退回测验页，避免白屏
    $target = !empty($test['slug']) ? '/test.php?slug=' . urlencode($test['slug']) : '/test.php?id=' . $testId;
    header('Location: ' . $target);
    exit;
}

$totalScore  = isset($detail['total_score']) ? (float)$detail['total_score'] : 0.0;
$dimScores   = $detail['dimension_scores'] ?? [];
$resultRow   = $detail['result'] ?? null;
$resultId    = $resultRow ? (int)$resultRow['id'] : null;

$currentUser = UserAuth::currentUser();
$userId      = $currentUser ? (int)$currentUser['id'] : null;

// 生成分享 token（16位十六进制），确保唯一性
$shareToken = null;
$maxRetries = Constants::TOKEN_GENERATION_MAX_RETRIES;
$retryCount = 0;

while ($retryCount < $maxRetries) {
    try {
        if (function_exists('random_bytes')) {
            $shareToken = bin2hex(random_bytes(Constants::SHARE_TOKEN_BYTES)); // 32 字符（16字节的十六进制）
        } else {
            $shareToken = md5(uniqid(mt_rand(), true)); // 32 字符（MD5 哈希）
        }
    } catch (Exception $e) {
        $shareToken = md5(uniqid(mt_rand(), true)); // 32 字符（MD5 哈希）
    }
    
    // 检查数据库中是否已存在该 token
    $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM test_runs WHERE share_token = ? LIMIT 1");
    $checkStmt->execute([$shareToken]);
    $exists = (int)$checkStmt->fetchColumn() > 0;
    
    if (!$exists) {
        // token 唯一，跳出循环
        break;
    }
    
    // token 已存在，重试
    $retryCount++;
    $shareToken = null;
}

// 如果重试多次后仍然冲突，抛出异常
if ($shareToken === null) {
    http_response_code(500);
    die('无法生成唯一的分享 token，请稍后重试');
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;

// 使用事务确保数据一致性
$pdo->beginTransaction();
try {
    // 写入 test_runs
    $runStmt = $pdo->prepare(
        "INSERT INTO test_runs (user_id, test_id, result_id, user_identifier, ip_address, user_agent, total_score, share_token)
         VALUES (:user_id, :test_id, :result_id, NULL, :ip, :ua, :score, :share_token)"
    );
    $runStmt->execute([
        ':user_id'     => $userId,
        ':test_id'     => $testId,
        ':result_id'   => $resultId,
        ':ip'          => $ipAddress,
        ':ua'          => $userAgent,
        ':score'       => $totalScore,
        ':share_token' => $shareToken,
    ]);
    $testRunId = (int)$pdo->lastInsertId();

    // 保存问题答案到 question_answers 表
    if ($testRunId > 0 && !empty($answers)) {
        // 获取所有选项的 option_key
        $optionIds = array_values(array_filter(array_map('intval', $answers), function($id) {
            return $id > 0;
        }));
        
        if (!empty($optionIds)) {
            $optPlaceholders = implode(',', array_fill(0, count($optionIds), '?'));
            $optStmt = $pdo->prepare(
                "SELECT id, question_id, option_key FROM question_options WHERE id IN ($optPlaceholders)"
            );
            $optStmt->execute($optionIds);
            $optionMap = [];
            while ($opt = $optStmt->fetch(PDO::FETCH_ASSOC)) {
                $optionMap[(int)$opt['id']] = [
                    'question_id' => (int)$opt['question_id'],
                    'option_key' => $opt['option_key']
                ];
            }
            
            // 插入 question_answers
            $insAnswer = $pdo->prepare(
                "INSERT INTO question_answers (test_run_id, test_id, question_id, option_key)
                 VALUES (:run_id, :test_id, :question_id, :option_key)"
            );
            
            foreach ($answers as $qId => $optId) {
                $qId = (int)$qId;
                $optId = (int)$optId;
                
                if (isset($optionMap[$optId]) && $optionMap[$optId]['question_id'] === $qId) {
                    $insAnswer->execute([
                        ':run_id' => $testRunId,
                        ':test_id' => $testId,
                        ':question_id' => $qId,
                        ':option_key' => $optionMap[$optId]['option_key']
                    ]);
                }
            }
        }
    }

    // 对于 dimensions 模式，额外记录各维度得分
    if (strtolower($test['scoring_mode'] ?? Constants::SCORING_MODE_SIMPLE) === Constants::SCORING_MODE_DIMENSIONS && $testRunId > 0 && !empty($dimScores)) {
        $insDim = $pdo->prepare(
            "INSERT INTO test_run_scores (test_run_id, dimension_key, score_value)
             VALUES (:run_id, :dim, :score)"
        );
        foreach ($dimScores as $dim => $score) {
            $insDim->execute([
                ':run_id' => $testRunId,
                ':dim'    => $dim,
                ':score'  => $score,
            ]);
        }
    }
    
    // 所有操作成功，提交事务
    $pdo->commit();
    
    // 清除 play_count 缓存（因为新增了一条 test_runs 记录）
    CacheHelper::delete('test_play_count_' . $testId);
    // 同时清除测验列表缓存（因为 play_count 会变化）
    CacheHelper::delete('published_tests_list');
} catch (Exception $e) {
    // 发生错误，回滚事务
    $pdo->rollBack();
    
    // 使用统一的错误处理
    ErrorHandler::handleException(
        $e,
        sprintf('提交测验答案失败: testId=%d, userId=%s', $testId, $userId ?? 'null')
    );
}

// 跳转到结果页
header('Location: /result.php?token=' . urlencode($shareToken));
exit;
