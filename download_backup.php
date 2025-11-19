<?php
$config = require __DIR__ . '/backup_config.php';
$userToken = $_GET['token'] ?? '';

if ($userToken !== $config['token']) {
    http_response_code(403);
    echo 'Forbidden';
    exit;
}

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

$stmt = $pdo->prepare("SELECT * FROM backup_logs WHERE id = :id AND status = 'success' LIMIT 1");
$stmt->execute([':id' => $id]);
$row = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$row) {
    http_response_code(404);
    echo 'Backup not found';
    exit;
}

$filePath = $row['file_path'];
if (!$filePath || !file_exists($filePath)) {
    http_response_code(404);
    echo 'File missing';
    exit;
}

header('Content-Type: application/zip');
header('Content-Length: ' . filesize($filePath));
header('Content-Disposition: attachment; filename="' . basename($filePath) . '"');

readfile($filePath);
exit;
