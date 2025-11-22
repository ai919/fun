<?php
$config = require __DIR__ . '/backup_config.php';
require __DIR__ . '/lib/backup_helpers.php';
require_once __DIR__ . '/lib/Constants.php';

$userToken = $_GET['token'] ?? '';
backup_require_token($config, $userToken);

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
    echo '数据库连接失败：' . $e->getMessage();
    exit;
}

@set_time_limit(0);
@ini_set('memory_limit', '512M');

$rootPath  = rtrim($config['root_path'], DIRECTORY_SEPARATOR);
$tempDir   = rtrim($config['temp_dir'], DIRECTORY_SEPARATOR);
$backupDir = rtrim($config['backup_dir'], DIRECTORY_SEPARATOR);

if (!is_dir($backupDir)) {
    if (!mkdir($backupDir, 0755, true) && !is_dir($backupDir)) {
        http_response_code(500);
        echo '无法创建备份目录：' . $backupDir;
        exit;
    }
}

$timestamp = date('Ymd_His');
$baseName  = 'dofun_backup_' . $timestamp;

$sqlFile = $tempDir . DIRECTORY_SEPARATOR . $baseName . '.sql';
$zipFile = $backupDir . DIRECTORY_SEPARATOR . $baseName . '.zip';

$dumpPath = $config['mysqldump_path'];

// 创建临时 MySQL 配置文件，避免密码出现在进程列表中
$mysqlConfigFile = $tempDir . DIRECTORY_SEPARATOR . 'mysql_config_' . uniqid() . '.cnf';
$mysqlConfigContent = sprintf(
    "[client]\nhost=%s\nport=%s\nuser=%s\npassword=%s\n",
    $dbConf['host'],
    $dbConf['port'],
    $dbConf['user'],
    $dbConf['password']
);

if (file_put_contents($mysqlConfigFile, $mysqlConfigContent) === false) {
    http_response_code(500);
    echo "无法创建 MySQL 配置文件";
    exit;
}

// 设置文件权限为 600（仅所有者可读）
@chmod($mysqlConfigFile, 0600);

// 使用 --defaults-file 选项，密码不会出现在进程列表中
$cmd = sprintf(
    '%s --defaults-file=%s --default-character-set=utf8mb4 %s > %s',
    escapeshellcmd($dumpPath),
    escapeshellarg($mysqlConfigFile),
    escapeshellarg($dbConf['name']),
    escapeshellarg($sqlFile)
);

exec($cmd, $output, $returnVar);

// 立即删除临时配置文件
@unlink($mysqlConfigFile);

if ($returnVar !== 0 || !file_exists($sqlFile)) {
    http_response_code(500);
    echo "数据库备份失败，请检查 mysqldump 配置。";
    exit;
}

$zip = new ZipArchive();
if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
    @unlink($sqlFile);
    http_response_code(500);
    echo "无法创建 ZIP 文件";
    exit;
}

$zip->addFile($sqlFile, 'database.sql');

$rootLen = strlen($rootPath) + 1;

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($rootPath, FilesystemIterator::SKIP_DOTS),
    RecursiveIteratorIterator::SELF_FIRST
);

foreach ($iterator as $file) {
    /** @var SplFileInfo $file */
    $filePath = $file->getPathname();
    $basename = basename($filePath);

    if ($basename === 'backup.php' || $basename === 'backup_config.php') {
        continue;
    }

    if (strpos($filePath, $backupDir) === 0) {
        continue;
    }

    if (strpos($filePath, DIRECTORY_SEPARATOR . '.git') !== false) {
        continue;
    }

    $relativePath = substr($filePath, $rootLen);
    if ($file->isDir()) {
        $zip->addEmptyDir($relativePath);
    } else {
        $zip->addFile($filePath, $relativePath);
    }
}

$zip->close();
@unlink($sqlFile);

if (!file_exists($zipFile)) {
    http_response_code(500);
    echo "打包失败";
    exit;
}

$fileSize = filesize($zipFile);
$ip       = $_SERVER['REMOTE_ADDR'] ?? null;

$stmt = $pdo->prepare("INSERT INTO backup_logs (filename, file_path, file_size, status, message, ip)
                       VALUES (:filename, :file_path, :file_size, :status, :message, :ip)");
$stmt->execute([
    ':filename'  => basename($zipFile),
    ':file_path' => $zipFile,
    ':file_size' => $fileSize,
    ':status'    => Constants::BACKUP_STATUS_SUCCESS,
    ':message'   => 'Backup created via backup.php',
    ':ip'        => $ip,
]);

$maxKeep = (int)($config['max_keep'] ?? 5);
if ($maxKeep > 0) {
    $stmt = $pdo->prepare("SELECT id, file_path FROM backup_logs WHERE status = ? ORDER BY created_at DESC");
    $stmt->execute([Constants::BACKUP_STATUS_SUCCESS]);
    $stmt->execute();
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (count($all) > $maxKeep) {
        $toDelete = array_slice($all, $maxKeep);
        foreach ($toDelete as $row) {
            $fid  = (int)$row['id'];
            $path = $row['file_path'];
            if ($path && file_exists($path)) {
                @unlink($path);
            }
            $pdo->prepare("DELETE FROM backup_logs WHERE id = :id")->execute([':id' => $fid]);
        }
    }
}

header('Content-Type: application/zip');
header('Content-Length: ' . $fileSize);
header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');

readfile($zipFile);
exit;
