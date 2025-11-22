# 数据库迁移系统使用文档

## 概述

`Migration` 类提供了数据库结构变更的版本管理，支持迁移执行、回滚和状态查看。

## 迁移文件命名

迁移文件必须遵循以下命名格式：

```
YYYY_MM_DD_HHMMSS_migration_name.php
```

例如：`2024_12_19_143000_add_user_table.php`

## 创建迁移

### 通过后台管理界面

访问后台的"数据库迁移"页面，输入迁移名称，点击"创建迁移文件"。

### 通过代码创建

```php
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/Migration.php';

$migration = new Migration($pdo);
$filepath = $migration->create('add_user_table');
echo "迁移文件已创建: $filepath";
```

## 迁移文件结构

```php
<?php
class AddUserTable
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 执行迁移
     */
    public function up()
    {
        $this->pdo->exec("
            CREATE TABLE users (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                name VARCHAR(255) NOT NULL,
                email VARCHAR(255) NOT NULL UNIQUE,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4
        ");
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        $this->pdo->exec("DROP TABLE IF EXISTS users");
    }
}
```

## 执行迁移

### 通过后台管理界面

1. 访问"数据库迁移"页面
2. 点击"执行所有待迁移"按钮
3. 系统会自动执行所有未执行的迁移

### 通过代码执行

```php
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/Migration.php';

$migration = new Migration($pdo);

// 执行所有待迁移
$results = $migration->migrate();

// 预览模式（不实际执行）
$results = $migration->migrate(true);

// 检查结果
if (!empty($results['failed'])) {
    echo "迁移失败: " . $results['failed'][0]['error'];
} else {
    echo "成功执行 " . count($results['executed']) . " 个迁移";
}
```

## 回滚迁移

### 回滚最后一个批次

```php
$results = $migration->rollback();

// 预览模式
$results = $migration->rollback(null, true);
```

### 回滚指定批次

```php
// 回滚最后 2 个批次
$results = $migration->rollback(2);
```

## 查看迁移状态

```php
$status = $migration->status();

foreach ($status as $item) {
    echo "{$item['migration']}: {$item['status']}\n";
}
```

## 迁移最佳实践

### 1. 迁移应该是可逆的

每个迁移都应该实现 `down()` 方法，以便可以回滚。

```php
public function up()
{
    $this->pdo->exec("ALTER TABLE users ADD COLUMN phone VARCHAR(20)");
}

public function down()
{
    $this->pdo->exec("ALTER TABLE users DROP COLUMN phone");
}
```

### 2. 使用事务（如果可能）

```php
public function up()
{
    $this->pdo->beginTransaction();
    try {
        $this->pdo->exec("CREATE TABLE ...");
        $this->pdo->exec("INSERT INTO ...");
        $this->pdo->commit();
    } catch (Exception $e) {
        $this->pdo->rollBack();
        throw $e;
    }
}
```

### 3. 避免在生产环境直接修改数据

迁移应该只用于结构变更，数据迁移应该使用单独的脚本。

### 4. 测试迁移和回滚

在执行迁移前，先在测试环境验证迁移和回滚是否正常工作。

## 迁移示例

### 创建表

```php
public function up()
{
    $this->pdo->exec("
        CREATE TABLE tests (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            slug VARCHAR(255) NOT NULL UNIQUE,
            status TINYINT NOT NULL DEFAULT 0,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_slug (slug)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
}

public function down()
{
    $this->pdo->exec("DROP TABLE IF EXISTS tests");
}
```

### 添加字段

```php
public function up()
{
    $this->pdo->exec("ALTER TABLE users ADD COLUMN avatar VARCHAR(255) NULL AFTER email");
}

public function down()
{
    $this->pdo->exec("ALTER TABLE users DROP COLUMN avatar");
}
```

### 添加索引

```php
public function up()
{
    $this->pdo->exec("CREATE INDEX idx_email ON users(email)");
}

public function down()
{
    $this->pdo->exec("DROP INDEX idx_email ON users");
}
```

### 修改字段

```php
public function up()
{
    $this->pdo->exec("ALTER TABLE users MODIFY COLUMN name VARCHAR(100) NOT NULL");
}

public function down()
{
    $this->pdo->exec("ALTER TABLE users MODIFY COLUMN name VARCHAR(50) NOT NULL");
}
```

## 注意事项

1. 迁移文件一旦执行，不应再修改
2. 如果需要修改已执行的迁移，应该创建新的迁移文件
3. 迁移按时间戳顺序执行
4. 如果迁移执行失败，后续迁移不会执行
5. 回滚操作会删除迁移记录，可以重新执行迁移

