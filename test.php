<?php
// test.php：显示单个测试 + 处理提交 + 记录日志（线性计分模式）
require __DIR__ . '/lib/db_connect.php';

// 从请求路径获取 slug，例如 /love /animal
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$slug = trim($path, '/');

// 防止直接访问根路径
if ($slug === '') {
    header('Location: /');
    exit;
}

// 获取测试基本信息
$stmt = $pdo->prepare("SELECT * FROM tests WHERE slug = ? LIMIT 1");
$stmt->execute([$slug]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    http_response_code(404);
    echo "<h1>测试不存在</h1>";
    exit;
}

$testId = (int)$test['id'];

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
    }
}

$errors       = [];
$scoresByDim  = [];
$resultsByDim = [];
$hasPosted    = ($_SERVER['REQUEST_METHOD'] === 'POST');

if ($hasPosted) {
    $answers = $_POST['answers'] ?? [];

    // 1. 检查每道题是否都有答案
    foreach ($questions as $q) {
        $qid = (int)$q['id'];
        if (empty($answers[$qid])) {
            $errors[] = "有题目还没有选择答案。";
            break;
        }
    }

    if (!$errors) {
        // 2. 取出所选选项信息（防止 HY093）
        $selectedOptionIds = $answers ? array_map('intval', $answers) : [];
        $selectedOptionIds = array_values(array_unique($selectedOptionIds));

        if (!$selectedOptionIds) {
            $errors[] = "没有有效的选项被提交，请重试。";
        } else {
            $placeholders = implode(',', array_fill(0, count($selectedOptionIds), '?'));
            $sql          = "SELECT * FROM options WHERE id IN ($placeholders)";
            $optStmt      = $pdo->prepare($sql);
            $optStmt->execute($selectedOptionIds);
            $opts = $optStmt->fetchAll(PDO::FETCH_ASSOC);

            if (!$opts || count($opts) !== count($selectedOptionIds)) {
                $errors[] = "部分选项无效，请重试。";
            } else {
                // 3. 计算各维度得分
                foreach ($opts as $op) {
                    $dimKey = $op['dimension_key'] ?: 'default';
                    $score  = (int)$op['score'];

                    if (!isset($scoresByDim[$dimKey])) {
                        $scoresByDim[$dimKey] = 0;
                    }
                    $scoresByDim[$dimKey] += $score;
                }

                // 4. 根据得分匹配结果
                if ($scoresByDim) {
                    $resultStmt = $pdo->prepare(
                        "SELECT * FROM results
                         WHERE test_id = ?
                           AND dimension_key = ?
                           AND range_min <= ?
                           AND range_max >= ?
                         LIMIT 1"
                    );

                    foreach ($scoresByDim as $dimKey => $score) {
                        $resultStmt->execute([$testId, $dimKey, $score, $score]);
                        $row = $resultStmt->fetch(PDO::FETCH_ASSOC);
                        if ($row) {
                            $resultsByDim[$dimKey] = $row;
                        } else {
                            $resultsByDim[$dimKey] = null; // 没有匹配也记录一下
                        }
                    }
                }

                // 5. 记录到 test_runs / test_run_scores（日志）
                try {
                    $pdo->beginTransaction();

                    // 5.1 插入 test_runs
                    $ip = $_SERVER['REMOTE_ADDR']     ?? null;
                    $ua = $_SERVER['HTTP_USER_AGENT'] ?? null;
                    if ($ua !== null && strlen($ua) > 255) {
                        $ua = substr($ua, 0, 255);
                    }

                    $insRun = $pdo->prepare(
                        "INSERT INTO test_runs (test_id, client_ip, user_agent)
                         VALUES (?, ?, ?)"
                    );
                    $insRun->execute([$testId, $ip, $ua]);
                    $runId = (int)$pdo->lastInsertId();

                    // 5.2 插入每个维度得分
                    if ($scoresByDim) {
                        $insScore = $pdo->prepare(
                            "INSERT INTO test_run_scores (run_id, dimension_key, score, result_id)
                             VALUES (?, ?, ?, ?)"
                        );
                        foreach ($scoresByDim as $dimKey => $score) {
                            $resRow   = $resultsByDim[$dimKey] ?? null;
                            $resultId = ($resRow && !empty($resRow['id']))
                                ? (int)$resRow['id']
                                : null;
                            $insScore->execute([$runId, $dimKey, $score, $resultId]);
                        }
                    }

                    $pdo->commit();
                } catch (Exception $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                    // 记录失败不影响用户看到结果，这里静默忽略或写日志
                }
            }
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
    <title><?= htmlspecialchars($test['title']) ?> · 趣味测试</title>
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
        .result-block { margin-top:20px; padding:12px 14px; border-radius:8px; background:#f0fdf4; border:1px solid #bbf7d0; }
        .result-block h2 { font-size:18px; margin:0 0 6px; }
        .result-block .dim-title { font-size:15px; font-weight:600; margin-top:8px; }
        .hint { font-size:13px; color:#666; }
        .back-link { margin-top:16px; font-size:13px; }
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

<?php if ($hasPosted && !$errors && $scoresByDim): ?>
    <div class="result-block">
        <h2>测试结果</h2>
        <?php foreach ($scoresByDim as $dimKey => $score): ?>
            <?php $res = $resultsByDim[$dimKey] ?? null; ?>
            <div class="dim-result">
                <div class="dim-title">
                    维度 <code><?= htmlspecialchars($dimKey) ?></code> 总分：<?= (int)$score ?>
                </div>
                <?php if ($res): ?>
                    <div><strong><?= htmlspecialchars($res['title']) ?></strong></div>
                    <?php if (!empty($res['description'])): ?>
                        <div style="font-size:14px; margin-top:4px;">
                            <?= nl2br(htmlspecialchars($res['description'])) ?>
                        </div>
                    <?php endif; ?>
                <?php else: ?>
                    <div class="hint">（当前分数没有匹配到任何结果区间。）</div>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        <p class="hint" style="margin-top:10px;">
            * 本次结果已匿名记录，用于后续统计分析。
        </p>
    </div>

    <div class="back-link">
        <a href="/">← 返回测试首页</a>
    </div>
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
