# 错误处理类使用指南

本文档介绍如何使用统一的错误处理类 `ErrorHandler`。

## 快速开始

### 1. 自动加载

`ErrorHandler` 会在首次使用时自动初始化，无需手动调用初始化方法。

### 2. 基本使用

#### 处理异常

```php
<?php
require_once __DIR__ . '/lib/ErrorHandler.php';
require_once __DIR__ . '/lib/db_connect.php';

try {
    // 你的代码
    $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ?");
    $stmt->execute([$testId]);
} catch (PDOException $e) {
    // 自动记录日志并渲染错误页面
    ErrorHandler::handleException($e, '查询测验失败');
}
```

#### 记录日志

```php
// 记录错误
ErrorHandler::logError('操作失败', ['userId' => 123, 'action' => 'update']);

// 记录警告
ErrorHandler::logWarning('配置不完整', ['testId' => 456]);

// 记录信息
ErrorHandler::logInfo('用户登录', ['userId' => 123]);

// 记录调试信息（仅在 DEBUG 模式下记录）
ErrorHandler::logDebug('变量值', ['var' => $value]);
```

#### 渲染错误页面

```php
// HTML 错误页面
ErrorHandler::renderError(404, '页面未找到');

// JSON API 错误响应
ErrorHandler::renderError(400, '参数错误', true);
```

## 配置

### 应用配置 (`config/app.php`)

```php
return [
    // 调试模式
    'debug' => false,  // 生产环境设为 false
    
    // 环境
    'environment' => 'production',  // 'development' | 'production' | 'testing'
    
    // 日志配置
    'log' => [
        'dir' => __DIR__ . '/../logs',  // 日志目录
        'enabled' => true,               // 是否启用日志
        'level' => 'INFO',              // 日志级别：'DEBUG' | 'INFO' | 'WARNING' | 'ERROR'
        'file' => true,                 // 是否记录到文件
        'system' => true,               // 是否记录到系统日志
    ],
    
    // 错误处理配置
    'error' => [
        'display_details' => true,      // 是否显示详细错误（仅在 debug 模式下）
        'log_stack_trace' => true,      // 是否记录堆栈跟踪
        'error_page' => null,           // 自定义错误页面路径（可选）
    ],
];
```

## API 参考

### ErrorHandler::handleException()

处理异常，自动记录日志并渲染错误响应。

```php
ErrorHandler::handleException(Throwable $exception, string $context = '', bool $renderResponse = true)
```

**参数**:
- `$exception`: 异常对象
- `$context`: 上下文信息（如操作名称）
- `$renderResponse`: 是否渲染错误响应（默认 true）

**示例**:
```php
try {
    // 代码
} catch (Exception $e) {
    ErrorHandler::handleException($e, '保存测验失败');
}
```

### ErrorHandler::logError()

记录错误日志。

```php
ErrorHandler::logError(string $message, array $context = [], string $level = 'INFO')
```

**参数**:
- `$message`: 日志消息
- `$context`: 上下文信息（键值对数组）
- `$level`: 日志级别（'DEBUG' | 'INFO' | 'WARNING' | 'ERROR'）

**示例**:
```php
ErrorHandler::logError('数据库查询失败', [
    'query' => $sql,
    'params' => $params,
], 'ERROR');
```

### ErrorHandler::renderError()

渲染错误响应。

```php
ErrorHandler::renderError(int $code, string $message, bool $isApi = false)
```

**参数**:
- `$code`: HTTP 状态码（400, 403, 404, 500 等）
- `$message`: 错误消息
- `$isApi`: 是否为 API 响应（默认 false，返回 HTML）

**示例**:
```php
// HTML 响应
ErrorHandler::renderError(404, '页面未找到');

// JSON API 响应
ErrorHandler::renderError(400, '参数错误', true);
// 输出: {"success":false,"error":{"code":400,"message":"参数错误"}}
```

### ErrorHandler::logDebug() / logInfo() / logWarning()

便捷方法，用于记录不同级别的日志。

```php
ErrorHandler::logDebug(string $message, array $context = []);
ErrorHandler::logInfo(string $message, array $context = []);
ErrorHandler::logWarning(string $message, array $context = []);
```

## 日志文件

日志文件按级别分别存储：

- `logs/error.log` - 错误日志
- `logs/warning.log` - 警告日志
- `logs/info.log` - 信息日志
- `logs/debug.log` - 调试日志（仅在 DEBUG 模式下）

日志格式：
```
[2024-12-19 10:30:45] [ERROR] 数据库连接失败 [context=数据库连接, file=/path/to/file.php, line=10]
```

## 迁移指南

### 从旧代码迁移

#### 旧代码（使用 die()）

```php
if (!$test) {
    http_response_code(404);
    die('测验不存在');
}
```

#### 新代码

```php
if (!$test) {
    ErrorHandler::renderError(404, '测验不存在');
}
```

#### 旧代码（手动记录日志）

```php
catch (Exception $e) {
    $logMessage = sprintf('[submit.php] 错误: %s', $e->getMessage());
    $logDir = __DIR__ . '/logs';
    $logFile = $logDir . '/error.log';
    if (is_dir($logDir) || @mkdir($logDir, 0755, true)) {
        @error_log($logMessage . PHP_EOL, 3, $logFile);
    } else {
        error_log($logMessage);
    }
    http_response_code(500);
    die('操作失败');
}
```

#### 新代码

```php
catch (Exception $e) {
    ErrorHandler::handleException($e, '提交测验答案失败');
}
```

## 最佳实践

1. **在文件开头引入 ErrorHandler**
   ```php
   require_once __DIR__ . '/lib/ErrorHandler.php';
   ```

2. **使用有意义的上下文信息**
   ```php
   ErrorHandler::handleException($e, '保存测验失败: testId=' . $testId);
   ```

3. **在 API 接口中使用 JSON 响应**
   ```php
   ErrorHandler::renderError(400, '参数错误', true);
   ```

4. **记录足够的上下文信息**
   ```php
   ErrorHandler::logError('操作失败', [
       'userId' => $userId,
       'action' => 'update',
       'resource' => 'test',
       'resourceId' => $testId,
   ]);
   ```

5. **生产环境关闭 DEBUG 模式**
   ```php
   // config/app.php
   'debug' => false,
   'environment' => 'production',
   ```

## 错误页面自定义

如果需要自定义错误页面，可以在 `config/app.php` 中设置：

```php
'error' => [
    'error_page' => __DIR__ . '/../templates/error.php',
],
```

自定义错误页面会接收以下变量：
- `$code`: HTTP 状态码
- `$message`: 错误消息
- `$isDebug`: 是否为调试模式

示例 `templates/error.php`:
```php
<!DOCTYPE html>
<html>
<head>
    <title>错误 <?php echo $code; ?></title>
</head>
<body>
    <h1>错误 <?php echo $code; ?></h1>
    <p><?php echo htmlspecialchars($message); ?></p>
    <?php if ($isDebug): ?>
        <pre><?php print_r(debug_backtrace()); ?></pre>
    <?php endif; ?>
</body>
</html>
```

