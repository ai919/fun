<?php
require __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/seo_helper.php';
require_once __DIR__ . '/lib/html_purifier.php';
require_once __DIR__ . '/lib/CacheHelper.php';

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
    // 尝试从缓存获取 slug 到 id 的映射
    $slugCacheKey = 'test_slug_id_' . md5($slug);
    $cachedTestId = CacheHelper::get($slugCacheKey, 300);
    
    if ($cachedTestId !== null) {
        $testId = (int)$cachedTestId;
    } else {
        $stmt = $pdo->prepare("SELECT id FROM tests WHERE slug = ? LIMIT 1");
        $stmt->execute([$slug]);
        $testId = (int)$stmt->fetchColumn();
        if ($testId) {
            CacheHelper::set($slugCacheKey, $testId);
        }
    }
}

if (!$testId) {
    http_response_code(404);
    echo '缺少 slug 或 id 参数。';
    exit;
}

// 尝试从缓存获取测验完整数据（缓存5分钟）
$testCacheKey = 'test_full_' . $testId;
$cachedData = CacheHelper::get($testCacheKey, 300);

if ($cachedData !== null && is_array($cachedData)) {
    $test = $cachedData['test'];
    $questions = $cachedData['questions'];
    $optionsByQuestion = $cachedData['options'];
} else {
    // 缓存未命中，从数据库查询
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
    
    // 存入缓存
    CacheHelper::set($testCacheKey, [
        'test' => $test,
        'questions' => $questions,
        'options' => $optionsByQuestion,
    ]);
}

$questionCount = count($questions);
require_once __DIR__ . '/lib/Constants.php';
$isStepByStep = (!empty($test['display_mode']) && $test['display_mode'] === Constants::DISPLAY_MODE_STEP_BY_STEP);

// play_count 变化频繁，使用较短的缓存时间（1分钟）
$playCountCacheKey = 'test_play_count_' . $testId;
$playCount = CacheHelper::get($playCountCacheKey, 60);

if ($playCount === null) {
    $playCountStmt = $pdo->prepare("SELECT COUNT(*) FROM test_runs WHERE test_id = ?");
    $playCountStmt->execute([$testId]);
    $playCount = (int)$playCountStmt->fetchColumn();
    CacheHelper::set($playCountCacheKey, $playCount);
}

// 构建 SEO 数据（包含结构化数据）
$seo = build_seo_meta('test', [
    'test' => $test,
    'questions' => $questions,
]);

?>
<!doctype html>
<html lang="zh-CN">
<head>
<?php render_seo_head($seo); ?>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
</head>
<body>

