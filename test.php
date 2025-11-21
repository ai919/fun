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
$questionCount = count($questions);
$isStepByStep = (!empty($test['display_mode']) && $test['display_mode'] === 'step_by_step');

$playCountStmt = $pdo->prepare("SELECT COUNT(*) FROM test_runs WHERE test_id = ?");
$playCountStmt->execute([$testId]);
$playCount = (int)$playCountStmt->fetchColumn();

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

<?php if (!empty($test['display_mode']) && $test['display_mode'] === 'step_by_step'): ?>
<div class="quiz-exit-bar">
    <a class="exit-btn" href="/index.php">← 返回首页</a>
</div>
<?php endif; ?>

<?php
$heroSubtitle = '';
if (!empty($test['subtitle'])) {
    $heroSubtitle = $test['subtitle'];
} elseif (!empty($test['description'])) {
    $heroSubtitle = mb_substr($test['description'], 0, 120);
}
$heroDescription = '';
if (!empty($test['description'])) {
    $heroDescription = $test['description'];
}
$emoji = trim($test['emoji'] ?? ($test['title_emoji'] ?? ''));
$titleColor = '#111827';
$titleColorField = trim($test['title_color'] ?? '');
if ($titleColorField !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $titleColorField)) {
    $titleColor = $titleColorField;
}
?>

<div class="test-page">
    <header class="test-hero">
        <div class="test-hero-meta">
            <div class="test-hero-text">
                <h1 class="test-title" style="color: <?= htmlspecialchars($titleColor) ?>">
                    <?php if ($emoji !== ''): ?>
                        <span class="test-title-emoji"><?= htmlspecialchars($emoji) ?></span>
                    <?php endif; ?>
                    <?= htmlspecialchars($test['title']) ?>
                </h1>
                <?php if ($heroSubtitle !== ''): ?>
                    <p class="test-subtitle"><?= htmlspecialchars($heroSubtitle) ?></p>
                <?php endif; ?>
                <?php if ($heroDescription !== ''): ?>
                    <div class="test-description">
                        <?= nl2br(htmlspecialchars($heroDescription)) ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <div class="test-hero-extra">
            <span class="quiz-tag">人格/情感测验</span>
            <span class="test-meta-text">
                已有 <strong><?= $playCount ?></strong> 人测验 · 共 <strong><?= $questionCount ?></strong> 题
            </span>
        </div>
    </header>

    <div class="test-progress sticky-progress">
        <div class="test-progress-label">
            <span id="answered-count">0</span> / <?= $questionCount ?> 题已作答
        </div>
        <div class="test-progress-bar">
            <div class="test-progress-fill" id="progress-fill"></div>
        </div>
    </div>

    <?php if (!$questions): ?>
        <p>该测验还没有题目，请稍后再试。</p>
    <?php else: ?>
        <form method="post" action="/submit.php" class="test-body" id="quiz-form">
            <input type="hidden" name="test_id" value="<?= (int)$testId ?>">
            <?php
            $totalQuestions = count($questions);
            $stepIndex = 0;
            ?>
            <?php foreach ($questions as $idx => $question): ?>
                <?php
                $qid        = (int)$question['id'];
                $questionNo = $question['sort_order'] ?? ($idx + 1);
                $text       = pick_field($question, ['content', 'question_text', 'title', 'body'], '未命名问题');
                $options    = $optionsByQuestion[$qid] ?? [];
                $stepIndex++;
                $stepClasses = 'question-block';
                if ($isStepByStep) {
                    $stepClasses .= ' question-step';
                    if ($stepIndex === 1) {
                        $stepClasses .= ' active';
                    }
                }
                ?>
                <div class="<?= $stepClasses ?>" data-step="<?= $stepIndex ?>">
                    <div class="question-header">
                        <div class="question-index">Q<?= htmlspecialchars($questionNo) ?></div>
                        <div class="question-text"><?= htmlspecialchars($text) ?></div>
                    </div>
                    <?php if (!$options): ?>
                        <p style="color:#ef4444;">该题暂无可选项。</p>
                    <?php else: ?>
                        <div class="question-options">
                            <?php foreach ($options as $optionIndex => $option): ?>
                                <?php
                                $optionId    = (int)$option['id'];
                                $label       = pick_field($option, ['option_label', 'label', 'letter'], null);
                                $optionText  = pick_field($option, ['text', 'content', 'option_text', 'body', 'description'], '选项');
                                if ($label === null) {
                                    $label = chr(ord('A') + $optionIndex);
                                }
                                ?>
                                <label class="option-card">
                                    <input type="radio" name="q[<?= $qid ?>]" value="<?= $optionId ?>" required>
                                    <div class="option-inner">
                                        <span class="option-key"><?= htmlspecialchars($label) ?></span>
                                        <span class="option-text"><?= nl2br(htmlspecialchars($optionText)) ?></span>
                                    </div>
                                </label>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <?php if ($isStepByStep): ?>
                        <div class="question-nav">
                            <?php if ($stepIndex > 1): ?>
                                <button type="button" class="btn-secondary btn-prev" data-prev="<?= $stepIndex - 1 ?>">上一题</button>
                            <?php endif; ?>

                            <?php if ($stepIndex < $totalQuestions): ?>
                                <button type="button" class="btn-primary btn-next" data-next="<?= $stepIndex + 1 ?>">下一题</button>
                            <?php else: ?>
                                <button type="submit" class="btn-primary">提交结果</button>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>

            <?php if (!$isStepByStep): ?>
                <div class="test-actions">
                    <a href="/index.php" class="btn-secondary">返回测验列表</a>
                    <button type="submit" class="btn-primary">提交测验</button>
                </div>
            <?php endif; ?>
        </form>
    <?php endif; ?>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const totalQuestions = <?= (int)$questionCount ?>;
    const radios = document.querySelectorAll('input[type="radio"][name^="q["]');
    const answeredLabel = document.getElementById('answered-count');
    const progressFill = document.getElementById('progress-fill');

    function updateProgress() {
        const answeredQuestions = new Set();
        radios.forEach(function (radio) {
            if (radio.checked) {
                answeredQuestions.add(radio.name);
            }
        });
        const answeredCount = answeredQuestions.size;
        if (answeredLabel) {
            answeredLabel.textContent = answeredCount;
        }
        if (progressFill) {
            const percent = totalQuestions > 0 ? (answeredCount / totalQuestions) * 100 : 0;
            progressFill.style.width = percent + '%';
        }
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', updateProgress);
    });
    updateProgress();
});
</script>
<script src="/assets/js/main.js"></script>
</script>
</body>
</html>
