# 网站地图优化文档

本文档说明 DoFun心理实验空间 的网站地图（Sitemap）优化实现。

## 已实现的优化

### 1. 主网站地图（sitemap.php）

#### 优化内容：
- ✅ **正确的 URL 格式**：使用 `/test.php?slug=` 而不是旧的 `/quiz.php?id=`
- ✅ **使用 updated_at 字段**：优先使用 `updated_at` 作为 `lastmod`，如果没有则使用 `created_at`
- ✅ **只包含已发布内容**：只包含 `status = 'published'` 的测验
- ✅ **包含重要页面**：首页（`/`）和测验列表页（`/index.php`）
- ✅ **分页支持**：如果 URL 超过 50,000 个，自动分页（Google 限制）

#### URL 结构：
```xml
<url>
    <loc>https://fun.dofun.fun/test.php?slug=xxx</loc>
    <lastmod>2025-01-15</lastmod>
    <changefreq>weekly</changefreq>
    <priority>0.8</priority>
</url>
```

### 2. 网站地图索引（sitemap_index.php）

#### 功能：
- 自动检测是否需要多个 sitemap 文件
- 如果只有一个 sitemap，自动重定向到 `sitemap.php`
- 如果有多个 sitemap，显示索引文件
- 自动包含图片 sitemap（如果存在）

#### 使用方式：
在 `robots.txt` 中引用：
```
Sitemap: https://fun.dofun.fun/sitemap_index.php
```

### 3. 图片网站地图（image_sitemap.php）

#### 功能：
- 包含所有已发布测验的封面图片
- 自动检测 `cover_image` 字段是否存在
- 如果字段不存在，返回空的 sitemap（不会报错）
- 自动处理相对路径和绝对路径

#### 图片 URL 处理：
- 如果图片 URL 是相对路径（如 `/images/test.jpg`），自动转换为绝对路径
- 如果图片 URL 已经是绝对路径（如 `https://example.com/image.jpg`），保持不变

## 技术实现

### 分页逻辑

每页最多包含 50,000 个 URL（Google 的限制）：
- 首页和列表页只在第一页包含
- 测验页面按 `sort_order DESC, id DESC` 排序
- 通过 `?page=N` 参数访问不同页面

### 状态过滤

使用 `Constants::TEST_STATUS_PUBLISHED` 常量来过滤：
- 支持字符串状态：`'published'`
- 兼容数字状态：`1`（旧数据）

### 时间字段优先级

1. 优先使用 `updated_at`（如果存在且不为空）
2. 其次使用 `created_at`（如果存在且不为空）
3. 最后使用当前日期

## 验证方法

### 1. 直接访问
- 主 sitemap：`https://fun.dofun.fun/sitemap.php`
- 索引文件：`https://fun.dofun.fun/sitemap_index.php`
- 图片 sitemap：`https://fun.dofun.fun/image_sitemap.php`

### 2. Google Search Console
1. 登录 [Google Search Console](https://search.google.com/search-console)
2. 选择你的网站
3. 进入 "Sitemaps" 部分
4. 提交 `sitemap_index.php` 的 URL
5. 等待 Google 验证和索引

### 3. XML 验证工具
- [XML Sitemap Validator](https://www.xml-sitemaps.com/validate-xml-sitemap.html)
- 检查 XML 格式是否正确
- 检查 URL 是否可访问

## 最佳实践

### 1. 更新频率
- **首页**：`changefreq=daily`（每天更新）
- **列表页**：`changefreq=daily`（每天更新）
- **测验页**：`changefreq=weekly`（每周更新）

### 2. 优先级设置
- **首页**：`priority=1.0`（最高）
- **列表页**：`priority=0.9`（高）
- **测验页**：`priority=0.8`（中高）

### 3. 维护建议
- 定期检查 sitemap 是否正常生成
- 确保所有 URL 都是可访问的
- 监控 Google Search Console 中的错误报告
- 如果添加了新页面类型，记得更新 sitemap

## 性能优化

### 缓存考虑
当前实现每次请求都查询数据库。如果网站很大，可以考虑：
- 使用文件缓存（每天生成一次 sitemap）
- 使用 Redis/Memcached 缓存查询结果
- 使用定时任务生成静态 XML 文件

### 数据库优化
- 确保 `tests` 表有 `status` 和 `sort_order` 的索引
- 考虑添加 `updated_at` 的索引（如果经常按时间查询）

## 未来扩展

可以考虑添加：
- 结果页面的 sitemap（如果结果页面有独立 URL）
- 新闻/博客文章的 sitemap（如果有内容管理系统）
- 视频 sitemap（如果有视频内容）
- 新闻 sitemap（如果有新闻内容）

## 故障排查

### 问题：sitemap 返回空内容
- 检查是否有已发布的测验
- 检查数据库连接是否正常
- 检查 `Constants::TEST_STATUS_PUBLISHED` 的值是否正确

### 问题：图片 sitemap 为空
- 检查 `cover_image` 字段是否存在
- 检查是否有测验设置了封面图片
- 检查图片 URL 格式是否正确

### 问题：Google 无法访问 sitemap
- 检查服务器是否允许访问 `.php` 文件
- 检查 `robots.txt` 是否正确
- 检查 HTTPS 证书是否有效

