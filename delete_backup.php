<?php
$config = require __DIR__ . '/backup_config.php';
require __DIR__ . '/lib/backup_helpers.php';
$userToken = $_GET['token'] ?? '';
backup_require_token($config, $userToken);

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Invalid id';
    exit;
}

$dbConf = $config['db'];
try {
    $pdo = new PDO(
        'mysql:host=' . $dbConf['host'] . ';port=' . $dbConf['port'] . ';dbname=' . $dbConf['name'] . ';charset=utf8mb4',
        $dbConf['user'],
        $dbConf['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo 'DB error';
    exit;
}

$stmt = $pdo->prepare("SELECT file_path FROM backup_logs WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if ($row) {
    $path = $row['file_path'];
    if ($path && file_exists($path)) {
        @unlink($path);
    }
    $pdo->prepare("DELETE FROM backup_logs WHERE id = :id")->execute([':id' => $id]);
}

header('Location: /admin/backup_logs.php');
exit;
