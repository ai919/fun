<?php
/**
 * 统一错误处理类
 * 
 * 提供统一的错误处理、日志记录和错误响应功能
 * 
 * 使用示例：
 *   // 处理异常
 *   try {
 *       // some code
 *   } catch (Exception $e) {
 *       ErrorHandler::handleException($e, '操作名称');
 *   }
 * 
 *   // 记录错误
 *   ErrorHandler::logError('错误消息', ['context' => 'value']);
 * 
 *   // 渲染错误页面
 *   ErrorHandler::renderError(500, '服务器错误');
 */
class ErrorHandler
{
    private static $config = null;
    private static $initialized = false;

    /**
     * 初始化错误处理
     */
    private static function init()
    {
        if (self::$initialized) {
            return;
        }

        // 加载配置
        $configPath = __DIR__ . '/../config/app.php';
        if (file_exists($configPath)) {
            self::$config = require $configPath;
        } else {
            // 默认配置
            self::$config = [
                'debug' => false,
                'environment' => 'production',
                'log' => [
                    'dir' => __DIR__ . '/../logs',
                    'enabled' => true,
                    'level' => 'INFO',
                    'file' => true,
                    'system' => true,
                ],
                'error' => [
                    'display_details' => true,
                    'log_stack_trace' => true,
                ],
            ];
        }

        // 设置时区
        if (isset(self::$config['timezone'])) {
            date_default_timezone_set(self::$config['timezone']);
        }

        // 设置错误报告级别
        if (self::isDebug()) {
            error_reporting(E_ALL);
            ini_set('display_errors', '1');
        } else {
            error_reporting(E_ALL & ~E_DEPRECATED & ~E_STRICT);
            ini_set('display_errors', '0');
        }

        // 注册全局异常处理器
        set_exception_handler([self::class, 'handleException']);
        
        // 注册全局错误处理器
        set_error_handler([self::class, 'handleError']);

        self::$initialized = true;
    }

    /**
     * 检查是否为调试模式
     */
    public static function isDebug(): bool
    {
        self::init();
        return self::$config['debug'] ?? false;
    }

    /**
     * 获取环境
     */
    public static function getEnvironment(): string
    {
        self::init();
        return self::$config['environment'] ?? 'production';
    }

    /**
     * 处理异常
     * 
     * @param Throwable $exception 异常对象
     * @param string $context 上下文信息（如操作名称）
     * @param bool $renderResponse 是否渲染错误响应（默认 true）
     */
    public static function handleException(Throwable $exception, string $context = '', bool $renderResponse = true)
    {
        self::init();

        // 记录错误日志
        $logContext = [
            'context' => $context,
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => self::$config['error']['log_stack_trace'] ? $exception->getTraceAsString() : null,
        ];

        self::logError(
            $exception->getMessage(),
            $logContext,
            'ERROR'
        );

        // 渲染错误响应
        if ($renderResponse) {
            $code = method_exists($exception, 'getCode') && $exception->getCode() > 0 
                ? $exception->getCode() 
                : 500;
            
            $message = self::isDebug() 
                ? $exception->getMessage() 
                : '服务器内部错误，请稍后重试';

            self::renderError($code, $message, false);
        }
    }

    /**
     * 处理 PHP 错误
     * 
     * @param int $errno 错误级别
     * @param string $errstr 错误消息
     * @param string $errfile 错误文件
     * @param int $errline 错误行号
     * @return bool 是否处理了错误
     */
    public static function handleError(int $errno, string $errstr, string $errfile = '', int $errline = 0): bool
    {
        self::init();

        // 只处理需要记录的错误
        if (!(error_reporting() & $errno)) {
            return false;
        }

        $errorTypes = [
            E_ERROR => 'ERROR',
            E_WARNING => 'WARNING',
            E_PARSE => 'ERROR',
            E_NOTICE => 'INFO',
            E_CORE_ERROR => 'ERROR',
            E_CORE_WARNING => 'WARNING',
            E_COMPILE_ERROR => 'ERROR',
            E_COMPILE_WARNING => 'WARNING',
            E_USER_ERROR => 'ERROR',
            E_USER_WARNING => 'WARNING',
            E_USER_NOTICE => 'INFO',
            E_STRICT => 'INFO',
            E_RECOVERABLE_ERROR => 'ERROR',
            E_DEPRECATED => 'WARNING',
            E_USER_DEPRECATED => 'WARNING',
        ];

        $level = $errorTypes[$errno] ?? 'WARNING';

        // 记录错误
        self::logError(
            $errstr,
            [
                'file' => $errfile,
                'line' => $errline,
                'errno' => $errno,
            ],
            $level
        );

        // 如果是致命错误，渲染错误页面
        if (in_array($errno, [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR, E_USER_ERROR])) {
            $message = self::isDebug() 
                ? $errstr 
                : '服务器错误，请稍后重试';
            
            self::renderError(500, $message, false);
        }

        return true;
    }

    /**
     * 记录日志
     * 
     * @param string $message 日志消息
     * @param array $context 上下文信息
     * @param string $level 日志级别：'DEBUG' | 'INFO' | 'WARNING' | 'ERROR'
     */
    public static function logError(string $message, array $context = [], string $level = 'INFO')
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

        // 构建日志消息
        $logMessage = self::formatLogMessage($message, $context, $level);

