# 安装指南

## 快速开始

DoFun 测验平台提供了自动安装向导，让您能够快速完成安装。

### 系统要求

- PHP >= 7.4
- MySQL >= 5.7 或 MariaDB >= 10.2
- PDO MySQL 扩展
- JSON 扩展
- 可写的 `config` 和 `cache` 目录

### 安装步骤

#### 1. 下载代码

```bash
git clone <repository-url>
cd fun
```

#### 2. 运行安装向导

在浏览器中访问：

```
http://your-domain.com/install.php
```

或者直接访问：

```
http://your-domain.com/install/
```

#### 3. 按照向导完成安装

安装向导包含三个步骤：

**步骤 1：环境检查**
- 自动检查 PHP 版本、扩展、目录权限等
- 确保所有要求都满足后继续

**步骤 2：数据库配置**
- 填写数据库连接信息：
  - 数据库主机（通常是 `127.0.0.1` 或 `localhost`）
  - 数据库名称（如果不存在会自动创建）
  - 数据库用户名
  - 数据库密码
- 点击"测试连接"验证配置

**步骤 3：创建管理员账户**
- 设置管理员用户名
- 设置管理员密码（至少 6 个字符）
- 确认密码
- 设置显示名称（可选）

#### 4. 完成安装

安装完成后，系统会：
- 自动创建数据库和所有表结构
- 创建管理员账户
- 生成 `.env` 配置文件
- 创建安装锁定文件 `.installed`

安装完成后会自动跳转到登录页面。

### 手动安装（可选）

如果您更喜欢手动安装，可以按照以下步骤：

#### 1. 配置数据库

复制 `.env.example` 为 `.env` 并修改配置：

```env
DB_HOST=127.0.0.1
DB_DATABASE=fun_quiz
DB_USERNAME=root
DB_PASSWORD=your_password
DB_CHARSET=utf8mb4
```

#### 2. 创建数据库

```sql
CREATE DATABASE fun_quiz CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

#### 3. 导入数据库结构

按顺序执行 `database/` 目录下的 SQL 文件：

```bash
mysql -u root -p fun_quiz < database/001_init_schema.sql
mysql -u root -p fun_quiz < database/002_seed_basic_data.sql
mysql -u root -p fun_quiz < database/004_create_backup_logs_table.sql
# ... 其他 SQL 文件
```

#### 4. 创建管理员账户

```sql
INSERT INTO admin_users (username, password_hash, display_name, is_active)
VALUES ('admin', '$2y$10$...', '管理员', 1);
```

使用 PHP 生成密码哈希：

```php
<?php
echo password_hash('your_password', PASSWORD_DEFAULT);
```

#### 5. 设置目录权限

确保以下目录可写：

```bash
chmod 755 config
chmod 755 cache
chmod 755 logs
```

### 验证安装

安装完成后，访问以下页面验证：

- 前台首页：`http://your-domain.com/`
- 后台登录：`http://your-domain.com/admin/login.php`

使用您创建的管理员账户登录。

### 故障排除

#### 安装向导无法访问

- 确保 Web 服务器已启动
- 检查文件权限
- 查看 PHP 错误日志

#### 数据库连接失败

- 检查数据库服务是否运行
- 验证数据库用户名和密码
- 确认数据库主机地址正确
- 检查防火墙设置

#### SQL 执行错误

- 确保 MySQL 版本 >= 5.7
- 检查数据库用户是否有创建表和索引的权限
- 查看 PHP 错误日志获取详细错误信息

#### 权限错误

- 确保 `config` 目录可写（用于创建 `.env` 文件）
- 确保 `cache` 目录可写
- 确保 `logs` 目录可写（如果存在）

### 重新安装

如果需要重新安装：

1. 删除 `.installed` 文件
2. 删除 `.env` 文件（可选，会重新生成）
3. 清空数据库或删除数据库
4. 重新运行安装向导

### 安全建议

安装完成后：

1. **删除安装目录**（生产环境）：
   ```bash
   rm -rf install/
   rm install.php
   ```

2. **保护 `.env` 文件**：
   - 确保 `.env` 文件不在 Web 根目录可访问
   - 或通过 `.htaccess` 禁止访问

3. **修改管理员密码**：
   - 登录后立即修改默认管理员密码

4. **检查文件权限**：
   ```bash
   chmod 644 .env
   chmod 755 config
   chmod 755 cache
   ```

### 升级

如果是从旧版本升级，请参考 [MIGRATION_USAGE.md](./MIGRATION_USAGE.md) 文档。

### 获取帮助

如果遇到问题，请：

1. 查看错误日志：`logs/` 目录
2. 检查 PHP 错误日志
3. 查看数据库错误日志
4. 提交 Issue 到项目仓库

