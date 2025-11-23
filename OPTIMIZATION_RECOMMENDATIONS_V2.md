# 全站优化建议报告

> 基于代码审查，专注于性能优化、Bug修复和功能优化，不增加新功能

## 一、性能优化

### 1. 优化 `SELECT *` 查询

**问题位置**：
- `result.php:15, 19, 33, 39` - 使用 `SELECT *` 查询
- `admin/stats.php:87` - 使用 `SELECT *` 查询
- `lib/AdHelper.php:60, 161, 176` - 使用 `SELECT *` 查询
- `lib/ScoreEngine.php:93` - 使用 `SELECT *` 查询选项

**影响**：
- 加载不必要的数据（特别是 TEXT 字段如 `description`）
- 增加内存占用和网络传输
- 降低查询性能

**优化方案**：
```php
// result.php 优化示例
// 原代码：
$runStmt = $pdo->prepare("SELECT * FROM test_runs WHERE share_token = :token LIMIT 1");

// 优化后：
$runStmt = $pdo->prepare("
    SELECT id, test_id, result_id, user_id, total_score, share_token, created_at 
    FROM test_runs 
    WHERE share_token = :token 
    LIMIT 1
");
```

**优先级**：高

---

### 2. 优化 `admin/tests.php` 的 N+1 查询

**问题位置**：`admin/tests.php:11`

**问题**：
```php
SELECT t.*,
       (SELECT COUNT(*) FROM test_runs r WHERE r.test_id = t.id) AS run_count
FROM tests t
```

虽然使用了子查询，但如果测验数量很多，每个测验都会执行一次子查询。

**优化方案**：
```php
// 使用 LEFT JOIN + GROUP BY 替代子查询
$stmt = $pdo->query("
    SELECT t.*, COUNT(r.id) AS run_count
    FROM tests t
    LEFT JOIN test_runs r ON r.test_id = t.id
    GROUP BY t.id
    ORDER BY t.sort_order DESC, t.id DESC
");
```

**优先级**：中

---

### 3. 优化 `index.php` 的标签统计逻辑

**问题位置**：`index.php:50-70`

**问题**：
- 标签统计在每次请求时计算（即使有缓存）
- 如果缓存未命中，需要遍历所有测验的标签

**优化方案**：
- 在数据库层面统计标签使用次数（可以添加一个 `tag_usage` 表或使用缓存表）
- 或者使用 Redis 的 sorted set 来统计热门标签

**优先级**：低（已有缓存机制）

---

### 4. 优化缓存策略

**问题位置**：`index.php:38-40`

**问题**：
```php
} else {
    // 如果从缓存获取，也需要限制数量
    $tests = array_slice($tests, 0, $homeTestsLimit);
}
```

如果缓存的是完整列表，但 `homeTestsLimit` 变化了，可能导致显示不一致。

**优化方案**：
- 缓存时按 `homeTestsLimit` 作为键的一部分
- 或者缓存完整列表，但确保限制逻辑一致

**优先级**：低

---

### 5. 优化文件锁性能

**问题位置**：`lib/CacheHelper.php:80`

**问题**：
```php
return @file_put_contents($path, $content, LOCK_EX) !== false;
```

在高并发情况下，文件锁可能导致性能瓶颈。

**优化方案**：
- 考虑使用 APCu 或 Redis 作为一级缓存
- 文件缓存作为二级缓存
- 或者使用更轻量的锁机制

**优先级**：中（如果并发量不高可忽略）

---

## 二、Bug修复

### 6. `result.php` 缺少 token 格式验证

**问题位置**：`result.php:9`

**问题**：
```php
$shareTokenParam = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
```

没有验证 token 的格式（应该是32位十六进制字符串）。

**修复方案**：
```php
$shareTokenParam = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
if ($shareTokenParam !== '' && !preg_match('/^[a-f0-9]{32}$/i', $shareTokenParam)) {
    http_response_code(400);
    echo '无效的分享链接。';
    exit;
}
```

**优先级**：中

---

### 7. `submit.php` token 生成在高并发下可能冲突

**问题位置**：`submit.php:114-148`

**问题**：
虽然有重试机制，但在极高并发下，多个请求可能同时生成相同的 token。

**修复方案**：
- 使用数据库唯一索引 + 异常处理
- 或者使用 UUID 替代随机字符串
- 或者使用数据库自增ID + 加密

**优先级**：低（当前重试机制已足够）

---

### 8. `index.php` 缓存逻辑不一致

**问题位置**：`index.php:20-41`

**问题**：
- 缓存时存储完整列表
- 但获取时可能限制数量
- 如果 `homeTestsLimit` 变化，可能导致显示不一致

**修复方案**：
```php
// 将 limit 作为缓存键的一部分
$cacheKey = 'published_tests_list_' . $homeTestsLimit;
```

**优先级**：低

---

### 9. `test.php` 中 `choose_order_field` 函数可能 SQL 注入

**问题位置**：`test.php:101-102`