        // 写入文件日志
        if (self::$config['log']['file'] ?? true) {
            self::writeToFile($logMessage, $level);
        }

        // 写入系统日志
        if (self::$config['log']['system'] ?? true) {
            error_log($logMessage);
        }
    }

    /**
     * 格式化日志消息
     */
    private static function formatLogMessage(string $message, array $context, string $level): string
    {
        $timestamp = date('Y-m-d H:i:s');
        $contextStr = '';

        if (!empty($context)) {
            $contextParts = [];
            foreach ($context as $key => $value) {
                if ($key === 'trace' && is_string($value)) {
                    // 堆栈跟踪单独处理
                    $contextParts[] = "trace=\n" . $value;
                } else {
                    $contextParts[] = $key . '=' . (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE));
                }
            }
            $contextStr = ' [' . implode(', ', $contextParts) . ']';
        }

        return sprintf(
            '[%s] [%s] %s%s',
            $timestamp,
            $level,
            $message,
            $contextStr
        );
    }

    /**
     * 写入文件日志
     */
    private static function writeToFile(string $message, string $level)
    {
        $logDir = self::$config['log']['dir'] ?? __DIR__ . '/../logs';
        
        // 确保日志目录存在
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0755, true);
        }

        // 根据级别选择日志文件
        $logFile = $logDir . '/' . strtolower($level) . '.log';
        
        // 尝试写入文件
        @error_log($message . PHP_EOL, 3, $logFile);
    }

    /**
     * 渲染错误响应
     * 
     * @param int $code HTTP 状态码
     * @param string $message 错误消息
     * @param bool $isApi 是否为 API 响应（默认 false，返回 HTML）
     */
    public static function renderError(int $code, string $message, bool $isApi = false)
    {
        self::init();

        // 设置 HTTP 状态码
        http_response_code($code);

        if ($isApi) {
            // JSON API 响应
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'success' => false,
                'error' => [
                    'code' => $code,
                    'message' => $message,
                ],
            ], JSON_UNESCAPED_UNICODE);
        } else {
            // HTML 响应
            header('Content-Type: text/html; charset=utf-8');
            
            // 检查是否有自定义错误页面
            $errorPage = self::$config['error']['error_page'] ?? null;
            if ($errorPage && file_exists($errorPage)) {
                include $errorPage;
            } else {
                // 默认错误页面
                self::renderDefaultErrorPage($code, $message);
            }
        }

        exit;
    }

    /**
     * 渲染默认错误页面
     */
    private static function renderDefaultErrorPage(int $code, string $message)
    {
        $isDebug = self::isDebug();
        $errorTitles = [
            400 => '请求错误',
            401 => '未授权',
            403 => '禁止访问',
            404 => '页面未找到',
            500 => '服务器错误',
            503 => '服务不可用',
        ];

        $title = $errorTitles[$code] ?? '错误';
        ?>
        <!DOCTYPE html>
        <html lang="zh-CN">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title><?php echo htmlspecialchars($title); ?></title>
            <style>
                * {
                    margin: 0;
                    padding: 0;
                    box-sizing: border-box;
                }
                body {
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    min-height: 100vh;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    padding: 20px;
                }
                .error-container {
                    background: white;
                    border-radius: 10px;
                    box-shadow: 0 10px 40px rgba(0,0,0,0.2);
                    padding: 40px;
                    max-width: 500px;
                    width: 100%;
                    text-align: center;
                }
                .error-code {
                    font-size: 72px;
                    font-weight: bold;
                    color: #667eea;
                    margin-bottom: 20px;
                }
                .error-title {
                    font-size: 24px;
                    color: #333;
                    margin-bottom: 20px;
                }
                .error-message {
                    font-size: 16px;
                    color: #666;
                    line-height: 1.6;
                    margin-bottom: 30px;
                }
                .error-message.debug {
                    background: #f5f5f5;
                    padding: 15px;
                    border-radius: 5px;
                    text-align: left;
                    font-family: monospace;
                    font-size: 14px;
                    color: #d32f2f;
                }
                .back-link {
                    display: inline-block;
                    padding: 12px 24px;
                    background: #667eea;
                    color: white;
                    text-decoration: none;
                    border-radius: 5px;
                    transition: background 0.3s;
                }
                .back-link:hover {
                    background: #5568d3;
                }
            </style>
        </head>
        <body>
            <div class="error-container">
                <div class="error-code"><?php echo $code; ?></div>
                <div class="error-title"><?php echo htmlspecialchars($title); ?></div>
                <div class="error-message <?php echo $isDebug ? 'debug' : ''; ?>">
                    <?php echo $isDebug ? nl2br(htmlspecialchars($message)) : htmlspecialchars($message); ?>
                </div>
                <a href="/" class="back-link">返回首页</a>
            </div>
        </body>
        </html>
        <?php
    }

    /**
     * 记录调试信息
     */
    public static function logDebug(string $message, array $context = [])
    {
        self::logError($message, $context, 'DEBUG');
    }

    /**
     * 记录信息
     */
    public static function logInfo(string $message, array $context = [])
    {
        self::logError($message, $context, 'INFO');
    }

    /**
     * 记录警告
     */
    public static function logWarning(string $message, array $context = [])
    {
        self::logError($message, $context, 'WARNING');
    }
}

