<?php
/**
 * 数据库配置文件
 * 
 * 支持从环境变量读取配置（DB_HOST, DB_DATABASE, DB_USERNAME, DB_PASSWORD 等）
 */
return [
    'host'      => $_ENV['DB_HOST'] ?? $_SERVER['DB_HOST'] ?? '127.0.0.1',
    'dbname'    => $_ENV['DB_DATABASE'] ?? $_SERVER['DB_DATABASE'] ?? 'fun_quiz',
    'user'      => $_ENV['DB_USERNAME'] ?? $_SERVER['DB_USERNAME'] ?? 'root',
    'pass'      => $_ENV['DB_PASSWORD'] ?? $_SERVER['DB_PASSWORD'] ?? '',
    'charset'   => $_ENV['DB_CHARSET'] ?? $_SERVER['DB_CHARSET'] ?? 'utf8mb4',
    
    // 持久连接：设置为 true 可重用连接，减少连接开销（适合高并发场景）
    // 注意：持久连接在某些场景下可能导致连接数过多，请根据实际情况调整
    'persistent' => filter_var(
        $_ENV['DB_PERSISTENT'] ?? $_SERVER['DB_PERSISTENT'] ?? false,
        FILTER_VALIDATE_BOOLEAN
    ),
    
    // 连接超时（秒）
    'timeout'   => (int)($_ENV['DB_TIMEOUT'] ?? $_SERVER['DB_TIMEOUT'] ?? 5),
    
    // 时区设置（可选）
    'timezone'  => $_ENV['DB_TIMEZONE'] ?? $_SERVER['DB_TIMEZONE'] ?? null,
];
