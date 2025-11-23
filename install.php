<?php
/**
 * 安装入口文件
 * 
 * 检查是否已安装，如果未安装则重定向到安装向导
 */

// 检查是否已安装
function isInstalled(): bool
{
    // 检查安装锁定文件
    $lockFile = __DIR__ . '/.installed';
    if (file_exists($lockFile)) {
        return true;
    }

    // 尝试连接数据库并检查表是否存在
    try {
        $config = require __DIR__ . '/config/db.php';
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            $config['host'],
            $config['dbname'],
            $config['charset'] ?? 'utf8mb4'
        );

        $pdo = new PDO(
            $dsn,
            $config['user'],
            $config['pass'],
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_TIMEOUT => 3,
            ]
        );

        // 检查 admin_users 表是否存在
        $stmt = $pdo->query("SHOW TABLES LIKE 'admin_users'");
        return $stmt->rowCount() > 0;
    } catch (Exception $e) {
        return false;
    }
}

// 如果已安装，重定向到首页
if (isInstalled()) {
    header('Location: /');
    exit;
}

// 重定向到安装向导
header('Location: /install/');
exit;

