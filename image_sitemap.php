<?php
/**
 * 图片网站地图
 * 
 * 包含所有测验的封面图片，帮助搜索引擎索引图片内容
 */

header('Content-Type: application/xml; charset=utf-8');

require __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/Constants.php';

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host   = $scheme . '://' . $_SERVER['HTTP_HOST'];

// 检查 cover_image 字段是否存在
$hasCoverImage = false;
try {
    $pdo->query("SELECT cover_image FROM tests LIMIT 1");
    $hasCoverImage = true;
} catch (PDOException $e) {
    // 字段不存在，返回空的 sitemap
}

if (!$hasCoverImage) {
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";
    echo '</urlset>';
    exit;
}

// 查询有封面图片的已发布测验
$testsStmt = $pdo->prepare("
    SELECT id, slug, cover_image, title, updated_at 
    FROM tests 
    WHERE (status = ? OR status = 1)
    AND cover_image IS NOT NULL 
    AND cover_image != ''
    ORDER BY sort_order DESC, id DESC
");
$testsStmt->execute([Constants::TEST_STATUS_PUBLISHED]);
$tests = $testsStmt->fetchAll(PDO::FETCH_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">
    <?php foreach ($tests as $test):
        $slug = $test['slug'] ?? '';
        $pageUrl = $host . '/test.php?slug=' . urlencode($slug);
        $imageUrl = $test['cover_image'] ?? '';
        
        // 如果图片 URL 是相对路径，转换为绝对路径
        if ($imageUrl && !preg_match('/^https?:\/\//', $imageUrl)) {
            if ($imageUrl[0] === '/') {
                $imageUrl = $host . $imageUrl;
            } else {
                $imageUrl = $host . '/' . $imageUrl;
            }
        }
        
        if (empty($imageUrl)) {
            continue;
        }
        
        $lastmod = !empty($test['updated_at']) 
            ? date('Y-m-d', strtotime($test['updated_at']))
            : date('Y-m-d');
    ?>
    <url>
        <loc><?= htmlspecialchars($pageUrl) ?></loc>
        <lastmod><?= $lastmod ?></lastmod>
        <image:image>
            <image:loc><?= htmlspecialchars($imageUrl) ?></image:loc>
            <?php if (!empty($test['title'])): ?>
            <image:title><?= htmlspecialchars($test['title']) ?></image:title>
            <?php endif; ?>
        </image:image>
    </url>
    <?php endforeach; ?>
</urlset>

