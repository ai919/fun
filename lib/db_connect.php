<?php
$config = require __DIR__ . '/../config/db.php';

$dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";

try {
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    // 记录详细错误到日志
    $logMessage = sprintf(
        '[db_connect.php] 数据库连接失败: host=%s, dbname=%s, error=%s',
        $config['host'],
        $config['dbname'],
        $e->getMessage()
    );
    $logDir = __DIR__ . '/../logs';
    $logFile = $logDir . '/db_error.log';
    if (is_dir($logDir) || @mkdir($logDir, 0755, true)) {
        @error_log($logMessage . PHP_EOL, 3, $logFile);
    } else {
        error_log($logMessage);
    }
    
    // 根据 DEBUG 模式决定是否显示详细错误
    $isDebug = defined('DEBUG') && DEBUG;
    if ($isDebug) {
        die('数据库连接失败：' . htmlspecialchars($e->getMessage()));
    } else {
        die('数据库连接失败，请稍后再试');
    }
}
