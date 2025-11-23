# SEO 优化与监控系统文档

本文档说明 DoFun心理实验空间 的 SEO 优化和监控系统实现。

## 一、URL 结构优化

### 1.1 301 重定向

已实现旧 URL 到新 URL 的 301 永久重定向：

- `test.php?id=123` → `/slug`
- `quiz.php?id=123` → `/slug`

**实现位置**: `router.php`

**工作原理**:
1. 检测到旧 URL 格式（`test.php?id=` 或 `quiz.php?id=`）
2. 从数据库或缓存获取对应的 slug
3. 执行 301 永久重定向到新 URL

**缓存策略**:
- slug 映射缓存 1 小时（3600 秒）
- 减少数据库查询

### 1.2 使用方式

无需额外配置，自动处理所有旧 URL 重定向。

## 二、结构化日志系统

### 2.1 概述

结构化日志系统使用 JSON 格式记录日志，便于日志分析和处理。

**文件位置**: `lib/StructuredLogger.php`

### 2.2 功能特性

- ✅ JSON 格式日志（便于解析和分析）
- ✅ 日志分级（DEBUG, INFO, WARNING, ERROR）
- ✅ 自动日志轮转（文件大小超过限制时）
- ✅ 上下文信息记录（请求 URI、IP、User-Agent 等）
- ✅ 性能日志记录

### 2.3 使用方法

```php
require_once __DIR__ . '/lib/StructuredLogger.php';

// 记录不同级别的日志
StructuredLogger::debug('调试信息', ['key' => 'value']);
StructuredLogger::info('一般信息', ['user_id' => 123]);
StructuredLogger::warning('警告信息', ['test_id' => 456]);
StructuredLogger::error('错误信息', ['error' => 'details']);

// 记录性能日志
StructuredLogger::performance('数据库查询', 0.5, ['table' => 'tests']);

// 记录数据库查询日志
StructuredLogger::query('SELECT * FROM tests', 0.2);
```

### 2.4 日志格式

```json
{
  "timestamp": "2025-01-15T10:30:00+08:00",
  "level": "ERROR",
  "message": "错误信息",
  "context": {
    "key": "value"
  },
  "server": {
    "request_uri": "/test.php?slug=xxx",
    "request_method": "GET",
    "remote_addr": "192.168.1.1",
    "user_agent": "Mozilla/5.0..."
  }
}
```

### 2.5 日志轮转

- 当日志文件超过 10MB 时自动轮转
- 保留最多 10 个历史文件
- 轮转后的文件命名：`error.json.log.2025-01-15_103000`

### 2.6 配置

在 `config/app.php` 中配置：

```php
'log' => [
    'dir' => __DIR__ . '/../logs',
    'enabled' => true,
    'level' => 'INFO',
    'max_file_size' => 10 * 1024 * 1024, // 10MB
    'max_files' => 10,
],
```

## 三、日志分析工具

### 3.1 概述

日志分析工具提供错误追踪、统计和告警功能。

**文件位置**: `lib/LogAnalyzer.php`

### 3.2 功能特性

- ✅ 按时间范围分析日志
- ✅ 错误趋势分析
- ✅ 最常见的错误统计
- ✅ 性能统计
- ✅ 告警检测

### 3.3 使用方法

```php
require_once __DIR__ . '/lib/LogAnalyzer.php';

$analyzer = new LogAnalyzer();

// 分析最近 24 小时的错误日志
$stats = $analyzer->analyze('error', 24);

// 获取最常见的错误
$topErrors = $analyzer->getTopErrors(10);

// 获取错误趋势
$trend = $analyzer->getErrorTrend(24);

// 检查告警
$alerts = $analyzer->checkAlerts(10); // 每小时错误阈值

// 获取性能统计
$performance = $analyzer->getPerformanceStats(24);
```

### 3.4 管理后台

访问 `admin/log_analysis.php` 查看日志分析界面：

- 日志统计概览
- 最常见的错误列表
- 错误趋势图表
- 性能统计
- 告警信息

## 四、APM（应用性能监控）

### 4.1 概述

APM 系统监控应用性能，包括响应时间、慢查询、错误率等。

**文件位置**: `lib/APM.php`

### 4.2 功能特性

- ✅ 请求响应时间监控
- ✅ 数据库查询监控
- ✅ 慢查询检测
- ✅ 错误记录
- ✅ 内存使用监控

### 4.3 使用方法

#### 4.3.1 初始化

在应用入口文件（如 `router.php` 或 `index.php`）的最开始处：

```php
require_once __DIR__ . '/lib/init_apm.php';
```

#### 4.3.2 手动记录

```php
require_once __DIR__ . '/lib/APM.php';

// 开始监控（通常在请求开始时）
APM::start();

// 记录数据库查询
$startTime = microtime(true);
// ... 执行查询 ...
$duration = microtime(true) - $startTime;
APM::recordQuery($sql, $duration);

// 记录错误
APM::recordError('错误消息', ['context' => 'value']);

// 结束监控（通常在请求结束时，自动调用）
APM::end();
```

### 4.4 管理后台

访问 `admin/apm_dashboard.php` 查看 APM 监控面板：

