<?php
require __DIR__ . '/lib/db_connect.php';
require __DIR__ . '/lib/functions.php';

$testId = (int)($_POST['test_id'] ?? 0);
if (!$testId) {
    die('缺少 test_id');
}

$optionIds = [];
foreach ($_POST as $key => $val) {
    if (strpos($key, 'q_') === 0) {
        $optionIds[] = (int)$val;
    }
}

if (!$optionIds) {
    die('你还没有选择任何选项。');
}

$scores = calculateDimensionScores($pdo, $optionIds);

$dimensionResults = [];
foreach ($scores as $dim => $score) {
    $result = getResultForDimension($pdo, $testId, $dim, $score);
    if ($result) {
        $dimensionResults[$dim] = [
            'score'  => $score,
            'result' => $result,
        ];
    }
}

$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
$testStmt->execute([$testId]);
$test = $testStmt->fetch(PDO::FETCH_ASSOC);

$finalTest            = $test;
$finalScores          = $scores;
$finalDimensionResult = $dimensionResults;

require __DIR__ . '/result.php';
