# DoFun项目代码检查报告

## 一、严重BUG（需要立即修复）

### 1. SQL注入风险 - `admin/clone_test.php:113` ✅ **已修复**
**位置**: `admin/clone_test.php` 第113行

**问题代码**:
```php
$optStmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id IN (" . implode(',', array_keys($questionIdMap)) . ")");
$optStmt->execute();
```

**问题**: 虽然 `array_keys($questionIdMap)` 理论上都是整数，但直接拼接到SQL中仍然存在风险。应该使用占位符。

**修复方案**:
```php
if ($questionIdMap) {
    // 使用占位符防止SQL注入
    $sourceQuestionIds = array_keys($questionIdMap);
    $placeholders = implode(',', array_fill(0, count($sourceQuestionIds), '?'));
    $optStmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id IN ($placeholders)");
    $optStmt->execute($sourceQuestionIds);
    $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);
    // ...
}
```

**修复状态**: ✅ 已修复（2024-12-19）

### 2. 数据库表缺失 - `dimensions` 表 ✅ **已修复**
**位置**: `admin/clone_test.php:78-93`

**问题**: 代码中引用了 `dimensions` 表，但在 `database/schema.sql` 中没有定义这个表。

**影响**: 克隆功能会失败，如果源测验使用了dimensions表。

**修复方案**: 
- 移除了所有对 `dimensions` 表的 SQL 查询和写入逻辑
- 不再复制任何与维度相关的旧结构
- 保留对 `tests.scoring_config` 的克隆（直接复制 JSON）
- 同时克隆 `scoring_mode` 字段
- 维度定义现在完全存储在 JSON 中，无需额外的数据库表

**修复状态**: ✅ 已修复（2024-12-19）

### 3. 字段名不一致 - `questions` 表 ✅ **已修复**
**位置**: `test.php:188`, `admin/clone_test.php:86,91`

**问题**: 
- `test.php` 使用 `pick_field($question, ['content', 'question_text', ...])` 尝试多个字段名
- `admin/clone_test.php:86,91` 使用 `content` 字段名和 `$question['content']`
- 但 `database/schema.sql` 中定义的是 `question_text`

**影响**: 可能导致题目显示为空或错误。

**修复方案**: 
- `test.php:188`: 将 `pick_field($question, ['content', 'question_text', 'title', 'body'], '未命名问题')` 改为 `$question['question_text'] ?? '未命名问题'`
- `admin/clone_test.php:86`: 将 INSERT 语句中的 `content` 字段改为 `question_text`
- `admin/clone_test.php:91`: 将 `$question['content']` 改为 `$question['question_text'] ?? ''`
- 删除了所有对 `content` 字段的兼容逻辑

**修复状态**: ✅ 已修复（2024-12-19）

## 二、安全性问题

### 4. 缺少CSRF保护 ✅ **已修复**
**位置**: 所有POST表单（`login.php`, `register.php`, `admin/test_edit.php` 等）

**问题**: 所有表单提交都没有CSRF token验证，存在CSRF攻击风险。

**修复方案**: 
- 创建了 `lib/csrf.php` CSRF 保护库，提供 token 生成、验证和表单字段生成功能
- 在所有 POST 表单中添加了隐藏的 CSRF token 字段：
  - `login.php` - 登录表单
  - `register.php` - 注册表单
  - `test.php` - 测验提交表单
  - `submit.php` - 测验提交处理（添加验证）
  - `admin/test_edit.php` - 管理员编辑表单
  - `admin/partials/test_edit_content.php` - 基础信息、题目编辑、结果编辑表单
  - `admin/clone_test.php` - 克隆测验表单
  - `admin/login.php` - 管理员登录表单
  - `admin/admin_users.php` - 管理员用户管理表单
- 在所有 POST 处理处添加了 CSRF token 验证，验证失败时返回 403 错误或显示错误消息

**修复状态**: ✅ 已修复（2024-12-19）

