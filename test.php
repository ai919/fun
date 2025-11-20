<?php
require __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/functions.php';
require_once __DIR__ . '/seo_helper.php';

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$slug = trim($path, '/');
if ($slug === '') {
    header('Location: /');
    exit;
}

$stmt = $pdo->prepare("SELECT * FROM tests WHERE slug = ? AND (status = 'published' OR status = 1) LIMIT 1");
$stmt->execute([$slug]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    http_response_code(404);
    echo "<h1>测试不存在</h1>";
    exit;
}

$testId = (int)$test['id'];
$seo    = df_seo_for_test($test);

$qStmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ? ORDER BY order_number ASC, id ASC");
$qStmt->execute([$testId]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
if (!$questions) {
    echo "<p>该测试暂未配置题目。</p>";
    exit;
}

$qIds = array_column($questions, 'id');
$optionsByQuestion = [];
$optionsById       = [];
if ($qIds) {
    $place = implode(',', array_fill(0, count($qIds), '?'));
    $oStmt = $pdo->prepare(
        "SELECT * FROM options
         WHERE question_id IN ($place)
         ORDER BY question_id ASC, id ASC"
    );
    $oStmt->execute($qIds);
    while ($row = $oStmt->fetch(PDO::FETCH_ASSOC)) {
        $qid = (int)$row['question_id'];
        $optionsByQuestion[$qid][] = $row;
        $optionsById[(int)$row['id']] = $row;
    }
}

$dimStmt = $pdo->prepare("SELECT key_name, title FROM dimensions WHERE test_id = ? ORDER BY id ASC");
$dimStmt->execute([$testId]);
$dimensionMeta = [];
while ($row = $dimStmt->fetch(PDO::FETCH_ASSOC)) {
    $dimensionMeta[$row['key_name']] = $row;
}

$errors          = [];
$scoresByDim     = [];
$dimensionScores = [];
$primaryResult   = null;
$totalScore      = 0;
$hasPosted       = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($hasPosted) {
    $answers = $_POST['answers'] ?? [];
    $selectedOptions = [];
    foreach ($questions as $q) {
        $qid = (int)$q['id'];
        if (empty($answers[$qid])) {
            $errors[] = "第 {$q['order_number']} 题尚未选择答案。";
            break;
        }
        $selectedId = (int)$answers[$qid];
        if (!isset($optionsById[$selectedId]) || (int)$optionsById[$selectedId]['question_id'] !== $qid) {
            $errors[] = "提交的数据无效，请刷新页面后重试。";
            break;
        }
        $selectedOptions[] = $optionsById[$selectedId];
    }

    if (!$errors) {
        foreach ($selectedOptions as $op) {
            $dimKey = $op['dimension_key'] ?: 'default';
            $score  = (int)$op['score'];
            if (!isset($scoresByDim[$dimKey])) {
                $scoresByDim[$dimKey] = 0;
            }
            $scoresByDim[$dimKey] += $score;
        }

        foreach ($scoresByDim as $dimKey => $score) {
            $dimensionScores[] = [
                'key'   => $dimKey,
                'title' => $dimensionMeta[$dimKey]['title'] ?? strtoupper($dimKey),
                'score' => $score,
            ];
        }

        $totalScore    = array_sum($scoresByDim);
        $primaryResult = getResultByTotalScore($pdo, $testId, (int)$totalScore);
    }
}

$runCount = 0;
try {
    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM test_runs WHERE test_id = ?");
    $cStmt->execute([$testId]);
    $runCount = (int)$cStmt->fetchColumn();
} catch (Exception $e) {
    $runCount = 0;
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($seo['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seo['description']) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($seo['url']) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seo['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seo['description']) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($seo['image']) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($seo['url']) ?>">
    <meta property="og:type" content="website">
    <link rel="stylesheet" href="/assets/css/style.css">
    <style>
        body { max-width: 720px; margin: 0 auto; padding: 24px 18px; font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"PingFang SC","Microsoft YaHei",sans-serif; }
        h1 { font-size: 24px; margin-bottom: 8px; }
        .desc { font-size: 14px; color: #555; margin-bottom: 8px; }
        .run-count { font-size: 13px; color: #6b7280; margin-bottom: 16px; }
        .question { margin-bottom: 18px; padding: 12px 14px; background:#fafafa; border-radius:10px; }
        .question-title { font-weight: 600; margin-bottom: 6px; }
        .option-list { margin:0; padding-left:18px; font-size:14px; }
        .errors { background:#ffecec; border:1px solid #ffb4b4; padding:8px 10px; border-radius:6px; margin-bottom:12px; font-size:14px; }
        .result-page { margin-top: 24px; display:flex; flex-direction:column; gap:20px; }
        .result-label { display:inline-flex; align-items:center; font-size:12px; padding:2px 10px; border-radius:999px; background:#eef2ff; color:#4338ca; margin-bottom:10px; }
        .result-title { font-size:24px; font-weight:700; margin:0 0 6px; }
        .result-subtitle { font-size:14px; color:#4b5563; margin:4px 0; }
        .result-card { background:#fff; border-radius:18px; border:1px solid #e5e7eb; box-shadow:0 20px 40px rgba(15,23,42,0.08); padding:18px 20px; }
        .result-dimensions { background:#f9fafb; border-radius:16px; padding:16px; }
        .result-dim-list { display:flex; flex-wrap:wrap; gap:10px; }
        .result-dim-chip { flex:1 1 140px; background:#fff; border:1px solid #e5e7eb; border-radius:12px; padding:10px 12px; display:flex; justify-content:space-between; align-items:center; }
        .result-actions { display:flex; gap:12px; flex-wrap:wrap; }
        .result-btn { padding:10px 18px; border-radius:999px; font-weight:600; text-decoration:none; }
        .result-btn-primary { background:linear-gradient(135deg,#4f46e5,#6366f1); color:#fff; }
        .result-btn-ghost { background:#fff; color:#4f46e5; border:1px solid #c7d2fe; }
        button { padding: 10px 18px; border-radius: 999px; border:none; background:#4f46e5; color:#fff; cursor:pointer; font-weight:600; }
        button:hover { filter:brightness(1.05); }
        a { color:#2563eb; text-decoration:none; }
        a:hover { text-decoration:underline; }
    </style>
</head>
<body>

<h1><?= htmlspecialchars($test['title']) ?></h1>

<?php if (!empty($test['description'])): ?>
    <p class="desc"><?= nl2br(htmlspecialchars($test['description'])) ?></p>
<?php endif; ?>

<p class="run-count">已有 <strong><?= number_format($runCount) ?></strong> 人参与测验</p>

<?php if ($errors): ?>
    <div class="errors">
        <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($hasPosted && !$errors): ?>
    <?php if ($primaryResult): ?>
        <div class="result-page">
            <header>
                <div class="result-label">检测结果</div>
                <h2 class="result-title"><?= htmlspecialchars($primaryResult['title']) ?></h2>
                <p class="result-subtitle">总分：<?= (int)$totalScore ?></p>
                <?php if (!empty($primaryResult['description'])): ?>
                    <p class="result-subtitle"><?= nl2br(htmlspecialchars($primaryResult['description'])) ?></p>
                <?php endif; ?>
            </header>
            <?php if (!empty($dimensionScores)): ?>
                <section class="result-dimensions">
                    <h3 style="margin:0 0 10px;">各维度得分</h3>
                    <div class="result-dim-list">
                        <?php foreach ($dimensionScores as $dim): ?>
                            <div class="result-dim-chip">
                                <div><?= htmlspecialchars($dim['title']) ?></div>
                                <div><?= (float)$dim['score'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>
            <footer class="result-actions">
                <a href="/<?= urlencode($test['slug']) ?>" class="result-btn result-btn-primary">再测一次</a>
                <a href="/" class="result-btn result-btn-ghost">看看别的测试</a>
            </footer>
        </div>
    <?php else: ?>
        <div class="result-page">
            <header>
                <div class="result-label">检测结果</div>
                <h2 class="result-title">暂未匹配结果</h2>
                <p class="result-subtitle">后台还没有配置对应的分数区间，稍后可以再试一次。</p>
            </header>
            <footer class="result-actions">
                <a href="/<?= urlencode($test['slug']) ?>" class="result-btn result-btn-primary">重新作答</a>
                <a href="/" class="result-btn result-btn-ghost">返回列表</a>
            </footer>
        </div>
    <?php endif; ?>
<?php else: ?>

    <form method="post">
        <?php foreach ($questions as $q): ?>
            <?php $qid = (int)$q['id']; ?>
            <div class="question">
                <div class="question-title">
                    Q<?= (int)$q['order_number'] ?>. <?= htmlspecialchars($q['content']) ?>
                </div>
                <ul class="option-list">
                    <?php foreach ($optionsByQuestion[$qid] ?? [] as $op): ?>
                        <li>
                            <label>
                                <input type="radio"
                                       name="answers[<?= $qid ?>]"
                                       value="<?= (int)$op['id'] ?>"
                                    <?= (isset($_POST['answers'][$qid]) && (int)$_POST['answers'][$qid] === (int)$op['id']) ? 'checked' : '' ?>>
                                <?= htmlspecialchars($op['content']) ?>
                            </label>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endforeach; ?>

        <button type="submit">提交并查看结果</button>
    </form>

    <p style="margin-top:12px;"><a href="/">← 返回首页</a></p>

<?php endif; ?>

</body>
</html>
