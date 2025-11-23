<?php
/**
 * 网站地图索引文件
 * 
 * 当网站有多个 sitemap 文件时，使用此索引文件
 * 如果只有一个 sitemap，可以直接使用 sitemap.php
 */

header('Content-Type: application/xml; charset=utf-8');

require __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/Constants.php';

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host   = $scheme . '://' . $_SERVER['HTTP_HOST'];

// 每页最多 50,000 个 URL
$maxUrlsPerSitemap = 50000;

// 查询已发布的测验总数（只包含有 slug 的测验）
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM tests 
    WHERE (status = ? OR status = 1)
    AND slug IS NOT NULL 
    AND slug != ''
");
$countStmt->execute([Constants::TEST_STATUS_PUBLISHED]);
$totalTests = (int)$countStmt->fetchColumn();

// 计算需要的 sitemap 数量（+2 是首页和列表页）
$totalUrls = $totalTests + 2;
$totalSitemaps = (int)ceil($totalUrls / $maxUrlsPerSitemap);

// 如果只有一个 sitemap，重定向到主 sitemap
if ($totalSitemaps <= 1) {
    header('Location: /sitemap.php', true, 301);
    exit;
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php for ($i = 1; $i <= $totalSitemaps; $i++): ?>
    <sitemap>
        <loc><?= htmlspecialchars($host . '/sitemap.php?page=' . $i) ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
    </sitemap>
    <?php endfor; ?>
    
    <!-- 图片 sitemap（如果存在） -->
    <?php
    // 检查是否有图片需要包含在 sitemap 中
    $hasImages = false;
    try {
        // 先检查字段是否存在
        $pdo->query("SELECT cover_image FROM tests LIMIT 1");
        
        // 字段存在，检查是否有图片
        $imageCheckStmt = $pdo->prepare("
            SELECT COUNT(*) 
            FROM tests 
            WHERE (status = ? OR status = 1)
            AND cover_image IS NOT NULL 
            AND cover_image != ''
        ");
        $imageCheckStmt->execute([Constants::TEST_STATUS_PUBLISHED]);
        $hasImages = (int)$imageCheckStmt->fetchColumn() > 0;
    } catch (PDOException $e) {
        // 如果 cover_image 字段不存在，忽略错误
        $hasImages = false;
    }
    
    if ($hasImages):
    ?>
    <sitemap>
        <loc><?= htmlspecialchars($host . '/image_sitemap.php') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
    </sitemap>
    <?php endif; ?>
</sitemapindex>

