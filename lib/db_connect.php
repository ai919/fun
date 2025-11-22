<?php
// 加载错误处理类（如果尚未加载）
if (!class_exists('ErrorHandler')) {
    require_once __DIR__ . '/ErrorHandler.php';
}

$config = require __DIR__ . '/../config/db.php';

$dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";

try {
    $pdo = new PDO($dsn, $config['user'], $config['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
} catch (PDOException $e) {
    // 使用统一的错误处理
    ErrorHandler::handleException(
        $e,
        sprintf('数据库连接失败: host=%s, dbname=%s', $config['host'], $config['dbname'])
    );
}
