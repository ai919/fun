# 数据库抽象层使用文档

## 概述

`Database` 类提供了统一的数据库查询接口，支持链式调用，减少重复代码，提高开发效率。

## 基本用法

### 初始化

```php
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/db_connect.php';

$db = new Database($pdo);
```

### 查询数据

```php
// 查询所有记录
$users = $db->table('users')->get();

// 查询单条记录
$user = $db->table('users')->where('id', 1)->first();

// 查询单个值
$count = $db->table('users')->where('status', 'active')->count();

// 获取单个字段值
$email = $db->table('users')->where('id', 1)->select('email')->value();
```

### WHERE 条件

```php
// 简单条件
$db->table('users')->where('status', 'active')->get();

// 多个条件（AND）
$db->table('users')
   ->where('status', 'active')
   ->where('age', '>', 18)
   ->get();

// OR 条件
$db->table('users')
   ->where('status', 'active')
   ->orWhere('status', 'pending')
   ->get();

// WHERE IN
$db->table('users')->whereIn('id', [1, 2, 3])->get();

// WHERE NULL
$db->table('users')->whereNull('deleted_at')->get();

// 数组形式（多个条件）
$db->table('users')->where([
    'status' => 'active',
    'age' => 18,
])->get();
```

### JOIN 连接

```php
// INNER JOIN
$db->table('users')
   ->join('profiles', 'users.id', '=', 'profiles.user_id')
   ->select('users.*', 'profiles.bio')
   ->get();

// LEFT JOIN
$db->table('users')
   ->leftJoin('profiles', 'users.id', '=', 'profiles.user_id')
   ->get();
```

### 排序和限制

```php
// ORDER BY
$db->table('users')
   ->orderBy('created_at', 'DESC')
   ->get();

// 多字段排序
$db->table('users')
   ->orderBy('status', 'ASC')
   ->orderBy('created_at', 'DESC')
   ->get();

// LIMIT
$db->table('users')->limit(10)->get();

// OFFSET
$db->table('users')->offset(10)->limit(10)->get();
```

### 插入数据

```php
// 单条插入
$id = $db->table('users')->insert([
    'name' => 'John',
    'email' => 'john@example.com',
]);

// 批量插入
$db->table('users')->insertBatch([
    ['name' => 'John', 'email' => 'john@example.com'],
    ['name' => 'Jane', 'email' => 'jane@example.com'],
]);
```

### 更新数据

```php
// 更新（必须包含 WHERE 条件）
$affected = $db->table('users')
               ->where('id', 1)
               ->update([
                   'name' => 'John Doe',
                   'updated_at' => date('Y-m-d H:i:s'),
               ]);
```

### 删除数据

```php
// 删除（必须包含 WHERE 条件）
$affected = $db->table('users')
               ->where('id', 1)
               ->delete();
```

### 执行原生 SQL

```php
// 执行原生查询
$stmt = $db->raw("SELECT * FROM users WHERE status = ?", ['active']);
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 执行原生更新
$db->raw("UPDATE users SET last_login = NOW() WHERE id = ?", [1]);
```

### 事务处理

```php
try {
    $db->beginTransaction();
    
    $db->table('users')->insert([...]);
    $db->table('profiles')->insert([...]);
    
    $db->commit();
} catch (Exception $e) {
    $db->rollBack();
    throw $e;
}
```

## 查询日志

### 启用查询日志

```php
// 启用查询日志
Database::enableQueryLog();

// 执行查询
$db->table('users')->get();

// 获取查询日志
$logs = Database::getQueryLog();
foreach ($logs as $log) {
    echo "SQL: {$log['sql']}\n";
    echo "Params: " . json_encode($log['params']) . "\n";
    echo "Time: {$log['time']}s\n";
}

// 禁用查询日志
Database::disableQueryLog();
```

## 使用示例

### 分页查询

```php
$page = $_GET['page'] ?? 1;
$perPage = 20;
$offset = ($page - 1) * $perPage;

$users = $db->table('users')
            ->where('status', 'active')
            ->orderBy('created_at', 'DESC')
            ->limit($perPage)
            ->offset($offset)
            ->get();

$total = $db->table('users')
            ->where('status', 'active')
            ->count();
```

### 复杂查询

```php
$results = $db->table('tests')
              ->select('tests.*', 'users.name as author_name')
              ->leftJoin('users', 'tests.user_id', '=', 'users.id')
              ->where('tests.status', 'published')
              ->where('tests.created_at', '>', '2024-01-01')
              ->whereIn('tests.category_id', [1, 2, 3])
              ->orderBy('tests.created_at', 'DESC')
              ->limit(10)
              ->get();
```

### 统计查询

```php
// 按状态统计
$stats = $db->table('users')
            ->select('status', 'COUNT(*) as count')
            ->groupBy('status')
            ->get();
```

## 注意事项

1. 所有查询方法都会自动重置查询构建器状态
2. UPDATE 和 DELETE 操作必须包含 WHERE 条件，防止误操作
3. 查询日志会记录所有执行的 SQL 和参数，便于调试和性能分析
4. 使用事务时，确保在异常情况下调用 `rollBack()`

