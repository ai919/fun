# Google Analytics 设置指南

本文档说明如何在网站中配置和使用 Google Analytics。

## 功能概述

网站已集成 Google Analytics 支持，您可以通过后台管理系统轻松配置：

- ✅ 支持 GA4（Google Analytics 4）测量 ID
- ✅ 支持完整的自定义脚本代码
- ✅ 后台开关控制，可随时启用/禁用
- ✅ 自动插入到所有前台页面的 `<head>` 标签中

## 安装步骤

### 1. 创建数据库表

首先需要运行数据库迁移文件创建 `settings` 表：

```sql
-- 执行 database/013_create_settings_table.sql
```

或者通过 phpMyAdmin 导入该 SQL 文件。

### 2. 获取 Google Analytics 代码

1. 访问 [Google Analytics](https://analytics.google.com) 并登录
2. 创建或选择一个属性（Property）
3. 在"管理" → "数据流"中找到您的网站数据流
4. 复制"测量 ID"（格式：`G-XXXXXXXXXX`）

### 3. 在后台配置

1. 登录后台管理系统
2. 进入"系统管理" → "Google Analytics"
3. 勾选"启用 Google Analytics"
4. 在"Google Analytics 代码"输入框中：
   - **方式一（推荐）**：输入 GA4 测量 ID，如 `G-XXXXXXXXXX`
   - **方式二**：输入完整的 Google Analytics 脚本代码
5. 点击"保存设置"

### 4. 验证安装

保存设置后，Google Analytics 代码会自动出现在所有前台页面的 `<head>` 标签中。

您可以通过以下方式验证：

1. **查看页面源代码**：
   - 打开任意前台页面（如首页、测验页面）
   - 右键点击"查看页面源代码"
   - 在 `<head>` 标签中查找 Google Analytics 代码

2. **使用浏览器开发者工具**：
   - 按 F12 打开开发者工具
   - 切换到"网络"（Network）标签
   - 刷新页面
   - 查找对 `googletagmanager.com` 的请求

3. **Google Analytics 实时报告**：
   - 登录 Google Analytics
   - 进入"报告" → "实时"
   - 访问您的网站，应该能看到实时访问数据

## 支持的输入方式

### 方式一：GA4 测量 ID（推荐）

只需输入测量 ID，系统会自动生成完整的跟踪脚本：

```
G-XXXXXXXXXX
```

系统会自动生成如下代码：

```html
<!-- Google Analytics (GA4) -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX');
</script>
```

### 方式二：完整脚本代码

如果您需要自定义配置或使用 Universal Analytics，可以输入完整的脚本代码：

```html
<!-- Google Analytics -->
<script async src="https://www.googletagmanager.com/gtag/js?id=G-XXXXXXXXXX"></script>
<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  gtag('js', new Date());
  gtag('config', 'G-XXXXXXXXXX', {
    'custom_parameter': 'value'
  });
</script>
```

## 代码插入位置

Google Analytics 代码会自动插入到以下前台页面的 `<head>` 标签中：

- ✅ 首页（`index.php`）
- ✅ 测验页面（`test.php`）
- ✅ 结果页面（`result.php`）

代码插入在 `<head>` 标签的末尾，确保在所有其他脚本之后加载。

## 管理设置

### 启用/禁用

在后台"Google Analytics 设置"页面，您可以随时：

- ✅ 勾选"启用 Google Analytics"来启用跟踪
- ❌ 取消勾选来禁用跟踪（代码不会出现在页面中）

### 更新代码

您可以随时更新 Google Analytics 代码：

1. 进入后台"Google Analytics 设置"
2. 修改"Google Analytics 代码"输入框中的内容
3. 点击"保存设置"

更改会立即生效，无需重启服务器。

## 技术实现

### 数据库结构

设置存储在 `settings` 表中：

- `google_analytics_enabled`: 是否启用（'0' 或 '1'）
- `google_analytics_code`: Google Analytics 代码或测量 ID

### 代码文件

- `lib/SettingsHelper.php`: 设置管理辅助类
- `admin/analytics_settings.php`: 后台设置页面
- 前台页面：`index.php`, `test.php`, `result.php`

### 工作原理

1. 后台保存设置到数据库
2. 前台页面加载时，调用 `SettingsHelper::renderGoogleAnalytics()`
3. 如果启用且有代码，则输出到页面 `<head>` 标签中
4. 如果代码是 GA4 测量 ID 格式，自动转换为完整脚本

## 常见问题

### Q: 为什么保存后看不到代码？

**A:** 可能的原因：
1. 未勾选"启用 Google Analytics"
2. 代码输入框为空
3. 浏览器缓存，请清除缓存或使用无痕模式
4. 数据库表未创建，请先运行迁移文件

### Q: 可以同时使用多个 Google Analytics 账号吗？

**A:** 可以，在"方式二"中输入包含多个测量 ID 的完整脚本代码即可。

### Q: 支持 Universal Analytics (UA) 吗？

**A:** 支持，使用"方式二"输入完整的 Universal Analytics 脚本代码即可。

### Q: 代码会影响页面加载速度吗？

**A:** Google Analytics 脚本是异步加载的，不会阻塞页面渲染。但建议在生产环境中启用，开发环境可以关闭。

### Q: 如何测试是否正常工作？

**A:** 
1. 查看页面源代码，确认代码已插入
2. 使用浏览器开发者工具查看网络请求
3. 在 Google Analytics 实时报告中查看访问数据
4. 使用 [Google Tag Assistant](https://tagassistant.google.com/) 浏览器扩展

## 最佳实践

1. **生产环境启用，开发环境禁用**
   - 避免开发测试数据污染分析数据
   - 在后台可以随时切换

2. **使用 GA4 测量 ID（方式一）**
   - 更简单，不易出错
   - 系统自动处理代码生成

3. **定期检查设置**
   - 确保代码正确
   - 确保开关状态正确

4. **监控数据**
   - 定期查看 Google Analytics 报告
   - 关注访问量、用户行为等指标

## 相关文件

- `database/013_create_settings_table.sql` - 数据库迁移文件
- `lib/SettingsHelper.php` - 设置管理辅助类
- `admin/analytics_settings.php` - 后台设置页面
- `admin/system.php` - 系统管理页面（包含 GA 设置入口）

## 技术支持

如果遇到问题，请检查：

1. 数据库表是否已创建
2. 后台设置是否正确保存
3. 前台页面是否正确引入 `SettingsHelper.php`
4. 浏览器控制台是否有错误信息
5. 服务器日志是否有相关错误

