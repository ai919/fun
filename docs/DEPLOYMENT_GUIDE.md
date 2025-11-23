# 线上部署指南

本文档说明如何将代码更新和数据库迁移安全地部署到生产环境。

## 📋 部署前准备

### 1. 备份数据库

**⚠️ 重要：执行任何数据库迁移前，必须先备份数据库！**

```bash
# 使用 mysqldump 备份
mysqldump -u用户名 -p数据库名 > backup_$(date +%Y%m%d_%H%M%S).sql

# 或通过后台备份功能
# 访问 /admin/backup.php 进行备份
```

### 2. 检查迁移状态

在部署前，检查当前生产环境的迁移状态：

**方式一：通过后台管理界面**
1. 访问 `/admin/migrations.php`
2. 查看"迁移状态"表格，确认哪些迁移已执行

**方式二：通过命令行**

```php
<?php
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/Migration.php';

$migration = new Migration($pdo);
$status = $migration->status();

foreach ($status as $item) {
    echo "{$item['migration']}: {$item['status']}\n";
}
```

### 3. 测试环境验证

**在测试环境先执行迁移，验证无误后再部署到生产环境！**

```bash
# 1. 在测试环境执行迁移预览
# 访问 /admin/migrations.php，点击"预览（不执行）"

# 2. 确认无误后执行迁移
# 点击"执行所有待迁移"

# 3. 验证功能正常
# 测试相关功能是否正常工作
```

---

## 🚀 部署步骤

### 方式一：通过后台管理界面（推荐）

**适用于：有后台访问权限，迁移操作简单的情况**

1. **上传代码**
   ```bash
   # 通过 Git 或其他方式将代码部署到服务器
   git pull origin main
   # 或使用 FTP/SFTP 上传文件
   ```

2. **访问迁移管理页面**
   - 登录后台：`https://your-domain.com/admin/login.php`
   - 访问迁移页面：`https://your-domain.com/admin/migrations.php`

3. **预览迁移（重要！）**
   - 点击"预览（不执行）"按钮
   - 确认将要执行的迁移列表
   - 检查是否有错误提示

4. **执行迁移**
   - 确认预览无误后，点击"执行所有待迁移"
   - 等待执行完成，查看执行结果
   - 如有错误，立即检查并处理

5. **验证部署**
   - 检查网站功能是否正常
   - 查看数据库结构是否正确
   - 检查日志文件是否有错误

### 方式二：通过命令行

**适用于：服务器有 SSH 访问权限，需要自动化部署的情况**

```bash
# 1. SSH 登录服务器
ssh user@your-server.com

# 2. 进入项目目录
cd /path/to/your/project

# 3. 拉取最新代码
git pull origin main

# 4. 执行迁移（使用 PHP CLI）
php -r "
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/Migration.php';

\$migration = new Migration(\$pdo);

// 先预览
\$results = \$migration->migrate(true);
echo '预览结果：' . json_encode(\$results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;

// 确认后执行（取消注释下面两行）
// \$results = \$migration->migrate(false);
// echo '执行结果：' . json_encode(\$results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . PHP_EOL;
"
```

### 方式三：直接执行 SQL（不推荐）

**⚠️ 不推荐：无法记录迁移历史，难以回滚**

仅在紧急情况下使用，且需要手动记录：

```sql
-- 1. 备份数据库
-- 2. 执行 SQL
ALTER TABLE `tests`
  ADD COLUMN `play_count_beautified` INT UNSIGNED NULL DEFAULT NULL AFTER `display_mode`;

-- 3. 手动记录到 migrations 表（如果使用迁移系统）
INSERT INTO `migrations` (`migration`, `batch`) 
VALUES ('2024_12_19_143000_add_play_count_beautified_to_tests', 
        (SELECT COALESCE(MAX(batch), 0) + 1 FROM (SELECT batch FROM migrations) AS t));
```

---

## 🔄 回滚操作

