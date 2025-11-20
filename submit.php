<?php
require __DIR__ . '/lib/db_connect.php';

$testId = (int)($_POST['test_id'] ?? 0);
if (!$testId) {
    die('缺少 test_id');
}

$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? AND (status = 'published' OR status = 1) LIMIT 1");
$testStmt->execute([$testId]);
$test = $testStmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    http_response_code(404);
    die('测试不存在或已下线。');
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

$optionIds = array_values(array_unique($optionIds));
$placeholders = implode(',', array_fill(0, count($optionIds), '?'));
$optStmt = $pdo->prepare(
    "SELECT qo.*, q.test_id
     FROM question_options qo
     JOIN questions q ON q.id = qo.question_id
     WHERE qo.id IN ($placeholders)"
);
$optStmt->execute($optionIds);
$found = $optStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$found || count($found) !== count($optionIds)) {
    http_response_code(400);
    die('提交的数据无效，请刷新页面后重试。');
}

$codeCounts = [];
foreach ($found as $row) {
    if ((int)$row['test_id'] !== $testId) {
        http_response_code(400);
        die('提交的数据无效，请刷新页面后重试。');
    }
    $code = strtoupper(trim($row['map_result_code'] ?? ''));
    if ($code === '') {
        continue;
    }
    if (!isset($codeCounts[$code])) {
        $codeCounts[$code] = 0;
    }
    $codeCounts[$code]++;
}

$finalResult = null;
if ($codeCounts) {
    ksort($codeCounts);
    $winningCode  = null;
    $winningCount = -1;
    foreach ($codeCounts as $code => $count) {
        if ($count > $winningCount) {
            $winningCode  = $code;
            $winningCount = $count;
        }
    }
    if ($winningCode !== null) {
        $resStmt = $pdo->prepare("SELECT * FROM results WHERE test_id = ? AND code = ? LIMIT 1");
        $resStmt->execute([$testId, $winningCode]);
        $finalResult = $resStmt->fetch(PDO::FETCH_ASSOC);
    }
}

require __DIR__ . '/result.php';