### 5. XSS风险 - 富文本内容 ✅ **已修复**
**位置**: `result.php:111`, `test.php` 等显示用户/管理员输入内容的地方

**问题**: 
- `result.php:111` 使用 `nl2br(htmlspecialchars(...))` 处理描述，但如果描述包含HTML（来自富文本编辑器），可能不安全
- 富文本编辑器允许输入HTML，但输出时没有进行适当的过滤

**修复方案**: 
- 创建了 `lib/html_purifier.php` HTML 净化库，实现白名单标签和属性过滤
- 允许的标签：`<b>`, `<strong>`, `<em>`, `<i>`, `<u>`, `<s>`, `<strike>`, `<p>`, `<br>`, `<a>`, `<img>`, `<span>`
- 允许的属性：
  - `<a>`: `href` (仅 http/https/相对路径), `target` (仅 _blank/_self), `rel`
  - `<img>`: `src` (仅 http/https/相对路径), `alt`, `style` (仅安全CSS属性)
  - `<span>`: `style` (仅安全CSS属性，如 color, background-color)
- 在所有显示富文本内容的地方应用了净化：
  - `result.php` - 结果描述（两处：主页面和海报）
  - `test.php` - 测验描述
  - `index.php` - 测验描述预览（移除所有HTML标签，只显示纯文本）
- 使用 DOMDocument 进行可靠的HTML解析和属性过滤（如果可用），否则回退到正则表达式方法
- 实现了 `purifyWithBreaks()` 方法，支持保留换行符转换

**修复状态**: ✅ 已修复（2024-12-19）

### 6. 密码泄露风险 - 备份脚本 ✅ **已修复**
**位置**: `backup.php:45-54`

**问题**: 虽然使用了 `escapeshellarg`，但密码可能出现在进程列表中。

**修复方案**: 
- 使用 `mysqldump` 的 `--defaults-file` 选项替代命令行 `--password` 参数
- 创建临时 MySQL 配置文件（`[client]` 节），包含 host、port、user、password
- 设置临时配置文件权限为 600（仅所有者可读）
- 备份完成后立即删除临时配置文件
- 密码不再出现在进程列表中，提高了安全性

**修复状态**: ✅ 已修复（2024-12-19）

## 三、逻辑错误和边界情况

### 7. 缺少输入验证 - `submit.php` ✅ 已修复
**位置**: `submit.php:36-88`

**问题**: 
```php
$answers = $_POST['q'] ?? [];
```
没有验证答案是否属于当前测验的题目，也没有验证 option_id 是否属于对应的 question_id。

**影响**: 用户可能提交不属于该测验的答案或无效选项，导致评分错误。

**修复方案**: 
- 验证所有答案的 question_id 都属于当前测验
- 验证每个 option_id 是否属于对应的 question_id
- 如果验证失败，返回 400 错误

**修复代码**:
```php
// 验证所有答案的 question_id 都属于当前测验
// 验证每个 option_id 是否属于对应的 question_id
// 构建有效的 option_id => question_id 映射
// 验证每个提交的答案：option_id 必须属于对应的 question_id
```

**修复状态**: ✅ 已修复（2024-12-19）

### 8. 并发问题 - `share_token` 生成 ✅ 已修复
**位置**: `submit.php:79-88`

**问题**: 使用 `random_bytes` 或 `md5(uniqid())` 生成token，理论上存在碰撞可能（虽然概率极低）。

**修复方案**: 
- 生成后检查数据库中是否已存在
- 如果存在则重新生成（最多重试 5 次）
- 如果重试多次后仍然冲突，返回 500 错误

**修复代码**:
```php
// 生成分享 token（16位十六进制），确保唯一性
$shareToken = null;
$maxRetries = 5;
$retryCount = 0;

while ($retryCount < $maxRetries) {
    // 生成 token
    // 检查数据库中是否已存在
    // 如果不存在则跳出循环
    // 如果存在则重试
}
```