- 系统健康度评分
- 关键性能指标
- 响应时间统计
- 慢查询统计
- 告警信息
- 自动刷新（30秒）

### 4.5 配置

在 `config/app.php` 中配置：

```php
'apm' => [
    'enabled' => true,
    'slow_query_threshold' => 1.0, // 1秒
    'slow_request_threshold' => 3.0, // 3秒
],
```

## 五、内容 SEO 优化

### 5.1 概述

SEO 内容优化器提供内容优化建议和工具。

**文件位置**: `lib/SEOContentOptimizer.php`

### 5.2 功能特性

- ✅ 标题优化（长度、关键词）
- ✅ 描述优化（长度、内容）
- ✅ 内部链接建议
- ✅ SEO 评分报告

### 5.3 使用方法

```php
require_once __DIR__ . '/lib/SEOContentOptimizer.php';

// 优化标题
$result = SEOContentOptimizer::optimizeTitle('测试标题', 60);
echo $result['optimized']; // 优化后的标题
print_r($result['suggestions']); // 优化建议

// 优化描述
$result = SEOContentOptimizer::optimizeDescription('测试描述', 160);

// 生成内部链接建议
$suggestions = SEOContentOptimizer::suggestInternalLinks($currentTest, $allTests, 5);

// 生成 SEO 报告
$report = SEOContentOptimizer::generateReport($test);
echo "SEO 分数: " . $report['score'];
print_r($report['issues']); // 问题列表
print_r($report['suggestions']); // 建议列表
```

### 5.4 SEO 评分标准

- 标题长度：30-60 字符（±20 分）
- 描述长度：120-160 字符（±20 分）
- 封面图片：有/无（±10 分）
- URL slug：有/无（±15 分）
- 关键词数量：≥3 个（±10 分）

总分：100 分

## 六、页面速度优化

### 6.1 页面缓存

页面缓存系统缓存完整页面内容，减少数据库查询和渲染时间。

**文件位置**: `lib/PageCache.php`

### 6.2 功能特性

- ✅ 页面级别缓存
- ✅ 自动过期管理
- ✅ 缓存统计
- ✅ 按标签删除缓存

### 6.3 使用方法

```php
require_once __DIR__ . '/lib/PageCache.php';

// 获取缓存
$cached = PageCache::get('/test.php', ['slug' => 'xxx']);
if ($cached) {
    echo $cached;
    exit;
}

// 生成页面内容
ob_start();
// ... 页面内容 ...
$content = ob_get_clean();

// 保存缓存
PageCache::set('/test.php', ['slug' => 'xxx'], $content, 300);

echo $content;
```

### 6.4 配置

在 `config/app.php` 中配置：

```php
'cache' => [
    'page_dir' => __DIR__ . '/../cache/pages',
    'page_enabled' => true,
    'page_ttl' => 300, // 5 分钟
],
```

### 6.5 缓存管理

```php
// 删除特定页面缓存
PageCache::delete('/test.php', ['slug' => 'xxx']);

// 按标签删除缓存
PageCache::deleteByTag('test');

// 清空所有缓存
PageCache::clear();

// 获取缓存统计
$stats = PageCache::getStats();
```

## 七、最佳实践

### 7.1 日志记录

1. **使用结构化日志**：优先使用 `StructuredLogger` 而不是 `ErrorHandler::logError`
2. **记录上下文**：提供足够的上下文信息便于问题排查
3. **日志级别**：合理使用日志级别（DEBUG < INFO < WARNING < ERROR）

### 7.2 性能监控

1. **启用 APM**：在入口文件引入 `init_apm.php`
2. **监控慢查询**：定期检查慢查询日志
3. **设置告警**：配置错误阈值和告警

### 7.3 SEO 优化

1. **定期检查**：使用 `SEOContentOptimizer::generateReport()` 检查内容
2. **优化标题和描述**：确保长度在推荐范围内
3. **添加内部链接**：使用 `suggestInternalLinks()` 生成相关链接

### 7.4 缓存策略

1. **页面缓存**：对不经常变化的页面启用缓存
2. **缓存时间**：根据内容更新频率设置 TTL
3. **缓存失效**：内容更新时及时清除相关缓存

## 八、故障排查

### 8.1 日志文件过大

- 检查日志轮转配置
- 手动清理旧日志文件
- 调整日志级别（减少 DEBUG 日志）

### 8.2 APM 数据不准确

- 确保在入口文件引入了 `init_apm.php`
- 检查 APM 是否启用（`APM::setEnabled(true)`）
- 查看日志文件确认是否有记录

### 8.3 页面缓存不生效

- 检查缓存目录权限
- 确认缓存已启用（`PageCache::setEnabled(true)`）
- 检查缓存 TTL 设置

## 九、未来扩展

可以考虑添加：

- 日志聚合和分析（如 ELK Stack）
- 实时告警通知（邮件、短信、Webhook）
- 性能基准测试
- A/B 测试支持
- 更多 SEO 工具（关键词分析、竞争分析等）

---

**文档维护**: 建议定期更新此文档，记录新的功能和最佳实践。

