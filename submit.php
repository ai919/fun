<?php
require __DIR__ . '/lib/db_connect.php';
require __DIR__ . '/lib/ScoreEngine.php';
require __DIR__ . '/lib/user_auth.php';

$testId = isset($_POST['test_id']) ? (int)$_POST['test_id'] : 0;
if ($testId <= 0) {
    http_response_code(400);
    die('缺少 test_id');
}

$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? AND (status = 'published' OR status = 1) LIMIT 1");
$testStmt->execute([$testId]);
$test = $testStmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    http_response_code(404);
    die('测验不存在或已下线。');
}

$answers = $_POST['q'] ?? [];
if (!$answers || !is_array($answers)) {
    $target = $test['slug'] ? '/test.php?slug=' . urlencode($test['slug']) : '/test.php?id=' . $testId;
    header('Location: ' . $target);
    exit;
}

$detail = ScoreEngine::score($test, $answers, $pdo);
if (!$detail || empty($detail['result'])) {
    $target = $test['slug'] ? '/test.php?slug=' . urlencode($test['slug']) : '/test.php?id=' . $testId;
    header('Location: ' . $target);
    exit;
}

$detail      = ScoreEngine::getLastDetail();
$totalScore  = isset($detail['total_score']) ? (float)$detail['total_score'] : 0.0;
$dimScores   = $detail['dimension_scores'] ?? [];
$answerMap   = $detail['answers'] ?? [];
$currentUser = UserAuth::currentUser();
$userId      = $currentUser ? (int)$currentUser['id'] : null;

$resultRow = $detail['result'] ?? null;
$resultId  = $resultRow ? (int)$resultRow['id'] : null;

// 生成分享用 token（16位十六进制字符串）
$shareToken = null;
try {
    if (function_exists('random_bytes')) {
        $shareToken = bin2hex(random_bytes(8));
    } else {
        $shareToken = substr(md5(uniqid(mt_rand(), true)), 0, 16);
    }
} catch (Exception $e) {
    $shareToken = substr(md5(uniqid(mt_rand(), true)), 0, 16);
}

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
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

if (strtolower($test['scoring_mode'] ?? 'simple') === 'dimensions' && $testRunId > 0 && !empty($dimScores)) {
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

if ($testRunId > 0 && !empty($answerMap)) {
    $insAns = $pdo->prepare("
        INSERT INTO question_answers (test_run_id, test_id, question_id, option_key)
        VALUES (:run_id, :test_id, :q_id, :opt)
    ");
    foreach ($answerMap as $qId => $optKey) {
        $insAns->execute([
            ':run_id'  => $testRunId,
            ':test_id' => $testId,
            ':q_id'    => (int)$qId,
            ':opt'     => $optKey,
        ]);
    }
}

header('Location: /result.php?token=' . urlencode($shareToken));
exit;
