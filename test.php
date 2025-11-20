<?php
require __DIR__ . '/lib/db_connect.php';

function pick_field(array $row, array $candidates, $default = '')
{
    foreach ($candidates as $key) {
        if (isset($row[$key]) && $row[$key] !== null && $row[$key] !== '') {
            return $row[$key];
        }
    }
    return $default;
}

function db_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $dbName = db_current_schema($pdo);
    $key = $dbName . '.' . $table . '.' . $column;
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare("SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? LIMIT 1");
    $stmt->execute([$dbName, $table, $column]);
    $cache[$key] = (bool)$stmt->fetchColumn();
    return $cache[$key];
}

function db_current_schema(PDO $pdo): string
{
    static $name = null;
    if ($name === null) {
        $name = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    }
    return $name;
}

function choose_order_field(PDO $pdo, string $table): ?string
{
    foreach (['sort_order', 'order_number', 'display_order'] as $candidate) {
        if (db_column_exists($pdo, $table, $candidate)) {
            return $candidate;
        }
    }
    return null;
}

$testId = null;
$slug   = trim($_GET['slug'] ?? '');

if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
    $testId = (int)$_GET['id'];
} elseif ($slug !== '') {
    $stmt = $pdo->prepare("SELECT id FROM tests WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $testId = (int)$stmt->fetchColumn();
}

if (!$testId) {
    http_response_code(404);
    echo '缺少 slug 或 id 参数。';
    exit;
}

$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
$testStmt->execute([$testId]);
$test = $testStmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    http_response_code(404);
    echo '测验不存在。';
    exit;
}

$questionOrderField = choose_order_field($pdo, 'questions');
$questionOrderSql = $questionOrderField ? "ORDER BY {$questionOrderField} ASC, id ASC" : "ORDER BY id ASC";
$questionsStmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ? {$questionOrderSql}");
$questionsStmt->execute([$testId]);
$questions = $questionsStmt->fetchAll(PDO::FETCH_ASSOC);

$questionIds = array_column($questions, 'id');
$optionsByQuestion = [];
if ($questionIds) {
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $optionOrderField = choose_order_field($pdo, 'question_options');
    $optionOrderSql = $optionOrderField ? "ORDER BY {$optionOrderField} ASC, id ASC" : "ORDER BY id ASC";
    $optStmt = $pdo->prepare(
        "SELECT * FROM question_options
         WHERE question_id IN ($placeholders)
         {$optionOrderSql}"
    );
    $optStmt->execute($questionIds);
    while ($opt = $optStmt->fetch(PDO::FETCH_ASSOC)) {
        $qid = (int)$opt['question_id'];
        $optionsByQuestion[$qid][] = $opt;
    }
}

?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($test['title'] ?? '测验') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<div class="page-container">
    <h1><?= htmlspecialchars($test['title']) ?></h1>
    <?php if (!empty($test['subtitle'])): ?>
        <p class="subtitle"><?= htmlspecialchars($test['subtitle']) ?></p>
    <?php endif; ?>
    <?php if (!empty($test['description'])): ?>
        <p><?= nl2br(htmlspecialchars($test['description'])) ?></p>
    <?php endif; ?>

    <?php if (!$questions): ?>
        <p>该测验还没有题目，请稍后再试。</p>
    <?php else: ?>
        <form method="post" action="/submit.php">
            <input type="hidden" name="test_id" value="<?= (int)$testId ?>">
            <?php foreach ($questions as $idx => $question): ?>
                <?php
                $qid        = (int)$question['id'];
                $questionNo = $question['sort_order'] ?? ($idx + 1);
                $text       = pick_field($question, ['content', 'question_text', 'title', 'body'], '未命名问题');
                $options    = $optionsByQuestion[$qid] ?? [];
                ?>
                <div class="question">
                    <div class="question-title">Q<?= htmlspecialchars($questionNo) ?>. <?= htmlspecialchars($text) ?></div>
                    <?php if (!$options): ?>
                        <p style="color:#ef4444;">该题暂无可选项。</p>
                    <?php else: ?>
                        <?php foreach ($options as $optionIndex => $option): ?>
                            <?php
                            $optionId    = (int)$option['id'];
                            $label       = pick_field($option, ['option_label', 'label', 'letter'], null);
                            $optionText  = pick_field($option, ['text', 'content', 'option_text', 'body', 'description'], '选项');
                            if ($label === null) {
                                $label = chr(ord('A') + $optionIndex);
                            }
                            ?>
                            <div class="option">
                                <label>
                                    <span class="badge"><?= htmlspecialchars($label) ?></span>
                                    <input type="radio" name="q[<?= $qid ?>]" value="<?= $optionId ?>" required>
                                    <span><?= htmlspecialchars($optionText) ?></span>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <button type="submit">提交测验</button>
        </form>
    <?php endif; ?>
</div>

</body>
</html>
