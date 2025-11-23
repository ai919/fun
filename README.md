# DoFun 在线测验平台

一个功能完整、性能优化的在线测验/心理测试平台，支持多种计分模式、SEO优化、性能监控和广告位管理。

## 📋 目录

- [项目简介](#项目简介)
- [核心功能](#核心功能)
- [技术栈](#技术栈)
- [项目结构](#项目结构)
- [安装部署](#安装部署)
- [配置说明](#配置说明)
- [功能模块](#功能模块)
- [开发指南](#开发指南)
- [更新日志](#更新日志)
- [贡献指南](#贡献指南)

---

## 🎯 项目简介

DoFun 是一个基于 PHP 开发的在线测验平台，支持创建、发布和管理各种类型的心理测试、性格测试、趣味测验等。平台提供了完整的后台管理系统、SEO优化、性能监控、缓存系统等企业级功能。

### 主要特性

- ✅ **多种计分模式**：简单计分、维度计分、范围计分、自定义计分
- ✅ **完整的后台管理**：测验管理、题目管理、结果管理、用户管理
- ✅ **SEO 优化**：结构化数据、sitemap、URL优化、元标签管理
- ✅ **性能优化**：多层级缓存、页面缓存、数据库连接池
- ✅ **监控系统**：APM性能监控、结构化日志、日志分析
- ✅ **广告位管理**：9个预设广告位，支持代码/图片/文字广告
- ✅ **国际化支持**：多语言切换（中文/英文）
- ✅ **用户系统**：注册、登录、收藏、分享统计
- ✅ **数据库迁移**：版本化数据库结构管理

---

## 🚀 核心功能

### 前台功能

1. **测验浏览**
   - 首页展示所有已发布的测验
   - 支持搜索和筛选
   - 显示测验播放次数和热门度

2. **答题系统**
   - 流畅的答题界面
   - 支持单选、多选等题型
   - 实时进度显示

3. **结果展示**
   - 根据分数自动匹配结果
   - 支持结果海报生成
   - 分享功能（复制链接、分享文案）

4. **用户功能**
   - 用户注册和登录
   - 我的测验收藏
   - 个人中心

### 后台功能

1. **测验管理**
   - 创建、编辑、删除测验
   - 测验状态管理（草稿/已发布/已归档）
   - 测验克隆功能
   - 排序管理

2. **题目管理**
   - 题目和选项的增删改查
   - 题目排序
   - 选项计分配置

3. **结果管理**
   - 结果配置和编辑
   - 分数范围设置
   - 结果图片上传

4. **系统管理**
   - SEO设置和优化
   - 广告位管理
   - 缓存管理
   - 数据库迁移
   - 系统日志查看
   - 性能监控
   - 备份管理

---

## 🛠 技术栈

### 后端

- **PHP 7.4+** - 核心语言
- **MySQL 5.7+** - 数据库
- **PDO** - 数据库抽象层
- **原生 PHP** - 无框架依赖，轻量高效

### 前端

- **HTML5 / CSS3** - 现代前端标准
- **JavaScript (ES6+)** - 交互功能
- **响应式设计** - 支持移动端
- **暗色模式** - 主题切换

### 工具和库

- **APCu** - 内存缓存
- **Redis** (可选) - 分布式缓存
- **HTML Purifier** - XSS防护
- **结构化日志** - JSON格式日志

---

## 📁 项目结构

```
fun/
├── admin/                  # 后台管理
│   ├── index.php          # 后台首页
│   ├── tests.php          # 测验管理
│   ├── questions.php      # 题目管理
│   ├── results.php        # 结果管理
│   ├── seo_settings.php   # SEO设置
│   ├── ad_positions.php   # 广告位管理
│   ├── migrations.php     # 数据库迁移
│   ├── performance.php    # 性能监控
│   └── ...
├── api/                   # API接口
│   └── share_stats.php    # 分享统计
├── assets/                # 静态资源
│   ├── css/              # 样式文件
│   ├── js/               # JavaScript文件
│   └── images/           # 图片资源
├── cache/                 # 缓存目录
│   └── pages/            # 页面缓存
├── config/                # 配置文件
│   ├── app.php           # 应用配置
│   ├── db.php            # 数据库配置
│   └── admin.php         # 后台配置
├── database/              # 数据库相关
│   ├── *.sql            # SQL脚本
│   └── migrations/      # 迁移文件
├── docs/                  # 文档目录
│   ├── AD_POSITIONS_USAGE.md
│   ├── SEO_AND_MONITORING.md
│   └── ...
├── lang/                  # 语言包
│   ├── zh-CN.php         # 中文
│   └── en-US.php         # 英文
├── lib/                   # 核心类库
│   ├── Database.php      # 数据库抽象层
│   ├── Cache.php         # 缓存系统
│   ├── Response.php      # 统一响应
│   ├── Config.php        # 配置管理
│   ├── Migration.php     # 数据库迁移
│   ├── APM.php           # 性能监控
│   ├── AdHelper.php      # 广告位助手
│   └── ...
├── logs/                  # 日志目录
├── index.php              # 首页
├── test.php               # 测验页
├── result.php             # 结果页
├── router.php             # 路由处理
├── sitemap.php            # 站点地图
└── README.md              # 本文件
```

---

## 📦 安装部署

### 环境要求

- PHP 7.4 或更高版本
- MySQL 5.7 或更高版本
- Apache/Nginx Web服务器
- 推荐启用：APCu、OPcache、Redis（可选）

### 安装步骤

1. **克隆或下载项目**

```bash
git clone <repository-url>
cd fun
```

2. **配置数据库**

编辑 `config/db.php` 或创建 `.env` 文件：

```php
// config/db.php
return [
    'host' => 'localhost',
    'dbname' => 'fun_quiz',
    'username' => 'your_username',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
];
```

或使用环境变量：

```env
DB_HOST=localhost
DB_NAME=fun_quiz
DB_USER=your_username
DB_PASS=your_password
```

3. **导入数据库**

执行数据库初始化脚本：

```sql
-- 执行 database/001_init_schema.sql
-- 然后根据需要执行其他迁移文件
```

或通过后台迁移系统执行：
- 访问 `/admin/migrations.php`
- 点击"执行所有待迁移"

4. **设置目录权限**

```bash
chmod -R 755 cache/
chmod -R 755 logs/
chmod -R 755 assets/
```

5. **配置 Web 服务器**

**Apache (.htaccess)**

确保启用了 `mod_rewrite` 模块。

**Nginx**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /path/to/fun;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

6. **访问后台**

- 后台地址：`/admin/login.php`
- 默认账号：`admin`
- 默认密码：请查看数据库初始化脚本或首次登录后修改

---

## ⚙️ 配置说明

### 应用配置 (`config/app.php`)

```php
return [
    'debug' => false,              // 调试模式
    'environment' => 'production', // 环境：development/production/testing
    'log' => [...],                // 日志配置
    'cache' => [...],              // 缓存配置
    'apm' => [...],               // APM监控配置
];
```

### 数据库配置 (`config/db.php`)

支持配置文件和环境变量两种方式，优先级：环境变量 > .env > 配置文件。

### SEO配置

通过后台 `/admin/seo_settings.php` 进行配置，包括：
- 站点标题和描述
- 默认关键词
- Open Graph 设置
- 结构化数据配置

### 广告位配置

通过后台 `/admin/ad_positions.php` 管理广告位，支持：
- 代码广告（Google AdSense等）
- 图片广告
- 文字广告

详见 `docs/AD_POSITIONS_USAGE.md`

---

## 🎨 功能模块

### 1. 测验系统

- **计分模式**
  - 简单计分：选项直接计分
  - 维度计分：多维度分析
  - 范围计分：分数范围匹配结果
  - 自定义计分：JSON配置灵活计分

- **题目类型**
  - 单选题
  - 多选题（可扩展）

### 2. 缓存系统

多层级缓存架构：

- **L1 缓存**：APCu（内存缓存，最快）
- **L2 缓存**：文件缓存（持久化）
- **L3 缓存**：Redis（可选，分布式）

支持标签化缓存失效策略。

### 3. SEO优化

- **结构化数据**：JSON-LD格式
- **Sitemap**：自动生成站点地图
- **URL优化**：友好的slug URL
- **元标签管理**：后台可配置
- **Open Graph**：社交媒体分享优化

### 4. 性能监控

- **APM系统**：请求响应时间、数据库查询监控
- **慢查询检测**：自动记录超过阈值的查询
- **日志分析**：错误趋势、性能统计
- **健康度评分**：系统整体健康评估

### 5. 广告位管理

9个预设广告位：
- 首页：顶部、中间、底部
- 测验页：顶部、中间（每3题后）、底部
- 结果页：顶部、中间、底部

支持代码广告、图片广告、文字广告三种类型。

### 6. 数据库迁移

版本化数据库结构管理：
- 自动记录已执行的迁移
- 支持回滚
- 后台可视化管理

### 7. 备份系统

- 数据库自动备份
- 备份日志记录
- 备份文件下载和管理

---

## 💻 开发指南

### 代码规范

- 遵循 PSR-12 编码规范
- 使用有意义的变量和函数名
- 添加必要的注释
- 保持代码简洁和可读性

### 核心类库使用

#### 数据库操作

```php
require_once __DIR__ . '/lib/Database.php';
require_once __DIR__ . '/lib/db_connect.php';

$db = new Database($pdo);
$tests = $db->table('tests')
    ->where('status', 'published')
    ->orderBy('sort_order', 'DESC')
    ->get();
```

#### 缓存操作

```php
require_once __DIR__ . '/lib/Cache.php';

// 设置缓存
Cache::set('key', $data, 3600, ['tag1', 'tag2']);

// 获取缓存
$data = Cache::get('key');

// 按标签删除
Cache::deleteByTag('tag1');
```

#### 统一响应

```php
require_once __DIR__ . '/lib/Response.php';

// API响应
Response::success($data, '操作成功');
Response::error('错误信息', 400);

// 重定向
Response::redirect('/admin/tests.php');
```

#### 配置读取

```php
require_once __DIR__ . '/lib/Config.php';

$debug = Config::get('app.debug');
$dbHost = Config::get('db.host');
```

### 添加新功能

1. **创建数据库迁移**（如需要）
   - 在 `database/` 目录创建 SQL 文件
   - 或使用迁移系统创建迁移文件

2. **创建业务逻辑类**（如需要）
   - 放在 `lib/` 目录
   - 遵循单一职责原则

3. **创建后台管理页面**（如需要）
   - 放在 `admin/` 目录
   - 使用 `admin/layout.php` 作为布局

4. **更新文档**
   - 更新本 README.md
   - 在 `docs/` 目录添加详细文档

### 测试

建议在开发环境进行充分测试：
- 功能测试
- 性能测试
- 兼容性测试

---

## 📝 更新日志

### 最新版本

#### 广告位管理系统 (2025-01-XX)

- ✅ 新增广告位管理功能
- ✅ 9个预设广告位（首页/测验页/结果页）
- ✅ 支持代码/图片/文字三种广告类型
- ✅ 后台可视化配置界面
- ✅ 广告位CSS样式和响应式设计

详见 `docs/AD_POSITIONS_USAGE.md`

#### SEO优化与监控系统

- ✅ URL结构优化（301重定向）
- ✅ 结构化日志系统
- ✅ 日志分析工具
- ✅ APM性能监控
- ✅ SEO内容优化器

详见 `docs/SEO_AND_MONITORING.md`

#### 核心功能优化

- ✅ 统一响应格式 (`lib/Response.php`)
- ✅ 配置管理系统 (`lib/Config.php`)
- ✅ 数据库抽象层 (`lib/Database.php`)
- ✅ 数据库迁移系统 (`lib/Migration.php`)
- ✅ 连接池优化 (`lib/DatabaseConnection.php`)

详见 `docs/IMPLEMENTATION_SUMMARY.md`

---

## 🤝 贡献指南

### 提交代码

1. Fork 本项目
2. 创建功能分支 (`git checkout -b feature/AmazingFeature`)
3. 提交更改 (`git commit -m 'Add some AmazingFeature'`)
4. 推送到分支 (`git push origin feature/AmazingFeature`)
5. 开启 Pull Request

### 代码要求

- 遵循项目代码规范
- 添加必要的注释和文档
- 确保向后兼容性
- 更新相关文档（包括本 README）

### 报告问题

使用 GitHub Issues 报告问题，包括：
- 问题描述
- 复现步骤
- 预期行为
- 实际行为
- 环境信息

---

## 📚 相关文档

- [广告位使用指南](docs/AD_POSITIONS_USAGE.md)
- [SEO优化与监控](docs/SEO_AND_MONITORING.md)
- [功能实现总结](docs/IMPLEMENTATION_SUMMARY.md)
- [数据库使用指南](docs/DATABASE_USAGE.md)
- [配置管理指南](docs/CONFIG_USAGE.md)
- [迁移系统使用](docs/MIGRATION_USAGE.md)
- [响应格式使用](docs/RESPONSE_USAGE.md)

---

## 📄 许可证

本项目采用 MIT 许可证。详见 LICENSE 文件。

---

## 🔗 相关链接

- 项目主页：[待添加]
- 问题反馈：[待添加]
- 更新日志：[待添加]

---

## ⚠️ 重要提示

### 更新 README

**当添加新功能或进行重大更改时，请务必更新本 README.md：**

1. ✅ 在"核心功能"部分添加新功能说明
2. ✅ 在"功能模块"部分添加详细描述
3. ✅ 在"更新日志"部分记录变更
4. ✅ 在"相关文档"部分添加文档链接
5. ✅ 更新"项目结构"（如有新增目录）
6. ✅ 更新"安装部署"步骤（如有配置变更）

### 安全建议

- 生产环境务必关闭调试模式
- 定期更新依赖和系统
- 使用强密码
- 启用 HTTPS
- 定期备份数据库

### 性能优化

- 启用 OPcache
- 配置适当的缓存策略
- 优化数据库查询
- 使用 CDN 加速静态资源

---

**最后更新：2025-01-XX**

如有问题或建议，欢迎提交 Issue 或 Pull Request！

