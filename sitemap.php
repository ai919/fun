<?php
/**
 * 优化的网站地图生成器
 * 
 * 功能：
 * - 使用正确的 URL 格式（test.php?slug=）
 * - 使用 updated_at 字段作为 lastmod
 * - 只包含已发布的测验
 * - 支持分页（如果超过 50,000 个 URL）
 * - 包含首页和测验列表页
 */

header('Content-Type: application/xml; charset=utf-8');

require __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/Constants.php';

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host   = $scheme . '://' . $_SERVER['HTTP_HOST'];

// 每页最多 50,000 个 URL（Google 限制）
$maxUrlsPerSitemap = 50000;
$page = max(1, (int)($_GET['page'] ?? 1));
$offset = ($page - 1) * $maxUrlsPerSitemap;

// 查询已发布的测验总数（兼容旧数据：status = 1 或 status = 'published'）
$countStmt = $pdo->prepare("
    SELECT COUNT(*) 
    FROM tests 
    WHERE (status = ? OR status = 1)
    AND slug IS NOT NULL 
    AND slug != ''
");
$countStmt->execute([Constants::TEST_STATUS_PUBLISHED]);
$totalTests = (int)$countStmt->fetchColumn();

// 查询当前页的测验（只包含有 slug 的测验）
$testsStmt = $pdo->prepare("
    SELECT id, slug, updated_at, created_at 
    FROM tests 
    WHERE (status = ? OR status = 1)
    AND slug IS NOT NULL 
    AND slug != ''
    ORDER BY sort_order DESC, id DESC
    LIMIT ? OFFSET ?
");
$testsStmt->execute([Constants::TEST_STATUS_PUBLISHED, $maxUrlsPerSitemap, $offset]);
$tests = $testsStmt->fetchAll(PDO::FETCH_ASSOC);

// 计算总页数
$totalPages = (int)ceil(($totalTests + 2) / $maxUrlsPerSitemap); // +2 是首页和列表页

// 如果是第一页，包含首页和列表页
$urlCount = 0;
if ($page === 1) {
    $urlCount = 2; // 首页 + 列表页
}

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <?php if ($page === 1): ?>
    <!-- 首页 -->
    <url>
        <loc><?= htmlspecialchars($host . '/') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <!-- 测验列表页 -->
    <url>
        <loc><?= htmlspecialchars($host . '/index.php') ?></loc>
        <lastmod><?= date('Y-m-d') ?></lastmod>
        <changefreq>daily</changefreq>
        <priority>0.9</priority>
    </url>
    <?php endif; ?>
    
    <!-- 测验页面 -->
    <?php foreach ($tests as $test):
        $slug = trim($test['slug'] ?? '');
        if (empty($slug)) {
            continue; // 跳过没有 slug 的测验
        }
        
        $loc = $host . '/test.php?slug=' . urlencode($slug);
        
        // 优先使用 updated_at，如果没有则使用 created_at
        $lastmod = !empty($test['updated_at']) && $test['updated_at'] !== '0000-00-00 00:00:00'
            ? date('Y-m-d', strtotime($test['updated_at']))
            : (!empty($test['created_at']) && $test['created_at'] !== '0000-00-00 00:00:00'
                ? date('Y-m-d', strtotime($test['created_at']))
                : date('Y-m-d'));
    ?>
    <url>
        <loc><?= htmlspecialchars($loc) ?></loc>
        <lastmod><?= htmlspecialchars($lastmod) ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php endforeach; ?>
</urlset>
