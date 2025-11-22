# 配置管理使用文档

## 概述

`Config` 类提供了统一的配置管理，支持从配置文件和环境变量加载配置，支持 `.env` 文件。

## 基本用法

### 获取配置值

```php
require_once __DIR__ . '/lib/Config.php';

// 获取配置（支持点号分隔）
$debug = Config::get('app.debug');
$dbHost = Config::get('db.host');
$logLevel = Config::get('app.log.level', 'INFO'); // 带默认值
```

### 设置配置值（运行时）

```php
// 设置配置（仅在当前请求有效）
Config::set('app.debug', true);
Config::set('app.log.level', 'DEBUG');
```

### 检查配置是否存在

```php
if (Config::has('app.debug')) {
    // 配置存在
}
```

### 获取所有配置

```php
$allConfig = Config::all();
```

## 环境变量支持

### 从环境变量读取配置

配置类会自动从环境变量读取配置，环境变量命名规则：

- 应用配置：`APP_*`（如 `APP_DEBUG`, `APP_ENV`）
- 数据库配置：`DB_*`（如 `DB_HOST`, `DB_DATABASE`）

### 使用 .env 文件

1. 复制 `.env.example` 为 `.env`
2. 修改 `.env` 文件中的配置

```env
# 应用环境
APP_ENV=production
APP_DEBUG=false

# 数据库配置
DB_HOST=127.0.0.1
DB_DATABASE=fun_quiz
DB_USERNAME=root
DB_PASSWORD=your_password
DB_PERSISTENT=true
```

### 环境变量优先级

1. 系统环境变量（`$_ENV`, `$_SERVER`）
2. `.env` 文件
3. 配置文件默认值

## 配置示例

### 应用配置（config/app.php）

```php
return [
    'debug' => false,
    'environment' => 'production',
    'log' => [
        'dir' => __DIR__ . '/../logs',
        'enabled' => true,
        'level' => 'INFO',
    ],
];
```

可以通过环境变量覆盖：

```bash
export APP_DEBUG=true
export APP_ENV=development
export APP_LOG_LEVEL=DEBUG
```

### 数据库配置（config/db.php）

```php
return [
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'dbname' => $_ENV['DB_DATABASE'] ?? 'fun_quiz',
    'user' => $_ENV['DB_USERNAME'] ?? 'root',
    'pass' => $_ENV['DB_PASSWORD'] ?? '',
    'persistent' => filter_var($_ENV['DB_PERSISTENT'] ?? false, FILTER_VALIDATE_BOOLEAN),
];
```

## 使用示例

### 在代码中使用配置

```php
<?php
require_once __DIR__ . '/lib/Config.php';

// 检查是否为调试模式
if (Config::get('app.debug')) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
}

// 获取日志级别
$logLevel = Config::get('app.log.level', 'INFO');

// 获取数据库配置
$dbHost = Config::get('db.host');
$dbName = Config::get('db.database');
```

### 根据环境切换配置

```php
$env = Config::get('app.environment', 'production');

if ($env === 'development') {
    // 开发环境配置
    Config::set('app.debug', true);
    Config::set('app.log.level', 'DEBUG');
} elseif ($env === 'production') {
    // 生产环境配置
    Config::set('app.debug', false);
    Config::set('app.log.level', 'WARNING');
}
```

## 注意事项

1. `.env` 文件不应提交到版本控制系统，应添加到 `.gitignore`
2. 敏感信息（如密码、API 密钥）应使用环境变量，不要写在配置文件中
3. 配置值会在首次访问时加载，后续访问会使用缓存的配置
4. 使用 `Config::set()` 设置的配置仅在当前请求有效，不会持久化

