<?php
require __DIR__ . '/lib/db_connect.php';
require __DIR__ . '/lib/ScoreEngine.php';

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

$resultCode = ScoreEngine::score($test, $answers, $pdo);
if (!$resultCode) {
    $target = $test['slug'] ? '/test.php?slug=' . urlencode($test['slug']) : '/test.php?id=' . $testId;
    header('Location: ' . $target);
    exit;
}

$detail      = ScoreEngine::getLastDetail();
$totalScore  = isset($detail['total_score']) ? (float)$detail['total_score'] : 0.0;
$dimScores   = $detail['dimension_scores'] ?? [];

$resStmt = $pdo->prepare("SELECT * FROM results WHERE test_id = ? AND code = ? LIMIT 1");
$resStmt->execute([$testId, $resultCode]);
$resultRow = $resStmt->fetch(PDO::FETCH_ASSOC);
$resultId  = $resultRow ? (int)$resultRow['id'] : null;

$ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
$runStmt = $pdo->prepare(
    "INSERT INTO test_runs (test_id, result_id, user_identifier, ip_address, user_agent, total_score)
     VALUES (:test_id, :result_id, NULL, :ip, :ua, :score)"
);
$runStmt->execute([
    ':test_id'  => $testId,
    ':result_id'=> $resultId,
    ':ip'       => $ipAddress,
    ':ua'       => $userAgent,
    ':score'    => $totalScore,
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

header('Location: /result.php?test_id=' . $testId . '&code=' . urlencode($resultCode));
exit;