### 9. 错误处理不完善 - `ScoreEngine.php` ✅ 已修复
**位置**: `lib/ScoreEngine.php:67-85`, `lib/ScoreEngine.php:214-225`

**问题**: 当 `$normalizedAnswers` 为空时，直接返回空结果，但没有记录日志或抛出异常。当 dimensions 模式配置不完整时，也没有记录日志。

**修复方案**: 
- 在 `score()` 方法中，当输入无效时记录警告日志
- 在 `scoreDimensions()` 方法中，当配置不完整时记录警告日志
- 日志写入 `logs/score_engine.log`，如果目录不存在则自动创建
- 如果文件写入失败，回退到系统日志

**修复代码**:
```php
// 记录警告日志，便于调试
$logMessage = sprintf(
    '[ScoreEngine] 无效输入: testId=%d, answersCount=%d, normalizedCount=%d',
    $testId,
    count($answers),
    count($normalizedAnswers)
);
// 尝试写入日志文件，如果失败则使用系统日志
```

**修复状态**: ✅ 已修复（2024-12-19）

### 10. 缺少事务处理 - `submit.php` ✅ 已修复
**位置**: `submit.php:153-200`

**问题**: 插入 `test_runs` 和 `test_run_scores` 时没有使用事务，如果第二个插入失败，会导致数据不一致。

**修复方案**: 
- 使用 `beginTransaction()` 开始事务
- 将插入 `test_runs` 和 `test_run_scores` 的操作都放在 try-catch 块中
- 如果所有操作成功，调用 `commit()` 提交事务
- 如果发生异常，调用 `rollBack()` 回滚事务
- 记录错误日志，便于调试
- 返回 500 错误给用户

**修复代码**:
```php
$pdo->beginTransaction();
try {
    // 插入 test_runs
    // 插入 test_run_scores
    $pdo->commit();
} catch (Exception $e) {
    $pdo->rollBack();
    // 记录错误日志
    // 返回错误响应
}
```

**修复状态**: ✅ 已修复（2024-12-19）

## 四、数据库结构优化建议

### 11. 缺少索引 ✅ 已修复
**问题**: 
- `test_runs.share_token` 没有索引，但经常用于查询（`result.php:11`）
- `test_runs.created_at` 没有索引，但用于排序和统计（`admin/index.php:12`）

**性能影响**:
- `share_token` 查询：每次通过 token 查询结果时都需要全表扫描，数据量大时性能很差
- `created_at` 查询：统计和排序操作（如最近7天的测试记录）性能较差

**修复方案**:
1. 创建了专门的迁移脚本 `database/008_add_test_runs_indexes.sql`
2. 使用存储过程检查索引是否存在，避免重复执行时报错
3. 更新了 `database/007_optimize_schema.sql`，包含相同的安全检查逻辑

**执行方法**:

**命令行执行**:
```bash
# 方法 1：使用专门的索引迁移脚本（推荐）
mysql -u username -p database_name < database/008_add_test_runs_indexes.sql

# 方法 2：使用完整的优化脚本
mysql -u username -p database_name < database/007_optimize_schema.sql
```

**phpMyAdmin 执行**（推荐）:
1. 打开 phpMyAdmin，选择你的数据库
2. 点击 "SQL" 标签页
3. 复制并执行 `database/008_add_test_runs_indexes_phpmyadmin.sql` 中的内容
   - 这是最简单的方法，直接执行 ALTER TABLE 语句
   - 如果索引已存在会报错，可以忽略或先检查索引是否存在
4. 或者使用安全版本 `database/008_add_test_runs_indexes_phpmyadmin_safe.sql`（带存储过程检查）

**执行步骤**（phpMyAdmin 简单版本）:
```sql
-- 1. 添加 share_token 索引
ALTER TABLE `test_runs` ADD INDEX `idx_share_token` (`share_token`);

-- 2. 添加 created_at 索引
ALTER TABLE `test_runs` ADD INDEX `idx_created_at` (`created_at`);

-- 3. 验证（可选）
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND index_name IN ('idx_share_token', 'idx_created_at');
```

