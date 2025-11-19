<?php
// 这里依赖 submit.php 传入的：
// $finalTest, $finalScores, $finalDimensionResult
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($finalTest['title'] ?? '测试结果') ?></title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" href="/favicon.ico">
</head>
<body>
<h1><?= htmlspecialchars($finalTest['title'] ?? '测试结果') ?></h1>

<?php if (!$finalDimensionResult): ?>
    <p>暂时没有匹配到结果，请检查 results 表的分数区间设置。</p>
<?php else: ?>
    <?php foreach ($finalDimensionResult as $dim => $data): 
        $res   = $data['result'];
        $score = $data['score'];
    ?>
        <div class="dimension-block">
            <h2><?= htmlspecialchars($res['title']) ?>（<?= htmlspecialchars($dim) ?>，得分：<?= (int)$score ?>）</h2>
            <p><?= nl2br(htmlspecialchars($res['description'])) ?></p>
        </div>
        <hr>
    <?php endforeach; ?>
<?php endif; ?>

<p><a href="/">返回首页</a></p>
</body>
</html>
