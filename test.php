<?php
// test.php：显示单个测试 + 处理提交 + 记录日志（线性计分模式）
require __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/seo_helper.php';

// 从请求路径获取 slug，例如 /love /animal
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$slug = trim($path, '/');

// 防止直接访问根路径
if ($slug === '') {
    header('Location: /');
    exit;
}

// 获取测试基本信息
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

// 获取题目
$qStmt = $pdo->prepare(
    "SELECT * FROM questions
     WHERE test_id = ?
     ORDER BY order_number ASC, id ASC"
);
$qStmt->execute([$testId]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

if (!$questions) {
    ?>
    <!doctype html>
    <html lang="zh-CN">
    <head>
        <meta charset="utf-8">
        <title><?= htmlspecialchars($test['title']) ?> · 趣味测试</title>
    </head>
    <body>
    <h1><?= htmlspecialchars($test['title']) ?></h1>
    <p>这个测试还没有题目，敬请期待。</p>
    <p><a href="/">← 返回首页</a></p>
    </body>
    </html>
    <?php
    exit;
}

// 获取所有题目的选项（按 question_id 分组）
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
        $qid = $row['question_id'];
        if (!isset($optionsByQuestion[$qid])) {
            $optionsByQuestion[$qid] = [];
        }
        $optionsByQuestion[$qid][] = $row;
        $optionsById[(int)$row['id']] = $row;
    }
}

$dimMetaStmt = $pdo->prepare("SELECT key_name, title FROM dimensions WHERE test_id = ?");
$dimMetaStmt->execute([$testId]);
$dimensionMeta = [];
while ($row = $dimMetaStmt->fetch(PDO::FETCH_ASSOC)) {
    $dimensionMeta[$row['key_name']] = $row;
}

$errors          = [];
$scoresByDim     = [];
$resultsByDim    = [];
$dimensionScores = [];
$primaryResult   = null;
$hasPosted       = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($hasPosted) {
    $answers = $_POST['answers'] ?? [];

    // 1. 检查每道题是否都有答案
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
    }

}