**问题**：
```php
$questionOrderField = choose_order_field($pdo, 'questions');
$questionOrderSql = $questionOrderField ? "ORDER BY {$questionOrderField} ASC, id ASC" : "ORDER BY id ASC";
```

虽然 `choose_order_field` 只返回白名单字段，但直接拼接到 SQL 中仍有风险。

**修复方案**：
```php
// 使用白名单验证
$allowedFields = ['sort_order', 'order_number', 'display_order'];
$questionOrderField = choose_order_field($pdo, 'questions');
if ($questionOrderField && in_array($questionOrderField, $allowedFields)) {
    $questionOrderSql = "ORDER BY `{$questionOrderField}` ASC, id ASC";
} else {
    $questionOrderSql = "ORDER BY id ASC";
}
```

**优先级**：中（虽然当前实现相对安全，但可以更严格）

---

## 三、功能优化

### 10. 统一错误处理

**问题位置**：多个文件

**问题**：
- 有些地方使用 `die()` 直接输出错误
- 有些地方使用 `ErrorHandler::renderError()`
- 错误信息格式不统一

**优化方案**：
- 统一使用 `ErrorHandler::renderError()` 处理错误
- 移除所有 `die()` 和直接 `echo` 错误的地方

**优先级**：中

---

### 11. 优化数据库连接管理

**问题位置**：`lib/AdHelper.php:17-24`

**问题**：
```php
private static function initPdo(): void
{
    if (self::$pdo === null) {
        require_once __DIR__ . '/db_connect.php';
        global $pdo;
        self::$pdo = $pdo;
    }
}
```

使用全局变量 `$pdo`，可能导致连接管理混乱。

**优化方案**：
- 使用依赖注入
- 或者使用单例模式统一管理数据库连接

**优先级**：低

---

### 12. 优化主题切换代码重复

**问题位置**：多个 PHP 文件

**问题**：
主题切换的 JavaScript 代码在多个文件中重复。

**优化方案**：
- 提取到公共 JS 文件
- 或者使用 PHP 模板函数统一渲染

**优先级**：低

---

### 13. 优化缓存键命名

**问题位置**：多个文件

**问题**：
缓存键命名不统一，例如：
- `published_tests_list`
- `test_full_{id}`
- `test_play_count_{id}`
- `test_slug_id_{hash}`

**优化方案**：
统一使用命名空间前缀，例如：
- `tests:list:published`
- `test:full:{id}`
- `test:play_count:{id}`
- `test:slug_id:{hash}`

**优先级**：低

---

### 14. 添加数据库索引建议

**建议位置**：数据库表

**建议**：
- `test_runs.test_id` - 应该已有索引
- `test_runs.share_token` - 应该已有唯一索引
- `questions.test_id` - 应该已有索引
- `question_options.question_id` - 应该已有索引
- `results.test_id` - 应该已有索引

**验证**：检查这些字段是否都有索引，如果没有则添加。

**优先级**：高（如果缺少索引）

---

## 四、代码质量改进

### 15. 移除未使用的代码

**检查位置**：全站

**建议**：
- 使用静态分析工具检查未使用的函数和变量
- 移除注释掉的代码
- 清理临时文件（如 `temp.txt`）

**优先级**：低

---

### 16. 统一代码风格

**问题位置**：全站

**建议**：
- 统一使用 PSR-12 代码风格
- 统一变量命名（驼峰 vs 下划线）
- 统一注释风格

**优先级**：低

---

### 17. 添加类型声明

**问题位置**：函数参数和返回值

**建议**：
- 为所有函数添加类型声明
- 使用 PHP 7.4+ 的类型提示特性

**优先级**：低

---

## 五、安全性改进

### 18. 验证用户输入

**问题位置**：多个文件

**建议**：
- 所有用户输入都应该验证和过滤
- 使用 `htmlspecialchars()` 转义输出
- 使用 `filter_var()` 验证输入格式

**优先级**：高

---

### 19. 防止 CSRF 攻击

**问题位置**：所有表单提交

**验证**：
- 检查所有表单是否都有 CSRF token
- 验证 token 是否正确验证

**优先级**：高（如果缺少）

---

## 六、总结

### 高优先级（立即修复）
1. ✅ 优化 `SELECT *` 查询（性能）
2. ✅ 添加数据库索引验证（性能）
3. ✅ 验证用户输入（安全）

### 中优先级（近期修复）
4. 优化 `admin/tests.php` 的 N+1 查询
5. 修复 `result.php` token 格式验证
6. 优化 `test.php` SQL 注入防护
7. 统一错误处理

### 低优先级（可选）
8. 优化缓存策略
9. 优化文件锁性能
10. 统一代码风格
11. 移除未使用的代码

---

## 实施建议

1. **先修复高优先级问题**：这些对性能和安全性影响最大
2. **逐步优化**：不要一次性修改太多，避免引入新问题
3. **测试验证**：每次修改后都要测试，确保功能正常
4. **性能监控**：使用 APM 工具监控优化效果

---

*生成时间：2024-12-19*
*基于代码审查：全站 PHP 文件*

