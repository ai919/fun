<?php
/**
 * 结构化日志记录器
 * 
 * 支持 JSON 格式的结构化日志，便于日志分析和处理
 * 支持日志轮转和归档
 */

class StructuredLogger
{
    private static $config = null;
    private static $logDir = null;
    private static $maxFileSize = 10 * 1024 * 1024; // 10MB
    private static $maxFiles = 10; // 保留最多 10 个文件

    /**
     * 初始化配置
     */
    private static function init()
    {
        if (self::$config !== null) {
            return;
        }

        // 防止在加载配置时触发循环引用
        static $loading = false;
        if ($loading) {
            // 如果正在加载配置，使用默认值
            self::$config = [];
            self::$logDir = __DIR__ . '/../logs';
            return;
        }

        $loading = true;
        try {
            $configFile = __DIR__ . '/../config/app.php';
            if (file_exists($configFile)) {
                self::$config = require $configFile;
            } else {
                self::$config = [];
            }

            self::$logDir = self::$config['log']['dir'] ?? __DIR__ . '/../logs';
            
            // 确保日志目录存在
            if (!is_dir(self::$logDir)) {
                @mkdir(self::$logDir, 0755, true);
            }

            // 从配置读取日志轮转设置
            self::$maxFileSize = self::$config['log']['max_file_size'] ?? self::$maxFileSize;
            self::$maxFiles = self::$config['log']['max_files'] ?? self::$maxFiles;
        } finally {
            $loading = false;
        }
    }

    /**
     * 记录日志
     * 
     * @param string $level 日志级别：DEBUG, INFO, WARNING, ERROR
     * @param string $message 日志消息
     * @param array $context 上下文信息
     */
    public static function log(string $level, string $message, array $context = [])
    {
        self::init();

        // 检查日志是否启用
        if (!(self::$config['log']['enabled'] ?? true)) {
            return;
        }

        // 检查日志级别
        $levels = ['DEBUG' => 0, 'INFO' => 1, 'WARNING' => 2, 'ERROR' => 3];
        $configLevel = self::$config['log']['level'] ?? 'INFO';
        $currentLevel = $levels[$level] ?? 1;
        $minLevel = $levels[$configLevel] ?? 1;

        if ($currentLevel < $minLevel) {
            return;
        }

        // 构建结构化日志数据
        $logData = [
            'timestamp' => date('c'), // ISO 8601 格式
            'level' => strtoupper($level),
            'message' => $message,
            'context' => $context,
            'server' => [
                'request_uri' => $_SERVER['REQUEST_URI'] ?? '',
                'request_method' => $_SERVER['REQUEST_METHOD'] ?? '',
                'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
            ],
        ];

        // 添加 PHP 错误信息（如果有）
        if (function_exists('error_get_last')) {
            $lastError = error_get_last();
            if ($lastError && $lastError['type'] === E_ERROR) {
                $logData['php_error'] = $lastError;
            }
        }

        // 转换为 JSON
        $jsonLog = json_encode($logData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . PHP_EOL;

        // 写入文件
        if (self::$config['log']['file'] ?? true) {
            self::writeToFile($jsonLog, $level);
        }

        // 写入系统日志（可选）
        if (self::$config['log']['system'] ?? true) {
            error_log(sprintf('[%s] %s', $level, $message));
        }
    }

    /**
     * 写入文件（带日志轮转）
     */
    private static function writeToFile(string $jsonLog, string $level)
    {
        $logFile = self::$logDir . '/' . strtolower($level) . '.json.log';
        
        // 检查文件大小，如果超过限制则轮转
        if (file_exists($logFile) && filesize($logFile) >= self::$maxFileSize) {
            self::rotateLog($logFile);
        }

        // 写入日志
        @file_put_contents($logFile, $jsonLog, FILE_APPEND | LOCK_EX);
    }

    /**
     * 日志轮转
     */
    private static function rotateLog(string $logFile)
    {
        // 归档当前日志文件
        $timestamp = date('Y-m-d_His');
        $archivedFile = $logFile . '.' . $timestamp;
        
        if (file_exists($logFile)) {
            @rename($logFile, $archivedFile);
        }

        // 清理旧日志文件（只保留最新的 N 个）
        $pattern = dirname($logFile) . '/' . basename($logFile) . '.*';
        $files = glob($pattern);
        
        if (count($files) > self::$maxFiles) {
            // 按修改时间排序，删除最旧的文件
            usort($files, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            
            $filesToDelete = array_slice($files, 0, count($files) - self::$maxFiles);
            foreach ($filesToDelete as $file) {
                @unlink($file);
            }
        }
    }

    /**
     * 便捷方法：记录 DEBUG 日志
     */
    public static function debug(string $message, array $context = [])
    {
        self::log('DEBUG', $message, $context);
    }

    /**
     * 便捷方法：记录 INFO 日志
     */
    public static function info(string $message, array $context = [])
    {
        self::log('INFO', $message, $context);
    }

    /**
     * 便捷方法：记录 WARNING 日志
     */
    public static function warning(string $message, array $context = [])
    {
        self::log('WARNING', $message, $context);
    }

    /**
     * 便捷方法：记录 ERROR 日志
     */
    public static function error(string $message, array $context = [])
    {
        self::log('ERROR', $message, $context);
    }

    /**
     * 记录性能日志
     */
    public static function performance(string $operation, float $duration, array $context = [])
    {
        $context['duration'] = $duration;
        $context['operation'] = $operation;
        self::log('INFO', "Performance: {$operation} took {$duration}s", $context);
    }

    /**
     * 记录数据库查询日志
     */
    public static function query(string $sql, float $duration, array $context = [])
    {
        $context['sql'] = $sql;
        $context['duration'] = $duration;
        $context['type'] = 'database_query';
        
        // 慢查询记录为 WARNING
        if ($duration > 1.0) {
            self::log('WARNING', "Slow query: {$sql}", $context);
        } else {
            self::log('DEBUG', "Query: {$sql}", $context);
        }
    }
}