**修复的索引**:
- `idx_share_token`: 优化 `result.php` 中通过 token 查询的性能
- `idx_created_at`: 优化按时间排序和统计查询的性能

**验证方法**:
执行脚本后会自动显示验证结果，或手动执行：
```sql
SELECT TABLE_NAME, INDEX_NAME, COLUMN_NAME
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND index_name IN ('idx_share_token', 'idx_created_at');
```

**修复状态**: ✅ 已修复（2024-12-19）

### 12. 字段类型优化 ✅ 已修复
**问题**: 
- `test_runs.total_score` 使用 `int(11)`，但 `ScoreEngine` 返回的是 `float`
- `test_run_scores.score_value` 也是 `int(11)`，但维度分数未来可能是小数
- `results.min_score` 和 `max_score` 也是 `int(11)`，需要与 `total_score` 类型一致

**当前情况**:
- 目前所有评分权重都是整数（+2, +1, -1, -2），所以 `int(11)` 暂时不会出错
- 代码中已经使用 `(float)` 转换，说明架构已经准备好支持小数

**潜在隐患**:
虽然当前使用整数权重不会出错，但如果未来需要：
- 题目权重系数（如 0.5×）
- Z-Score 标准化
- 维度百分比保留两位小数
- 多重矩阵映射（如 Big Five 机制）

使用 `int(11)` 会导致：
- 小数被直接截断成整数（不会警告）
- 用户得分出现偏差
- 隐性 bug，难以排查

**修复方案**:
将所有分数相关字段改为 `DECIMAL(10,2)`，支持两位小数：
- `test_runs.total_score`: `int(11)` → `DECIMAL(10,2) DEFAULT NULL`
- `test_run_scores.score_value`: `int(11)` → `DECIMAL(10,2) NOT NULL DEFAULT 0`
- `results.min_score`: `int(11)` → `DECIMAL(10,2) DEFAULT NULL`
- `results.max_score`: `int(11)` → `DECIMAL(10,2) DEFAULT NULL`

**执行方法**:

**命令行执行**:
```bash
mysql -u username -p database_name < database/009_optimize_score_fields.sql
```

**phpMyAdmin 执行**（推荐）:
1. 打开 phpMyAdmin，选择你的数据库
2. 点击 "SQL" 标签页
3. 复制并执行 `database/009_optimize_score_fields_phpmyadmin.sql` 中的内容

**执行步骤**（phpMyAdmin 简单版本）:
```sql
-- 1. 修改 test_runs.total_score
ALTER TABLE `test_runs` 
  MODIFY COLUMN `total_score` DECIMAL(10,2) DEFAULT NULL;

-- 2. 修改 test_run_scores.score_value
ALTER TABLE `test_run_scores` 
  MODIFY COLUMN `score_value` DECIMAL(10,2) NOT NULL DEFAULT 0;

-- 3. 修改 results.min_score
ALTER TABLE `results` 
  MODIFY COLUMN `min_score` DECIMAL(10,2) DEFAULT NULL;

-- 4. 修改 results.max_score
ALTER TABLE `results` 
  MODIFY COLUMN `max_score` DECIMAL(10,2) DEFAULT NULL;
```

**优势**:
- ✅ 不影响现有测验（整数自动转换为 DECIMAL，如 100 → 100.00）
- ✅ 未来支持小数和标准化计算
- ✅ 提高准确性，增强专业感
- ✅ 代码中已使用 `(float)` 转换，无需修改 PHP 代码

**验证方法**:
执行脚本后会自动显示验证结果，或手动执行：
```sql
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE table_schema = DATABASE()
  AND (
    (TABLE_NAME = 'test_runs' AND COLUMN_NAME = 'total_score')
    OR (TABLE_NAME = 'test_run_scores' AND COLUMN_NAME = 'score_value')
    OR (TABLE_NAME = 'results' AND COLUMN_NAME IN ('min_score', 'max_score'))
  )
ORDER BY TABLE_NAME, COLUMN_NAME;
```

