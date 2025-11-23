<?php
/**
 * 网站设置辅助类
 * 
 * 用于管理网站设置，如 Google Analytics 等
 */
class SettingsHelper
{
    private static ?PDO $pdo = null;
    private static array $cache = [];

    /**
     * 初始化数据库连接
     */
    private static function initPdo(): void
    {
        if (self::$pdo === null) {
            require_once __DIR__ . '/db_connect.php';
            global $pdo;
            self::$pdo = $pdo;
        }
    }

    /**
     * 获取设置值
     * 
     * @param string $key 设置键名
     * @param mixed $default 默认值
     * @return mixed 设置值
     */
    public static function get(string $key, mixed $default = null): mixed
    {
        // 检查缓存
        if (isset(self::$cache[$key])) {
            return self::$cache[$key];
        }

        self::initPdo();
        
        try {
            $stmt = self::$pdo->prepare("SELECT value FROM settings WHERE key_name = ? LIMIT 1");
            $stmt->execute([$key]);
            $result = $stmt->fetchColumn();
            
            if ($result === false) {
                self::$cache[$key] = $default;
                return $default;
            }
            
            self::$cache[$key] = $result;
            return $result;
        } catch (PDOException $e) {
            // 如果表不存在，返回默认值
            return $default;
        }
    }

    /**
     * 设置值
     * 
     * @param string $key 设置键名
     * @param mixed $value 设置值
     * @param string|null $description 设置描述
     * @return bool 是否成功
     */
    public static function set(string $key, mixed $value, ?string $description = null): bool
    {
        self::initPdo();
        
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO settings (key_name, value, description) 
                VALUES (?, ?, ?)
                ON DUPLICATE KEY UPDATE 
                    value = VALUES(value),
                    description = COALESCE(VALUES(description), description),
                    updated_at = CURRENT_TIMESTAMP
            ");
            
            $result = $stmt->execute([$key, $value, $description]);
            
            // 清除缓存
            unset(self::$cache[$key]);
            
            return $result;
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * 获取 Google Analytics 是否启用
     * 
     * @return bool
     */
    public static function isGoogleAnalyticsEnabled(): bool
    {
        return (bool)self::get('google_analytics_enabled', '0');
    }

    /**
     * 获取 Google Analytics 代码
     * 
     * @return string
     */
    public static function getGoogleAnalyticsCode(): string
    {
        return (string)self::get('google_analytics_code', '');
    }

    /**
     * 渲染 Google Analytics 代码
     * 如果启用且有代码，则输出到页面
     * 
     * @return void
     */
    public static function renderGoogleAnalytics(): void
    {
        if (!self::isGoogleAnalyticsEnabled()) {
            return;
        }

        $code = trim(self::getGoogleAnalyticsCode());
        if (empty($code)) {
            return;
        }

        // 如果代码是 GA4 测量 ID（格式：G-XXXXXXXXXX），转换为完整脚本
        if (preg_match('/^G-[A-Z0-9]+$/i', $code)) {
            echo "<!-- Google Analytics (GA4) -->\n";
            echo "<script async src=\"https://www.googletagmanager.com/gtag/js?id=" . htmlspecialchars($code) . "\"></script>\n";
            echo "<script>\n";
            echo "  window.dataLayer = window.dataLayer || [];\n";
            echo "  function gtag(){dataLayer.push(arguments);}\n";
            echo "  gtag('js', new Date());\n";
            echo "  gtag('config', '" . htmlspecialchars($code) . "');\n";
            echo "</script>\n";
        } else {
            // 如果是完整的脚本代码，直接输出
            echo "<!-- Google Analytics -->\n";
            echo $code . "\n";
        }
    }

    /**
     * 清除所有缓存
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}

