<?php
require_once __DIR__ . '/seo_helper.php';

$finalTest       = $finalTest ?? null;
$finalResult     = $finalResult ?? null;
$finalScores     = $finalScores ?? [];
$finalTotalScore = isset($finalTotalScore) ? (int)$finalTotalScore : array_sum($finalScores);

$seo = [
    'title'       => 'DoFun 性格实验室 - 测试结果',
    'description' => '探索你的测试结果与维度得分。',
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

<?php if (!$finalResult): ?>
    <p>暂未匹配到结果，可能是后台还未配置完整的分数区间。稍后再试试吧。</p>
<?php else: ?>
    <section style="margin:20px 0;padding:18px;border-radius:16px;background:#fff;border:1px solid #e5e7eb;box-shadow:0 18px 35px rgba(15,23,42,0.08);">
        <div style="font-size:13px;color:#6b7280;margin-bottom:8px;">检测结果</div>
        <h2 style="margin:0 0 6px;font-size:24px;"><?= htmlspecialchars($finalResult['title']) ?></h2>
        <p style="margin:0 0 10px;color:#4b5563;">总分：<?= $finalTotalScore ?></p>
        <?php if (!empty($finalResult['description'])): ?>
            <div style="font-size:15px;color:#1f2937;line-height:1.8;">
                <?= nl2br(htmlspecialchars($finalResult['description'])) ?>
            </div>
        <?php endif; ?>
        <?php if (!empty($finalResult['image_url'])): ?>
            <div style="margin-top:12px;">
                <img src="<?= htmlspecialchars($finalResult['image_url']) ?>" alt="result image" style="max-width:100%;border-radius:12px;">
            </div>
        <?php endif; ?>
    </section>

    <?php if ($finalScores): ?>
        <section style="margin:20px 0;padding:16px;border-radius:16px;background:#f9fafb;">
            <h3 style="margin:0 0 12px;font-size:16px;">各维度得分</h3>
            <ul style="list-style:none;padding:0;margin:0;display:flex;flex-wrap:wrap;gap:10px;">
                <?php foreach ($finalScores as $dim => $score): ?>
                    <li style="flex:1 1 160px;min-width:150px;background:#fff;border-radius:10px;border:1px solid #e5e7eb;padding:10px 12px;">
                        <div style="font-size:13px;color:#475569;"><?= htmlspecialchars($dim) ?></div>
                        <div style="font-size:18px;font-weight:600;color:#111827;"><?= (float)$score ?></div>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>
<?php endif; ?>

<p><a href="/">← 返回测试列表</a></p>
</body>
</html>