如果迁移执行后出现问题，可以回滚：

### 通过后台管理界面

1. 访问 `/admin/migrations.php`
2. 点击"预览回滚"查看将要回滚的迁移
3. 确认后点击"回滚最后一个批次"

### 通过命令行

```php
<?php
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/Migration.php';

$migration = new Migration($pdo);

// 预览回滚
$results = $migration->rollback(null, true);
echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

// 执行回滚（确认后取消注释）
// $results = $migration->rollback();
// echo json_encode($results, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
```

---

## 📝 本次迁移说明（017）

### 迁移内容

**文件**: `database/migrations/2024_12_19_143000_add_play_count_beautified_to_tests.php`

**功能**: 为 `tests` 表添加 `play_count_beautified` 字段

**SQL 操作**:
```sql
ALTER TABLE `tests`
  ADD COLUMN `play_count_beautified` INT UNSIGNED NULL DEFAULT NULL AFTER `display_mode`;
```

### 影响范围

- **表**: `tests`
- **操作**: 添加字段（非破坏性，不影响现有数据）
- **风险**: 低（仅添加字段，不修改现有数据）

### 执行步骤

1. **备份数据库** ✅
2. **上传代码**（包含迁移文件）
3. **预览迁移**（确认无误）
4. **执行迁移**
5. **验证功能**（检查美化设置页面是否正常）

### 验证方法

```sql
-- 检查字段是否添加成功
SHOW COLUMNS FROM `tests` LIKE 'play_count_beautified';

-- 应该返回：
-- Field: play_count_beautified
-- Type: int(10) unsigned
-- Null: YES
-- Default: NULL
```

---

## ⚠️ 注意事项

### 1. 迁移执行时机

- **推荐时间**: 业务低峰期（如凌晨 2-4 点）
- **避免时间**: 业务高峰期、重要活动期间

### 2. 迁移执行顺序

- 迁移按时间戳顺序自动执行
- 不要手动修改已执行的迁移文件
- 如需修改，创建新的迁移文件

### 3. 错误处理

如果迁移执行失败：

1. **不要惊慌**，迁移系统使用事务，失败会自动回滚
2. **查看错误信息**，了解失败原因
3. **检查日志文件**：`logs/error.log`
4. **修复问题**后重新执行
5. **必要时恢复备份**

### 4. 字段已存在的情况

如果字段已存在（可能之前手动执行过 SQL），迁移会自动跳过，不会报错。

### 5. 生产环境安全

- ✅ 使用 HTTPS 访问后台
- ✅ 确保后台有访问控制（`admin/auth.php`）
- ✅ 定期更新密码
- ✅ 限制后台访问 IP（如可能）

---

## 🔍 故障排查

### 问题：迁移执行失败

**可能原因**:
1. 数据库连接失败
2. SQL 语法错误
3. 权限不足
4. 表/字段已存在

**解决方法**:
1. 检查数据库配置：`config/db.php`
2. 检查数据库用户权限
3. 查看错误日志：`logs/error.log`
4. 手动检查数据库状态

### 问题：迁移显示已执行，但字段不存在

**可能原因**:
- 迁移记录存在，但实际执行失败
- 手动删除了字段

**解决方法**:
```sql
-- 1. 删除迁移记录
DELETE FROM `migrations` 
WHERE `migration` = '2024_12_19_143000_add_play_count_beautified_to_tests';

-- 2. 重新执行迁移
```

### 问题：无法访问迁移管理页面

**可能原因**:
1. 未登录后台
2. 文件路径错误
3. 服务器配置问题

**解决方法**:
1. 确认已登录：`/admin/login.php`
2. 检查文件是否存在：`admin/migrations.php`
3. 检查服务器错误日志

---

## 📞 获取帮助

如果遇到问题：

1. **查看日志**: `logs/error.log`
2. **检查文档**: `docs/MIGRATION_USAGE.md`
3. **联系技术支持**

---

**最后更新**: 2024-12-19

