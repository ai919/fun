<?php
// 如果用 PHP 内置服务器（php -S），这段是让静态文件直接返回
if (php_sapi_name() === 'cli-server') {
    $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
    $file = __DIR__ . $path;
    if ($file !== __FILE__ && is_file($file)) {
        return false;
    }
}

require __DIR__ . '/lib/db_connect.php';

$uri  = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$slug = trim($uri, '/');

// 根路径 → 首页
if ($slug === '' || $slug === 'index.php') {
    require __DIR__ . '/index.php';
    exit;
}

// 根据 slug 找测试
$stmt = $pdo->prepare("SELECT id FROM tests WHERE slug = ? LIMIT 1");
$stmt->execute([$slug]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if ($test) {
    $_GET['id'] = $test['id'];
    require __DIR__ . '/test.php';
    exit;
}

// 提交结果（POST 到 /submit.php）
if ($slug === 'submit.php') {
    require __DIR__ . '/submit.php';
    exit;
}

// 兜底 404
http_response_code(404);
echo '404 - 页面不存在';