**修复状态**: ✅ 已修复（2024-12-19）

### 13. 外键约束缺失 ✅ 已修复
**问题**: 
- `test_runs.user_id` 应该引用 `users.id`，但没有外键约束
- `question_answers` 表缺少外键约束，可能导致数据不一致
- 虽然有 `ON DELETE CASCADE` 的注释，但实际schema中可能没有设置

**当前情况**:
- `test_runs` 表已有 `user_id` 字段和索引，但缺少外键约束
- `question_answers` 表有 `test_run_id`、`question_id`、`test_id` 字段，但都缺少外键约束
- 其他表（如 `questions`、`question_options`、`results` 等）已有外键约束

**潜在隐患**:
缺少外键约束会导致：
- **数据完整性风险**：可能插入无效的引用（如 `user_id` 指向不存在的用户）
- **孤立数据**：删除主表记录时，子表记录变成孤立数据
- **查询性能**：数据库优化器无法利用外键关系优化查询
- **维护困难**：表之间的关系不够明确，难以理解和维护

**修复方案**:
添加以下缺失的外键约束：

1. **`test_runs.user_id` → `users.id`**
   - 约束名：`fk_runs_user`
   - 删除规则：`ON DELETE SET NULL`
   - 原因：`user_id` 可为空，删除用户时保留测试记录但清空用户关联（匿名化）

2. **`question_answers.test_run_id` → `test_runs.id`**
   - 约束名：`fk_answers_run`
   - 删除规则：`ON DELETE CASCADE`
   - 原因：删除测试记录时自动删除相关答案

3. **`question_answers.question_id` → `questions.id`**
   - 约束名：`fk_answers_question`
   - 删除规则：`ON DELETE CASCADE`
   - 原因：删除题目时自动删除相关答案

4. **`question_answers.test_id` → `tests.id`**
   - 约束名：`fk_answers_test`
   - 删除规则：`ON DELETE CASCADE`
   - 原因：删除测试时自动删除相关答案（虽然可通过 `test_run_id` 关联，但确保数据一致性）

**执行方法**:

**命令行执行**:
```bash
mysql -u username -p database_name < database/010_add_foreign_keys.sql
```

**phpMyAdmin 执行**（推荐）:
1. 打开 phpMyAdmin，选择你的数据库
2. 点击 "SQL" 标签页
3. 复制并执行 `database/010_add_foreign_keys_phpmyadmin.sql` 中的内容

**执行步骤**（phpMyAdmin 简单版本）:
```sql
-- 1. test_runs.user_id → users.id
ALTER TABLE `test_runs`
  ADD CONSTRAINT `fk_runs_user` 
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
  ON DELETE SET NULL;

-- 2. question_answers.test_run_id → test_runs.id
ALTER TABLE `question_answers`
  ADD CONSTRAINT `fk_answers_run` 
  FOREIGN KEY (`test_run_id`) REFERENCES `test_runs` (`id`) 
  ON DELETE CASCADE;

-- 3. question_answers.question_id → questions.id
ALTER TABLE `question_answers`
  ADD CONSTRAINT `fk_answers_question` 
  FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) 
  ON DELETE CASCADE;

-- 4. question_answers.test_id → tests.id
ALTER TABLE `question_answers`
  ADD CONSTRAINT `fk_answers_test` 
  FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) 
  ON DELETE CASCADE;
```

**执行前检查**:
如果表中已有孤立数据（引用了不存在的记录），添加外键会失败。执行前请检查：