<?php if (!empty($test['display_mode']) && $test['display_mode'] === Constants::DISPLAY_MODE_STEP_BY_STEP): ?>
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
                        <?= HTMLPurifier::purifyWithBreaks($heroDescription, true) ?>
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

    <div class="progress-indicator" id="global-progress" role="progressbar" aria-label="页面加载进度" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
        <div class="progress-indicator-bar" id="global-progress-bar"></div>
    </div>
    
    <div class="test-progress sticky-progress">
        <div class="test-progress-label">
            <span id="answered-count">0</span> / <?= $questionCount ?> 题已作答
        </div>
        <div class="test-progress-bar" role="progressbar" aria-label="答题进度" aria-valuemin="0" aria-valuemax="<?= $questionCount ?>" aria-valuenow="0">
            <div class="test-progress-fill" id="progress-fill"></div>
        </div>
    </div>

    <?php if (!$questions): ?>
        <p>该测验还没有题目，请稍后再试。</p>
    <?php else: ?>
        <form method="post" action="/submit.php" class="quiz-form test-body" id="quiz-form">
            <?php require_once __DIR__ . '/lib/csrf.php'; echo CSRF::getTokenField(); ?>
            <input type="hidden" name="test_id" value="<?= (int)$testId ?>">

            <div class="quiz-questions-wrapper <?= $isStepByStep ? 'step-mode' : 'single-page-mode' ?>"
                 data-total="<?= count($questions) ?>">
                <?php foreach ($questions as $idx => $question): ?>
                    <?php
                    $qid        = (int)$question['id'];
                    $questionNo = $question['sort_order'] ?? ($idx + 1);
                    $text       = $question['question_text'] ?? '未命名问题';
                    $options    = $optionsByQuestion[$qid] ?? [];
                    $stepIndex  = $idx + 1;
                    ?>
                    <div class="quiz-question-block question-block<?= $isStepByStep ? ' question-step' : '' ?>"
                         data-step="<?= $stepIndex ?>"
                         role="group"
                         aria-labelledby="question-<?= $qid ?>"
                         aria-label="问题 <?= $stepIndex ?>">
                        <div class="quiz-question-heading question-header">
                            <span class="quiz-q-number question-index" id="question-<?= $qid ?>">Q<?= htmlspecialchars($questionNo) ?></span>
                            <span class="quiz-q-text question-text"><?= htmlspecialchars($text) ?></span>
                        </div>

                        <div class="quiz-options question-options">
                            <?php if (!$options): ?>
                                <p style="color:#ef4444;">该题暂无可选项。</p>
                            <?php else: ?>
                                <?php foreach ($options as $optionIndex => $option): ?>
                                    <?php
                                    $optionId    = (int)$option['id'];
                                    $label       = pick_field($option, ['option_label', 'label', 'letter'], null);
                                    $optionText  = pick_field($option, ['text', 'option_text', 'body', 'description'], '选项');
                                    if ($label === null) {
                                        $label = chr(ord('A') + $optionIndex);
                                    }
                                    ?>
                                    <label class="quiz-option-item option-card" role="radio" aria-checked="false" tabindex="0">
                                        <input
                                            type="radio"
                                            name="q[<?= $qid ?>]"
                                            value="<?= $optionId ?>"
                                            required
                                            aria-label="<?= htmlspecialchars($text) ?> - 选项 <?= htmlspecialchars($label) ?>"
                                            aria-required="true"
                                        >
                                        <div class="option-inner">
                                            <span class="quiz-option-key option-key"><?= htmlspecialchars($label) ?>.</span>
                                            <span class="quiz-option-text option-text"><?= nl2br(htmlspecialchars($optionText)) ?></span>
                                        </div>
                                    </label>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($isStepByStep): ?>
                <div class="quiz-step-footer">
                    <div class="quiz-progress">
                        <span id="quiz-progress-text">0 / <?= count($questions) ?> 题已作答</span>
                    </div>
                    <div class="quiz-step-buttons">
                        <button type="button" class="btn-secondary" id="btn-prev-question">上一题</button>
                        <button type="button" class="btn-primary" id="btn-next-question">下一题</button>
                        <button type="submit" class="btn-primary" id="btn-submit-quiz" aria-label="提交测验结果">提交结果</button>
                    </div>
                </div>
            <?php else: ?>
                <div class="test-actions">
                    <a href="/index.php" class="btn-secondary">返回测验列表</a>
                    <button type="submit" class="btn-primary" aria-label="提交测验">提交测验</button>
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
    const globalProgress = document.getElementById('global-progress');
    const globalProgressBar = document.getElementById('global-progress-bar');

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
            progressFill.parentElement.setAttribute('aria-valuenow', answeredCount);
        }
    }

    // 全局进度指示器（用于表单提交等长操作）
    function showGlobalProgress(percent) {
        if (globalProgress && globalProgressBar) {
            if (percent > 0 && percent < 100) {
                globalProgress.classList.add('active');
                globalProgressBar.style.width = percent + '%';
                globalProgress.setAttribute('aria-valuenow', Math.round(percent));
            } else {
                globalProgress.classList.remove('active');
                globalProgressBar.style.width = '0%';
            }
        }
    }

    // 表单提交时显示进度
    const quizForm = document.getElementById('quiz-form');
    if (quizForm) {
        quizForm.addEventListener('submit', function() {
            showGlobalProgress(30);
            // 模拟进度更新
            let progress = 30;
            const progressInterval = setInterval(function() {
                progress += 10;
                if (progress < 90) {
                    showGlobalProgress(progress);
                } else {
                    clearInterval(progressInterval);
                    showGlobalProgress(90);
                }
            }, 200);
        });
    }

    radios.forEach(function (radio) {
        radio.addEventListener('change', updateProgress);
    });
    updateProgress();
});
</script>
<?php if ($isStepByStep): ?>
<script src="/assets/js/quiz_step_mode.js"></script>
<?php endif; ?>
</body>
</html>
