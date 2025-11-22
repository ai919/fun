<?php
/**
 * 配置管理类
 * 
 * 支持从配置文件和环境变量加载配置
 * 支持 .env 文件（如果存在）
 */
class Config
{
    private static $config = [];
    private static $loaded = false;

    /**
     * 初始化配置
     */
    public static function init()
    {
        if (self::$loaded) {
            return;
        }

        // 加载 .env 文件（如果存在）
        self::loadEnvFile();

        // 加载应用配置
        $appConfig = require __DIR__ . '/../config/app.php';
        self::$config['app'] = self::mergeEnvVars($appConfig, 'APP_');

        // 加载数据库配置
        $dbConfig = require __DIR__ . '/../config/db.php';
        self::$config['db'] = self::mergeEnvVars($dbConfig, 'DB_');

        self::$loaded = true;
    }

    /**
     * 加载 .env 文件
     */
    private static function loadEnvFile()
    {
        $envFile = __DIR__ . '/../.env';
        if (!file_exists($envFile)) {
            return;
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        foreach ($lines as $line) {
            // 跳过注释
            if (strpos(trim($line), '#') === 0) {
                continue;
            }

            // 解析 KEY=VALUE
            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value);

                // 移除引号
                if ((substr($value, 0, 1) === '"' && substr($value, -1) === '"') ||
                    (substr($value, 0, 1) === "'" && substr($value, -1) === "'")) {
                    $value = substr($value, 1, -1);
                }

                // 设置环境变量（如果尚未设置）
                if (!isset($_ENV[$key]) && !isset($_SERVER[$key])) {
                    $_ENV[$key] = $value;
                    $_SERVER[$key] = $value;
                }
            }
        }
    }

    /**
     * 合并环境变量到配置数组
     * 
     * @param array $config 配置数组
     * @param string $prefix 环境变量前缀
     * @return array
     */
    private static function mergeEnvVars(array $config, string $prefix): array
    {
        foreach ($config as $key => $value) {
            $envKey = $prefix . strtoupper($key);
            $envValue = self::getEnv($envKey);

            if ($envValue !== null) {
                // 转换类型
                if (is_bool($value)) {
                    $config[$key] = in_array(strtolower($envValue), ['true', '1', 'yes', 'on']);
                } elseif (is_int($value)) {
                    $config[$key] = (int)$envValue;
                } elseif (is_float($value)) {
                    $config[$key] = (float)$envValue;
                } else {
                    $config[$key] = $envValue;
                }
            }
        }

        return $config;
    }

    /**
     * 获取环境变量
     * 
     * @param string $key 环境变量名
     * @param mixed $default 默认值
     * @return mixed
     */
    private static function getEnv(string $key, $default = null)
    {
        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }
        if (isset($_SERVER[$key])) {
            return $_SERVER[$key];
        }
        return $default;
    }

    /**
     * 获取配置值
     * 
     * @param string $key 配置键，支持点号分隔（如 'app.debug'）
     * @param mixed $default 默认值
     * @return mixed
     */
    public static function get(string $key, $default = null)
    {
        self::init();

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return $default;
            }
            $value = $value[$k];
        }

        return $value;
    }

    /**
     * 设置配置值（仅在运行时有效）
     * 
     * @param string $key 配置键
     * @param mixed $value 配置值
     */
    public static function set(string $key, $value)
    {
        self::init();

        $keys = explode('.', $key);
        $config = &self::$config;

        foreach ($keys as $k) {
            if (!isset($config[$k]) || !is_array($config[$k])) {
                $config[$k] = [];
            }
            $config = &$config[$k];
        }

        $config = $value;
    }

    /**
     * 获取所有配置
     * 
     * @return array
     */
    public static function all(): array
    {
        self::init();
        return self::$config;
    }

    /**
     * 检查配置是否存在
     * 
     * @param string $key 配置键
     * @return bool
     */
    public static function has(string $key): bool
    {
        self::init();

        $keys = explode('.', $key);
        $value = self::$config;

        foreach ($keys as $k) {
            if (!isset($value[$k])) {
                return false;
            }
            $value = $value[$k];
        }

        return true;
    }
}

