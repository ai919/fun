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
    'title'       => 'DoFun 性格实验室 - 测试结果',
    'description' => '探索你的测试结果。',
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
<body style="max-width:720px;margin:0 auto;padding:24px 18px;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,'PingFang SC','Microsoft YaHei',sans-serif;">

<h1><?= htmlspecialchars($finalTest['title'] ?? '测试结果') ?></h1>
<?php if (!empty($finalTest['subtitle'])): ?>
    <p style="color:#6b7280;"><?= htmlspecialchars($finalTest['subtitle']) ?></p>
<?php endif; ?>

<?php if (!$finalResult): ?>
    <p>暂未匹配到结果，可能是后台还未配置完整。</p>
<?php else: ?>
    <section style="margin:20px 0;padding:18px;border-radius:16px;background:#fff;border:1px solid #e5e7eb;box-shadow:0 20px 40px rgba(15,23,42,0.08);">
        <div style="font-size:13px;color:#6b7280;margin-bottom:8px;">检测结果</div>
        <h2 style="margin:0 0 10px;"><?= htmlspecialchars($finalResult['title']) ?></h2>
        <?php if (!empty($finalResult['description'])): ?>
            <div style="font-size:15px;line-height:1.8;color:#1f2937;">
                <?= nl2br(htmlspecialchars($finalResult['description'])) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($finalResult['image_url'])): ?>
            <div style="margin-top:12px;">
                <img src="<?= htmlspecialchars($finalResult['image_url']) ?>" alt="result image" style="max-width:100%;border-radius:12px;">
            </div>
        <?php endif; ?>
    </section>
    <?php if ($codeCounts): ?>
        <section style="margin:12px 0;padding:14px;border-radius:12px;background:#f9fafb;">
            <strong>各类型得票数：</strong>
            <ul style="list-style:none;padding:0;margin:8px 0 0;">
                <?php foreach ($codeCounts as $code => $count): ?>
                    <li><?= htmlspecialchars($code) ?> ： <?= (int)$count ?></li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
<?php endif; ?>

<p><a href="/">← 返回测试列表</a></p>
</body>
</html>
