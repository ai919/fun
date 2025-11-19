<?php
header('Content-Type: application/xml; charset=utf-8');

require __DIR__ . '/lib/db_connect.php';

$scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
$host   = $scheme . '://' . $_SERVER['HTTP_HOST'];

$testsStmt = $pdo->query("SELECT id, created_at FROM tests");
$tests = $testsStmt->fetchAll(PDO::FETCH_ASSOC);

echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
?>
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc><?= htmlspecialchars($host . '/') ?></loc>
        <changefreq>daily</changefreq>
        <priority>1.0</priority>
    </url>
    <?php foreach ($tests as $test):
        $id = (int)$test['id'];
        $loc = $host . '/quiz.php?id=' . $id;
        $lastmod = !empty($test['created_at'])
            ? date('Y-m-d', strtotime($test['created_at']))
            : date('Y-m-d');
    ?>
    <url>
        <loc><?= htmlspecialchars($loc) ?></loc>
        <lastmod><?= $lastmod ?></lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.8</priority>
    </url>
    <?php endforeach; ?>
</urlset>
