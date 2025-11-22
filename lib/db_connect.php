<?php
/**
 * 数据库连接文件
 * 
 * 使用 DatabaseConnection 类管理连接，支持连接池和持久连接
 */

// 加载错误处理类（如果尚未加载）
if (!class_exists('ErrorHandler')) {
    require_once __DIR__ . '/ErrorHandler.php';
}

// 加载数据库连接管理类
if (!class_exists('DatabaseConnection')) {
    require_once __DIR__ . '/DatabaseConnection.php';
}

// 获取数据库连接实例（单例模式，支持连接池）
$pdo = DatabaseConnection::getInstance();
