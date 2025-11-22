<?php
/**
 * 数据库连接管理类
 * 
 * 实现单例模式和连接池，支持持久连接
 */
class DatabaseConnection
{
    private static $instance = null;
    private static $pdo = null;
    private static $config = null;

    /**
     * 私有构造函数，防止直接实例化
     */
    private function __construct()
    {
    }

    /**
     * 获取数据库连接实例（单例模式）
     * 
     * @return PDO
     */
    public static function getInstance(): PDO
    {
        if (self::$pdo === null) {
            self::connect();
        }

        return self::$pdo;
    }

    /**
     * 建立数据库连接
     * 
     * @param array|null $config 数据库配置（可选，如果不提供则从配置文件读取）
     * @return PDO
     */
    public static function connect(?array $config = null): PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        // 加载配置
        if ($config === null) {
            if (self::$config === null) {
                self::$config = require __DIR__ . '/../config/db.php';
            }
            $config = self::$config;
        } else {
            self::$config = $config;
        }

        // 加载错误处理类
        if (!class_exists('ErrorHandler')) {
            require_once __DIR__ . '/ErrorHandler.php';
        }

        // 构建 DSN
        $dsn = sprintf(
            "mysql:host=%s;dbname=%s;charset=%s",
            $config['host'],
            $config['dbname'],
            $config['charset'] ?? 'utf8mb4'
        );

        // PDO 选项
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false, // 使用原生预处理语句
            PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . ($config['charset'] ?? 'utf8mb4'),
        ];

        // 如果配置了持久连接，启用它
        if (isset($config['persistent']) && $config['persistent']) {
            $options[PDO::ATTR_PERSISTENT] = true;
        }

        // 连接超时设置
        if (isset($config['timeout'])) {
            $options[PDO::ATTR_TIMEOUT] = $config['timeout'];
        }

        try {
            self::$pdo = new PDO(
                $dsn,
                $config['user'],
                $config['pass'],
                $options
            );

            // 设置时区（如果配置了）
            if (isset($config['timezone'])) {
                self::$pdo->exec("SET time_zone = '{$config['timezone']}'");
            }
        } catch (PDOException $e) {
            ErrorHandler::handleException(
                $e,
                sprintf('数据库连接失败: host=%s, dbname=%s', $config['host'], $config['dbname'])
            );
        }

        return self::$pdo;
    }

    /**
     * 关闭数据库连接
     */
    public static function disconnect(): void
    {
        self::$pdo = null;
    }

    /**
     * 检查连接是否有效
     * 
     * @return bool
     */
    public static function isConnected(): bool
    {
        if (self::$pdo === null) {
            return false;
        }

        try {
            self::$pdo->query('SELECT 1');
            return true;
        } catch (PDOException $e) {
            // 连接已断开，重置
            self::$pdo = null;
            return false;
        }
    }

    /**
     * 重新连接数据库
     * 
     * @return PDO
     */
    public static function reconnect(): PDO
    {
        self::disconnect();
        return self::connect();
    }

    /**
     * 获取连接统计信息
     * 
     * @return array
     */
    public static function getStats(): array
    {
        if (self::$pdo === null) {
            return [
                'connected' => false,
                'persistent' => false,
            ];
        }

        $attributes = self::$pdo->getAttribute(PDO::ATTR_SERVER_INFO);
        $persistent = self::$pdo->getAttribute(PDO::ATTR_PERSISTENT);

        return [
            'connected' => true,
            'persistent' => (bool)$persistent,
            'server_info' => $attributes,
        ];
    }
}