```sql
-- 检查 test_runs 中的孤立 user_id
SELECT * FROM test_runs 
WHERE user_id IS NOT NULL 
  AND user_id NOT IN (SELECT id FROM users);

-- 检查 question_answers 中的孤立 test_run_id
SELECT * FROM question_answers 
WHERE test_run_id IS NOT NULL 
  AND test_run_id NOT IN (SELECT id FROM test_runs);

-- 检查 question_answers 中的孤立 question_id
SELECT * FROM question_answers 
WHERE question_id NOT IN (SELECT id FROM questions);

-- 检查 question_answers 中的孤立 test_id
SELECT * FROM question_answers 
WHERE test_id NOT IN (SELECT id FROM tests);
```

如果发现孤立数据，需要先清理或修复后再执行脚本。

**优势**:
- ✅ 保证数据完整性，防止插入无效引用
- ✅ 自动级联删除，避免孤立数据
- ✅ 提高查询性能，优化器可以利用外键关系
- ✅ 明确表之间的关系，便于理解和维护

**验证方法**:
执行脚本后会自动显示验证结果，或手动执行：
```sql
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME,
    DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE
WHERE table_schema = DATABASE()
  AND referenced_table_name IS NOT NULL
  AND (
    (TABLE_NAME = 'test_runs' AND CONSTRAINT_NAME = 'fk_runs_user')
    OR (TABLE_NAME = 'question_answers' AND CONSTRAINT_NAME IN ('fk_answers_run', 'fk_answers_question', 'fk_answers_test'))
  )
ORDER BY TABLE_NAME, CONSTRAINT_NAME;
```

**修复状态**: ✅ 已修复（2024-12-19）

### 14. 字段长度优化 ✅ 已修复
**问题**: 
- `users.email` 使用 `VARCHAR(255)`，但实际作为用户名使用，可能过长
- `test_runs.share_token` 使用 `CHAR(16)`，当前代码生成 16 字符，但为了更好的安全性和唯一性，建议使用 32 字符

**当前情况**:
- `users.email` 字段虽然名叫 email，但实际用作用户名（见 `lib/user_auth.php:16-20`）
  - 用户名规则：3-25 位英文和数字组合（正则：`/^[A-Za-z0-9]{3,25}$/`）
  - 当前使用 `VARCHAR(255)`，远超过实际需求，浪费存储空间和索引空间
- `test_runs.share_token` 当前使用 `CHAR(16)`
  - 代码中生成方式：`bin2hex(random_bytes(8))` = 16 字符（8字节的十六进制）
  - 16 字符的 token 在大量数据时碰撞概率较高
  - 建议改为 32 字符（`bin2hex(random_bytes(16))`），提高安全性和唯一性

**潜在隐患**:
- **存储浪费**：`VARCHAR(255)` 用于 3-25 位的用户名，浪费大量存储空间
- **索引效率**：过长的字段会影响索引效率，增加查询时间
- **安全性**：16 字符的 token 在大量数据时碰撞概率较高，可能导致数据混淆

**修复方案**:

1. **`users.email`：从 `VARCHAR(255)` 改为 `VARCHAR(50)`**
   - 用户名规则是 3-25 位，`VARCHAR(50)` 足够容纳并留有余量
   - 减少存储空间，提高索引效率
   - 不影响现有数据（现有用户名都在 25 位以内）

2. **`test_runs.share_token`：从 `CHAR(16)` 改为 `CHAR(32)`**
   - 当前代码生成 16 字符，但为了更好的安全性和唯一性，建议使用 32 字符
   - 需要同步更新代码中的生成逻辑为 `bin2hex(random_bytes(16))`
   - 或者保持 16 字符，但需要确保代码和数据库一致

**执行方法**:

**命令行执行**:
```bash
mysql -u username -p database_name < database/011_optimize_field_lengths.sql
```

**phpMyAdmin 执行**（推荐）:
1. 打开 phpMyAdmin，选择你的数据库
2. 点击 "SQL" 标签页
3. 复制并执行 `database/011_optimize_field_lengths_phpmyadmin.sql` 中的内容