// 统计总完成次数（用于前台显示“已有 X 人做过此测试”）
$runCount = 0;
try {
    $cStmt = $pdo->prepare("SELECT COUNT(*) FROM test_runs WHERE test_id = ?");
    $cStmt->execute([$testId]);
    $runCount = (int)$cStmt->fetchColumn();
} catch (Exception $e) {
    // 忽略统计错误
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
        body {
            max-width: 720px;
            margin: 0 auto;
            padding: 20px 16px 40px;
            font-family: -apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"PingFang SC","Microsoft YaHei",sans-serif;
        }
        h1 { font-size: 22px; margin-bottom: 8px; }
        .desc { font-size: 14px; color: #555; margin-bottom: 8px; }
        .run-count { font-size: 13px; color: #6b7280; margin-bottom: 16px; }
        .question { margin-bottom: 18px; padding: 10px 12px; background:#fafafa; border-radius:8px; }
        .question-title { font-weight: 600; margin-bottom: 6px; }
        .option-list { margin:0; padding-left:18px; font-size:14px; }
        .errors { background:#ffecec; border:1px solid #ffb4b4; padding:8px 10px; border-radius:6px; margin-bottom:12px; font-size:14px; }
        .result-page {
            margin-top: 24px;
            display: flex;
            flex-direction: column;
            gap: 20px;
        }
        .result-header {
            text-align: left;
        }
        .result-label {
            display: inline-flex;
            align-items: center;
            font-size: 12px;
            padding: 2px 10px;
            border-radius: 999px;
            background: #eef2ff;
            color: #4338ca;
            margin-bottom: 10px;
        }
        .result-title {
            font-size: 24px;
            font-weight: 700;
            margin: 0 0 8px;
        }
        .result-emoji {
            font-size: 28px;
            margin-right: 8px;
        }
        .result-subtitle {
            font-size: 14px;
            color: #4b5563;
            margin: 0;
            line-height: 1.6;
        }
        .result-card {
            background: #ffffff;
            border-radius: 18px;
            border: 1px solid #e5e7eb;
            box-shadow: 0 20px 45px rgba(15,23,42,0.08);
            padding: 18px 20px;
        }
        .result-card-heading {
            font-size: 16px;
            color: #4f46e5;
            margin: 0 0 8px;
        }
        .result-card-body {
            font-size: 15px;
            color: #1f2937;
            line-height: 1.8;
        }
        .result-dimensions {
            background: #f9fafb;
            border-radius: 16px;
            padding: 16px;
        }
        .result-dim-title {
            font-size: 15px;
            font-weight: 600;
            margin: 0 0 12px;
            color: #1f2937;
        }
        .result-dim-list {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
        }
        .result-dim-chip {
            flex: 1 1 140px;
            min-width: 140px;
            background: #ffffff;
            border: 1px solid #e5e7eb;
            border-radius: 12px;
            padding: 10px 12px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            box-shadow: inset 0 0 0 1px rgba(226,232,240,0.6);
        }
        .result-dim-chip .dim-name {
            font-size: 13px;
            color: #475569;
        }
        .result-dim-chip .dim-score {
            font-size: 16px;
            font-weight: 600;
            color: #111827;
        }
        .result-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            margin-top: 10px;
        }
        .result-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            padding: 10px 18px;
            border-radius: 999px;
            font-size: 14px;
            font-weight: 600;
            border: none;
            cursor: pointer;
            text-decoration: none;
        }
        .result-btn-primary {
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            color: #fff;
            box-shadow: 0 12px 30px rgba(79,70,229,0.35);
        }
        .result-btn-ghost {
            background: #fff;
            color: #4f46e5;
            border: 1px solid #c7d2fe;
        }
        button { padding: 8px 16px; border-radius: 999px; border:none; background:#4f46e5; color:#fff; cursor:pointer; }
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

<p class="run-count">
    已有 <strong><?= number_format($runCount) ?></strong> 人做过这个测试
</p>

<?php if ($errors): ?>
    <div class="errors">
        <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($hasPosted && !$errors): ?>
    <?php $result = $primaryResult; ?>
    <?php
        $resultSummary = $result['summary'] ?? ($result['description'] ?? '');
        $resultDetail  = $result['detail_text'] ?? ($result['description'] ?? '');
    ?>
    <?php if ($result): ?>
        <div class="result-page">
            <header class="result-header">
                <div class="result-label">测验结果</div>
                <h1 class="result-title">
                    <span class="result-emoji"><?= htmlspecialchars($test['title_emoji'] ?? '✨', ENT_QUOTES, 'UTF-8') ?></span>
                    <?= htmlspecialchars($result['title'], ENT_QUOTES, 'UTF-8') ?>
                </h1>
                <?php if (!empty($resultSummary)): ?>
                    <p class="result-subtitle">
                        <?= nl2br(htmlspecialchars($resultSummary, ENT_QUOTES, 'UTF-8')) ?>
                    </p>
                <?php endif; ?>
            </header>

            <section class="result-card">
                <h2 class="result-card-heading">你的状态解读</h2>
                <div class="result-card-body">
                    <?= nl2br(htmlspecialchars($resultDetail, ENT_QUOTES, 'UTF-8')) ?>
                </div>
            </section>

            <?php if (!empty($dimensionScores)): ?>
                <section class="result-dimensions">
                    <h3 class="result-dim-title">各维度评分</h3>
                    <div class="result-dim-list">
                        <?php foreach ($dimensionScores as $dim): ?>
                            <div class="result-dim-chip">
                                <div class="dim-name"><?= htmlspecialchars($dim['title'] ?? strtoupper($dim['key']), ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="dim-score"><?= (float)$dim['score'] ?></div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </section>
            <?php endif; ?>

            <footer class="result-actions">
                <a href="/<?= urlencode($test['slug']) ?>" class="result-btn result-btn-primary">再测一次</a>
                <a href="/" class="result-btn result-btn-ghost">看看其他测验</a>
            </footer>
        </div>
    <?php else: ?>
        <div class="result-page">
            <header class="result-header">
                <div class="result-label">测验结果</div>
                <h1 class="result-title">
                    <span class="result-emoji">🤔</span>
                    暂未匹配到结果
                </h1>
                <p class="result-subtitle">这并不代表你“没有问题”，只是当前规则还不够细致。</p>
            </header>
            <footer class="result-actions">
                <a href="/<?= urlencode($test['slug']) ?>" class="result-btn result-btn-primary">换个答案再试试</a>
                <a href="/" class="result-btn result-btn-ghost">回到测验列表</a>
            </footer>
        </div>
    <?php endif; ?>
<?php else: ?>

    <form method="post">
        <?php foreach ($questions as $idx => $q): ?>
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

        <button type="submit">提交查看结果</button>
    </form>

    <div class="back-link">
        <a href="/">← 返回测试首页</a>
    </div>

<?php endif; ?>

</body>
</html>
