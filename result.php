<?php
require_once __DIR__ . '/seo_helper.php';

$finalTest   = $finalTest ?? null;
$finalResult = $finalResult ?? null;
$codeCounts  = $codeCounts ?? [];

if ((!$finalTest || !$finalResult) && isset($_GET['test_id'], $_GET['code'])) {
    require __DIR__ . '/lib/db_connect.php';
    $testId = (int)$_GET['test_id'];
    $code   = trim($_GET['code']);
    if ($testId > 0 && $code !== '') {
        $testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
        $testStmt->execute([$testId]);
        $finalTest = $testStmt->fetch(PDO::FETCH_ASSOC);

        $resStmt = $pdo->prepare("SELECT * FROM results WHERE test_id = ? AND code = ? LIMIT 1");
        $resStmt->execute([$testId, $code]);
        $finalResult = $resStmt->fetch(PDO::FETCH_ASSOC);
    }
}

 $seo = [
    'title'       => 'DoFun 性格实验室 - 测验结果',
    'description' => '探索你的测验结果。',
    'url'         => df_current_url(),
    'image'       => df_base_url() . '/og.php?scope=result',
];
if ($finalTest && $finalResult) {
    $seo = df_seo_for_result($finalTest, $finalResult);
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($seo['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seo['description']) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($seo['url']) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seo['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seo['description']) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($seo['image']) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($seo['url']) ?>">
    <meta property="og:type" content="website">
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body>

<?php
$emoji = trim($finalTest['emoji'] ?? ($finalTest['title_emoji'] ?? ''));
?>

<div class="result-page">
    <?php if ($finalTest): ?>
        <header class="result-hero">
            <?php if ($emoji !== ''): ?>
                <div class="result-emoji"><?= htmlspecialchars($emoji) ?></div>
            <?php endif; ?>
            <div class="result-pill">测验结果</div>
            <h1 class="result-title"><?= htmlspecialchars($finalResult['title'] ?? '测验结果') ?></h1>
            <p class="result-subtitle">
                来自测验：<?= htmlspecialchars($finalTest['title'] ?? '') ?>
            </p>
        </header>
    <?php endif; ?>

    <?php if (!$finalResult): ?>
        <p>暂未匹配到结果，可能是后台还未配置完整。</p>
    <?php else: ?>
        <section class="result-body">
            <p class="result-highlight">
                这代表你在此次测验中，呈现出的核心倾向是：
                <strong><?= htmlspecialchars($finalResult['title']) ?></strong>
            </p>
            <div class="result-description">
                <?= nl2br(htmlspecialchars($finalResult['description'] ?? '')) ?>
            </div>
            <?php if (!empty($finalResult['image_url'])): ?>
                <div style="margin-top:12px;">
                    <img src="<?= htmlspecialchars($finalResult['image_url']) ?>" alt="result image" style="max-width:100%;border-radius:12px;">
                </div>
            <?php endif; ?>
        </section>
    <?php endif; ?>

    <footer class="result-actions">
        <?php if ($finalTest): ?>
            <a href="/test.php?slug=<?= urlencode($finalTest['slug'] ?? '') ?>" class="btn-secondary">再测一次</a>
        <?php endif; ?>
        <a href="/index.php" class="btn-primary">返回全部测验</a>
    </footer>
</div>
</body>
</html>