**执行步骤**（phpMyAdmin 简单版本）:
```sql
-- 1. 优化 users.email：从 VARCHAR(255) 改为 VARCHAR(50)
ALTER TABLE `users`
  MODIFY COLUMN `email` VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL;

-- 2. 优化 test_runs.share_token：从 CHAR(16) 改为 CHAR(32)
-- 注意：修改后需要更新代码中的生成逻辑为 bin2hex(random_bytes(16))
ALTER TABLE `test_runs`
  MODIFY COLUMN `share_token` CHAR(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL;
```

**执行前检查**:
如果表中已有超过新长度的数据，修改会失败。执行前请检查：

```sql
-- 检查 users.email 是否有超过 50 字符的数据
SELECT 
    COUNT(*) AS over_length_count,
    MAX(CHAR_LENGTH(email)) AS max_length
FROM users
WHERE CHAR_LENGTH(email) > 50;

-- 检查 test_runs.share_token 是否有超过 32 字符的数据
SELECT 
    COUNT(*) AS over_length_count,
    MAX(CHAR_LENGTH(share_token)) AS max_length
FROM test_runs
WHERE share_token IS NOT NULL AND CHAR_LENGTH(share_token) > 32;
```

如果发现超过新长度的数据，需要先清理或修复后再执行脚本。

**代码更新**（如果选择将 share_token 改为 32 字符）:
修改 `submit.php:121`，将：
```php
$shareToken = bin2hex(random_bytes(8));  // 16 字符
```
改为：
```php
$shareToken = bin2hex(random_bytes(16)); // 32 字符
```

**选项说明**:
- **选项 1（推荐）**：将 `share_token` 改为 `CHAR(32)`，并更新代码生成逻辑
  - 优点：更高的安全性和唯一性，减少碰撞概率
  - 缺点：需要更新代码，现有 16 字符的 token 需要迁移或清理
  
- **选项 2**：保持 `share_token` 为 `CHAR(16)`，只优化 `users.email`
  - 优点：不需要修改代码，向后兼容
  - 缺点：token 碰撞概率相对较高

**优势**:
- ✅ 减少存储空间，提高索引效率
- ✅ 提高 token 安全性和唯一性（如果选择 32 字符）
- ✅ 字段长度更符合实际需求，便于维护

**验证方法**:
执行脚本后会自动显示验证结果，或手动执行：
```sql
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE table_schema = DATABASE()
  AND (
    (TABLE_NAME = 'users' AND COLUMN_NAME = 'email')
    OR (TABLE_NAME = 'test_runs' AND COLUMN_NAME = 'share_token')
  )
ORDER BY TABLE_NAME, COLUMN_NAME;
```

**修复状态**: ✅ 已修复（2024-12-19）

### 15. 缺少唯一约束
**问题**: 
- `test_runs.share_token` 应该唯一，但没有唯一索引

**修复建议**:
```sql
ALTER TABLE `test_runs` 
  ADD UNIQUE KEY `uk_share_token` (`share_token`);
```

### 16. 表结构不一致
**问题**: 
- `database/schema.sql` 中 `questions` 表使用 `question_text`
- 但代码中有时使用 `content`
- `admin/clone_test.php` 中插入时使用 `content`，但schema中是 `question_text`

**修复建议**: 统一字段名，建议全部使用 `question_text`。

## 五、代码质量问题

### 17. 硬编码的魔法值
**位置**: 多处

**问题**: 
- `submit.php:48` 硬编码 `random_bytes(8)` 生成16位token
- 各种状态值（'draft', 'published'）硬编码在代码中

**修复建议**: 使用常量或配置类定义这些值。

### 18. 错误信息泄露
**位置**: `lib/db_connect.php:11`

**问题**: 
```php
die('数据库连接失败：' . $e->getMessage());
```
在生产环境可能泄露数据库信息。

**修复建议**: 
```php
if (defined('DEBUG') && DEBUG) {
    die('数据库连接失败：' . $e->getMessage());
} else {
    die('数据库连接失败，请稍后再试');
}
```

### 19. 缺少输入长度限制
**位置**: `admin/partials/test_edit_content.php:95-109`

