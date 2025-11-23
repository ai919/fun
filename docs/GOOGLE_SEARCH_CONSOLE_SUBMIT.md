# Google Search Console 提交指南

本文档说明如何将网站的 sitemap 提交到 Google Search Console。

## Sitemap 文件位置

网站已自动生成以下 sitemap 文件：

### 1. 主 Sitemap 索引文件（推荐使用）
```
https://fun.dofun.fun/sitemap_index.php
```

**说明**：
- 如果网站 URL 数量少于 50,000，会自动重定向到 `sitemap.php`
- 如果超过 50,000 个 URL，会显示包含多个 sitemap 的索引文件
- 自动包含图片 sitemap（如果存在）

### 2. 主 Sitemap 文件
```
https://fun.dofun.fun/sitemap.php
```

**包含内容**：
- 首页（`/`）
- 测验列表页（`/index.php`）
- 所有已发布的测验页面（`/test.php?slug=xxx`）

### 3. 图片 Sitemap（如果存在）
```
https://fun.dofun.fun/image_sitemap.php
```

**包含内容**：
- 所有已发布测验的封面图片

## 提交步骤

### 方法一：通过 Google Search Console 提交（推荐）

1. **访问 Google Search Console**
   - 打开 https://search.google.com/search-console
   - 使用您的 Google 账号登录

2. **选择或添加属性**
   - 如果还没有添加网站，点击"添加属性"
   - 选择"网址前缀"方式，输入：`https://fun.dofun.fun`
   - 完成验证（DNS 验证、HTML 文件验证或 HTML 标签验证）

3. **提交 Sitemap**
   - 在左侧菜单中，点击"网站地图"（Sitemaps）
   - 在"添加新的网站地图"输入框中，输入：
     ```
     sitemap_index.php
     ```
   - 点击"提交"按钮

4. **验证提交**
   - 提交后，Google 会显示处理状态
   - 通常几分钟内会显示"成功"状态
   - 如果显示错误，请检查 sitemap 文件是否可以正常访问

### 方法二：通过 robots.txt 声明（已配置）

网站已在 `robots.txt` 中声明了 sitemap：

```
Sitemap: https://fun.dofun.fun/sitemap_index.php
```

Google 会自动发现并抓取这个 sitemap，但建议也在 Search Console 中手动提交，以便更快被索引。

## 验证 Sitemap 是否正常工作

### 1. 直接访问 Sitemap URL

在浏览器中访问：
```
https://fun.dofun.fun/sitemap_index.php
```

应该看到 XML 格式的 sitemap 内容。

### 2. 使用 Google Search Console 测试工具

在 Google Search Console 中：
1. 进入"网站地图"页面
2. 点击已提交的 sitemap
3. 查看"已发现的网址"数量
4. 检查是否有错误或警告

### 3. 使用在线验证工具

可以使用以下工具验证 sitemap 格式：
- https://www.xml-sitemaps.com/validate-xml-sitemap.html
- https://www.xml-sitemaps.com/

## Sitemap 内容说明

### URL 格式

网站使用以下 URL 格式：

- **首页**：`https://fun.dofun.fun/`
- **测验列表**：`https://fun.dofun.fun/index.php`
- **测验页面**：`https://fun.dofun.fun/test.php?slug=xxx`

### 优先级设置

- **首页**：priority = 1.0，changefreq = daily
- **测验列表页**：priority = 0.9，changefreq = daily
- **测验页面**：priority = 0.8，changefreq = weekly

### 更新频率

- Sitemap 是动态生成的，每次访问都会获取最新的数据
- 只包含状态为"已发布"且有 slug 的测验
- `lastmod` 字段使用测验的 `updated_at` 或 `created_at` 时间

## 常见问题

### Q: 为什么提交后显示"无法获取"？

**A:** 可能的原因：
1. 服务器配置问题，PHP 文件无法正常执行
2. 数据库连接失败
3. 文件权限问题

**解决方法**：
- 检查 `sitemap_index.php` 和 `sitemap.php` 是否可以直接访问
- 查看服务器错误日志
- 确保数据库连接正常

### Q: 为什么显示的 URL 数量少于实际测验数量？

**A:** 可能的原因：
1. 只包含已发布的测验（status = 'published' 或 status = 1）
2. 只包含有 slug 的测验（slug 不为空）
3. 草稿或已归档的测验不会出现在 sitemap 中

### Q: 如何更新已提交的 Sitemap？

**A:** 
- Sitemap 是动态生成的，无需手动更新
- Google 会自动定期重新抓取
- 也可以在 Search Console 中点击"重新抓取"按钮

### Q: 可以提交多个 Sitemap 吗？

**A:** 
- 可以，但建议使用 `sitemap_index.php` 作为主入口
- `sitemap_index.php` 会自动包含所有相关的 sitemap 文件
- 如果网站 URL 超过 50,000，会自动分页

## 最佳实践

1. **定期检查**
   - 每月检查一次 Google Search Console 中的 sitemap 状态
   - 关注错误和警告信息

2. **监控索引状态**
   - 在 Search Console 中查看"覆盖率"报告
   - 关注"已编入索引"的 URL 数量

3. **优化内容**
   - 确保所有重要页面都有 slug
   - 定期更新测验内容，更新 `updated_at` 字段
   - 保持 URL 结构的一致性

4. **性能优化**
   - Sitemap 文件已启用缓存，减少数据库查询
   - 如果网站很大，考虑使用静态 sitemap 文件

## 相关文件

- `sitemap.php` - 主 sitemap 生成器
- `sitemap_index.php` - Sitemap 索引文件
- `image_sitemap.php` - 图片 sitemap 生成器
- `robots.txt` - 已声明 sitemap 位置
- `docs/SITEMAP_OPTIMIZATION.md` - Sitemap 优化文档

## 技术支持

如果遇到问题，请检查：
1. 服务器日志
2. Google Search Console 中的错误信息
3. Sitemap 文件是否可以正常访问
4. 数据库连接是否正常