**问题**: 虽然前端有 `maxlength`，但后端没有验证，可能被绕过。

**修复建议**: 在后端也添加长度验证。

### 20. 缺少分页限制
**位置**: `my_tests.php:21`

**问题**: 
```php
LIMIT 100
```
硬编码限制，如果用户答题很多，可能无法查看全部记录。

**修复建议**: 实现分页功能。

## 六、性能优化建议

### 21. N+1查询问题 ✅ **已修复**
**位置**: `admin/partials/test_edit_content.php:484-512`

**问题**: 在循环中执行SQL查询：
```php
foreach ($questions as $q):
    $stmtOpt->execute([':qid' => $qid]);
```

**影响**: 如果有 N 个题目，就会执行 N+1 次查询（1次查询题目 + N次查询选项），性能很差。

**修复方案**: 
- 在循环之前，一次性查询所有题目的选项（使用 IN 查询）
- 将选项按 `question_id` 分组存储在数组中
- 在循环中直接从内存中的分组数组获取选项
- 从 N+1 次查询优化为 2 次查询（1次查询题目 + 1次查询所有选项）

**修复代码**:
```php
// 一次性查询所有题目的选项，避免 N+1 查询问题
$questionIds = array_column($questions, 'id');
$optionsByQuestionId = [];

if (!empty($questionIds)) {
    // 使用占位符构建 IN 查询
    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
    $stmtOpt = $pdo->prepare("
        SELECT id, question_id, option_key, option_text
        FROM question_options
        WHERE question_id IN ($placeholders)
        ORDER BY question_id ASC, option_key ASC, id ASC
    ");
    $stmtOpt->execute($questionIds);
    $allOptions = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);
    
    // 按 question_id 分组
    foreach ($allOptions as $opt) {
        $qid = (int)$opt['question_id'];
        if (!isset($optionsByQuestionId[$qid])) {
            $optionsByQuestionId[$qid] = [];
        }
        $optionsByQuestionId[$qid][] = $opt;
    }
}

foreach ($questions as $q):
    $qid = (int)$q['id'];
    $opts = $optionsByQuestionId[$qid] ?? [];
```

**修复状态**: ✅ 已修复（2024-12-19）

### 22. 缺少查询缓存
**位置**: 多处

**问题**: 一些不经常变化的数据（如测验列表）每次都查询数据库。

**修复建议**: 对热点数据实现缓存机制（Redis或文件缓存）。

**修复状态**: ✅ 已修复（2024-12-19）

**修复内容**:
- 创建了 `lib/CacheHelper.php` 文件缓存工具类
- 在 `index.php` 中为已发布测验列表添加缓存（5分钟）
- 在 `router.php` 中为 slug 查询添加缓存（5分钟）
- 在 `test.php` 中为测验详情、问题、选项添加缓存（5分钟），play_count 使用较短缓存（1分钟）
- 在管理员保存/更新测验、问题、结果时自动清除相关缓存
- 缓存使用文件系统存储，无需额外依赖

## 七、总结

### 优先级修复清单

**高优先级（立即修复）**:
1. SQL注入风险 (#1)
2. 字段名不一致 (#3)
3. 缺少CSRF保护 (#4)
4. 缺少事务处理 (#10)

**中优先级（尽快修复）**:
5. 缺少输入验证 (#7)
6. 数据库索引优化 (#11, #15)
7. 错误处理完善 (#9)
8. XSS风险 (#5)

**低优先级（逐步优化）**:
9. 性能优化 (#21, #22)
10. 代码质量改进 (#17, #18, #19, #20)

### 数据库结构优化清单

1. 添加 `test_runs.share_token` 唯一索引
2. 添加 `test_runs.created_at` 索引
3. 统一 `questions` 表字段名（`question_text` vs `content`）
4. 检查并修复 `share_token` 字段长度
5. 添加外键约束
6. 考虑是否需要 `dimensions` 表，或从代码中移除相关逻辑

