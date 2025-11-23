<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../config/app.php';
require_once __DIR__ . '/../lib/Cache.php';
require_once __DIR__ . '/../lib/PageCache.php';
require_once __DIR__ . '/../lib/APM.php';

$pageTitle = '性能优化';
$pageSubtitle = '缓存、APM 和性能指标总览';
$activeMenu = 'system';

$config = require __DIR__ . '/../config/app.php';

// 获取缓存统计
$cacheStats = Cache::getStats();
$pageCacheStats = PageCache::getStats();

// 获取 APM 统计（如果方法存在）
$apmStats = method_exists('APM', 'getStats') ? APM::getStats() : [
    'total_requests' => 0,
    'avg_response_time' => 0,
    'max_response_time' => 0,
    'total_queries' => 0,
    'avg_query_time' => 0,
    'slow_queries' => 0,
    'error_count' => 0,
];

// 计算缓存命中率（如果有数据）
$cacheHitRate = 0;
if (isset($cacheStats['apcu_info']['num_hits']) && isset($cacheStats['apcu_info']['num_misses'])) {
    $total = $cacheStats['apcu_info']['num_hits'] + $cacheStats['apcu_info']['num_misses'];
    if ($total > 0) {
        $cacheHitRate = round(($cacheStats['apcu_info']['num_hits'] / $total) * 100, 1);
    }
}

// 获取系统信息
$memoryUsage = memory_get_usage(true);
$memoryPeak = memory_get_peak_usage(true);
$memoryLimit = ini_get('memory_limit');

// 转换内存限制为字节
function parseMemoryLimit($limit) {
    $limit = trim($limit);
    $last = strtolower($limit[strlen($limit)-1]);
    $value = (int)$limit;
    switch($last) {
        case 'g': $value *= 1024;
        case 'm': $value *= 1024;
        case 'k': $value *= 1024;
    }
    return $value;
}

$memoryLimitBytes = parseMemoryLimit($memoryLimit);
$memoryUsagePercent = $memoryLimitBytes > 0 ? round(($memoryUsage / $memoryLimitBytes) * 100, 1) : 0;

// 获取 OPcache 状态（如果启用）
$opcacheEnabled = function_exists('opcache_get_status');
$opcacheStats = null;
if ($opcacheEnabled) {
    $opcacheStats = opcache_get_status(false);
}

// 获取文件缓存目录大小
$cacheDir = __DIR__ . '/../cache';
$cacheDirSize = 0;
$cacheFileCount = 0;
if (is_dir($cacheDir)) {
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($cacheDir),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
        if ($file->isFile()) {
            $cacheDirSize += $file->getSize();
            $cacheFileCount++;
        }
    }
}

ob_start();
?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <span class="admin-table__muted">监控系统性能指标，优化缓存策略，提升响应速度。</span>
    </div>
    <div class="admin-toolbar__right">
        <a href="cache_manage.php" class="btn btn-primary">缓存管理</a>
        <a href="apm_dashboard.php" class="btn btn-primary">APM 监控</a>
    </div>
</div>

<!-- 性能指标概览 -->
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">性能指标概览</h2>
    <table class="admin-table admin-table--kpi">
        <tbody>
        <tr>
            <td>
                <div class="admin-kpi-number" style="color: <?= $cacheHitRate >= 80 ? '#34d399' : ($cacheHitRate >= 50 ? '#f59e0b' : '#ef4444') ?>;">
                    <?= $cacheHitRate ?>%
                </div>
                <div class="admin-kpi-label">缓存命中率</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: <?= $apmStats['avg_response_time'] < 1 ? '#34d399' : ($apmStats['avg_response_time'] < 3 ? '#f59e0b' : '#ef4444') ?>;">
                    <?= number_format($apmStats['avg_response_time'] ?? 0, 2) ?>s
                </div>
                <div class="admin-kpi-label">平均响应时间</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: <?= $memoryUsagePercent < 70 ? '#34d399' : ($memoryUsagePercent < 90 ? '#f59e0b' : '#ef4444') ?>;">
                    <?= $memoryUsagePercent ?>%
                </div>
                <div class="admin-kpi-label">内存使用率</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: <?= ($apmStats['error_count'] ?? 0) === 0 ? '#34d399' : '#ef4444' ?>;">
                    <?= $apmStats['error_count'] ?? 0 ?>
                </div>
                <div class="admin-kpi-label">错误数量</div>
            </td>
        </tr>
        </tbody>
    </table>
</div>

<!-- 缓存系统状态 -->
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">缓存系统状态</h2>
    
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 16px; margin-bottom: 16px;">
        <!-- 多层级缓存 -->
        <div style="background: #1f2937; padding: 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #9ca3af; margin-bottom: 8px;">多层级缓存 (Cache)</div>
            <div style="font-size: 20px; font-weight: bold; color: #e5e7eb; margin-bottom: 4px;">
                <?php
                $cacheSize = 0;
                if (isset($cacheStats['apcu_info']['mem_size'])) {
                    $cacheSize = $cacheStats['apcu_info']['mem_size'];
                }
                if ($cacheSize > 1024 * 1024) {
                    echo number_format($cacheSize / (1024 * 1024), 2) . ' MB';
                } elseif ($cacheSize > 1024) {
                    echo number_format($cacheSize / 1024, 2) . ' KB';
                } else {
                    echo number_format($cacheSize) . ' B';
                }
                ?>
            </div>
            <div style="font-size: 11px; color: #6b7280;">
                <?php if (isset($cacheStats['apcu_info'])): ?>
                    命中: <?= $cacheStats['apcu_info']['num_hits'] ?? 0 ?> · 
                    未命中: <?= $cacheStats['apcu_info']['num_misses'] ?? 0 ?>
                <?php else: ?>
                    文件数: <?= $cacheStats['file_count'] ?? 0 ?>
                <?php endif; ?>
            </div>
            <div style="margin-top: 8px; font-size: 11px;">
                <?php if ($cacheStats['apcu_enabled'] ?? false): ?>
                    <span style="color: #34d399;">✓ APCu 已启用</span>
                <?php else: ?>
                    <span style="color: #9ca3af;">✗ APCu 未启用</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- 页面缓存 -->
        <div style="background: #1f2937; padding: 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #9ca3af; margin-bottom: 8px;">页面缓存 (PageCache)</div>
            <div style="font-size: 20px; font-weight: bold; color: #e5e7eb; margin-bottom: 4px;">
                <?php
                $pageCacheSize = $pageCacheStats['total_size'] ?? 0;
                if ($pageCacheSize > 1024 * 1024) {
                    echo number_format($pageCacheSize / (1024 * 1024), 2) . ' MB';
                } elseif ($pageCacheSize > 1024) {
                    echo number_format($pageCacheSize / 1024, 2) . ' KB';
                } else {
                    echo $pageCacheSize . ' B';
                }
                ?>
            </div>
            <div style="font-size: 11px; color: #6b7280;">
                文件数: <?= $pageCacheStats['total_files'] ?? 0 ?> · 
                有效: <?= $pageCacheStats['valid_files'] ?? 0 ?> · 
                过期: <?= $pageCacheStats['expired_files'] ?? 0 ?>
            </div>
            <div style="margin-top: 8px; font-size: 11px;">
                <?php if ($config['cache']['page_enabled'] ?? false): ?>
                    <span style="color: #34d399;">✓ 页面缓存已启用</span>
                <?php else: ?>
                    <span style="color: #9ca3af;">✗ 页面缓存未启用</span>
                <?php endif; ?>
            </div>
        </div>

        <!-- 缓存目录总大小 -->
        <div style="background: #1f2937; padding: 16px; border-radius: 6px;">
            <div style="font-size: 12px; color: #9ca3af; margin-bottom: 8px;">缓存目录总大小</div>
            <div style="font-size: 20px; font-weight: bold; color: #e5e7eb; margin-bottom: 4px;">
                <?php
                if ($cacheDirSize > 1024 * 1024) {
                    echo number_format($cacheDirSize / (1024 * 1024), 2) . ' MB';
                } elseif ($cacheDirSize > 1024) {
                    echo number_format($cacheDirSize / 1024, 2) . ' KB';
                } else {
                    echo $cacheDirSize . ' B';
                }
                ?>
            </div>
            <div style="font-size: 11px; color: #6b7280;">
                文件数: <?= $cacheFileCount ?>
            </div>
        </div>
    </div>

    <div style="margin-top: 16px;">
        <a href="cache_manage.php" class="btn btn-xs" style="background: #3b82f6; color: #fff; border: none;">管理缓存</a>
    </div>
</div>

<!-- APM 监控状态 -->
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">APM 监控状态</h2>
    
    <table class="admin-table admin-table--compact">
        <tbody>
        <tr>
            <td style="width: 200px; color: #9ca3af;">总请求数</td>
            <td><code class="code-badge"><?= number_format($apmStats['total_requests'] ?? 0) ?></code></td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">平均响应时间</td>
            <td>
                <code class="code-badge" style="color: <?= ($apmStats['avg_response_time'] ?? 0) < 1 ? '#34d399' : '#f59e0b' ?>;">
                    <?= number_format($apmStats['avg_response_time'] ?? 0, 3) ?> 秒
                </code>
            </td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">最慢响应时间</td>
            <td>
                <code class="code-badge" style="color: <?= ($apmStats['max_response_time'] ?? 0) < 3 ? '#34d399' : '#ef4444' ?>;">
                    <?= number_format($apmStats['max_response_time'] ?? 0, 3) ?> 秒
                </code>
            </td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">总查询数</td>
            <td><code class="code-badge"><?= number_format($apmStats['total_queries'] ?? 0) ?></code></td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">平均查询时间</td>
            <td>
                <code class="code-badge" style="color: <?= ($apmStats['avg_query_time'] ?? 0) < 0.1 ? '#34d399' : '#f59e0b' ?>;">
                    <?= number_format($apmStats['avg_query_time'] ?? 0, 3) ?> 秒
                </code>
            </td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">慢查询数 (>1秒)</td>
            <td>
                <code class="code-badge" style="color: <?= ($apmStats['slow_queries'] ?? 0) === 0 ? '#34d399' : '#ef4444' ?>;">
                    <?= $apmStats['slow_queries'] ?? 0 ?>
                </code>
            </td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">错误数量</td>
            <td>
                <code class="code-badge" style="color: <?= ($apmStats['error_count'] ?? 0) === 0 ? '#34d399' : '#ef4444' ?>;">
                    <?= $apmStats['error_count'] ?? 0 ?>
                </code>
            </td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">APM 状态</td>
            <td>
                <?php if ($config['apm']['enabled'] ?? false): ?>
                    <span style="color: #34d399;">✓ 已启用</span>
                <?php else: ?>
                    <span style="color: #9ca3af;">✗ 未启用</span>
                <?php endif; ?>
            </td>
        </tr>
        </tbody>
    </table>

    <div style="margin-top: 16px;">
        <a href="apm_dashboard.php" class="btn btn-xs" style="background: #3b82f6; color: #fff; border: none;">查看详细监控</a>
    </div>
</div>

<!-- 系统资源 -->
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">系统资源</h2>
    
    <table class="admin-table admin-table--compact">
        <tbody>
        <tr>
            <td style="width: 200px; color: #9ca3af;">内存使用</td>
            <td>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="flex: 1; background: #374151; height: 8px; border-radius: 4px; overflow: hidden;">
                        <div style="background: <?= $memoryUsagePercent < 70 ? '#34d399' : ($memoryUsagePercent < 90 ? '#f59e0b' : '#ef4444') ?>; 
                                    height: 100%; width: <?= $memoryUsagePercent ?>%;"></div>
                    </div>
                    <div style="font-size: 12px; color: #e5e7eb; white-space: nowrap;">
                        <?php
                        if ($memoryUsage > 1024 * 1024) {
                            echo number_format($memoryUsage / (1024 * 1024), 2) . ' MB';
                        } elseif ($memoryUsage > 1024) {
                            echo number_format($memoryUsage / 1024, 2) . ' KB';
                        } else {
                            echo $memoryUsage . ' B';
                        }
                        ?>
                        / 
                        <?php
                        if ($memoryLimitBytes > 1024 * 1024) {
                            echo number_format($memoryLimitBytes / (1024 * 1024), 2) . ' MB';
                        } elseif ($memoryLimitBytes > 1024) {
                            echo number_format($memoryLimitBytes / 1024, 2) . ' KB';
                        } else {
                            echo $memoryLimitBytes . ' B';
                        }
                        ?>
                        (<?= $memoryUsagePercent ?>%)
                    </div>
                </div>
            </td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">内存峰值</td>
            <td>
                <code class="code-badge">
                    <?php
                    if ($memoryPeak > 1024 * 1024) {
                        echo number_format($memoryPeak / (1024 * 1024), 2) . ' MB';
                    } elseif ($memoryPeak > 1024) {
                        echo number_format($memoryPeak / 1024, 2) . ' KB';
                    } else {
                        echo $memoryPeak . ' B';
                    }
                    ?>
                </code>
            </td>
        </tr>
        <?php if ($opcacheEnabled && $opcacheStats): ?>
        <tr>
            <td style="color: #9ca3af;">OPcache 状态</td>
            <td>
                <?php if ($opcacheStats['opcache_enabled']): ?>
                    <span style="color: #34d399;">✓ 已启用</span>
                    <span style="color: #9ca3af; margin-left: 16px;">
                        命中率: <?= isset($opcacheStats['opcache_statistics']) ? number_format($opcacheStats['opcache_statistics']['opcache_hit_rate'], 1) : 'N/A' ?>%
                    </span>
                <?php else: ?>
                    <span style="color: #9ca3af;">✗ 未启用</span>
                <?php endif; ?>
            </td>
        </tr>
        <?php endif; ?>
        <tr>
            <td style="color: #9ca3af;">PHP 版本</td>
            <td><code class="code-badge"><?= PHP_VERSION ?></code></td>
        </tr>
        </tbody>
    </table>
</div>

<!-- 性能优化建议 -->
<div class="admin-card">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">性能优化建议</h2>
    
    <div style="background: #1f2937; padding: 16px; border-radius: 6px;">
        <?php
        $suggestions = [];
        
        // 缓存命中率建议
        if ($cacheHitRate < 50) {
            $suggestions[] = [
                'level' => 'warning',
                'message' => '缓存命中率较低（' . $cacheHitRate . '%），建议检查缓存策略和 TTL 设置',
            ];
        }
        
        // 响应时间建议
        if (($apmStats['avg_response_time'] ?? 0) > 2) {
            $suggestions[] = [
                'level' => 'warning',
                'message' => '平均响应时间较慢（' . number_format($apmStats['avg_response_time'], 2) . '秒），建议优化数据库查询和启用页面缓存',
            ];
        }
        
        // 慢查询建议
        if (($apmStats['slow_queries'] ?? 0) > 0) {
            $suggestions[] = [
                'level' => 'error',
                'message' => '发现 ' . $apmStats['slow_queries'] . ' 个慢查询，建议优化数据库索引和查询语句',
            ];
        }
        
        // 内存使用建议
        if ($memoryUsagePercent > 80) {
            $suggestions[] = [
                'level' => 'warning',
                'message' => '内存使用率较高（' . $memoryUsagePercent . '%），建议检查内存泄漏或增加内存限制',
            ];
        }
        
        // OPcache 建议
        if (!$opcacheEnabled || ($opcacheStats && !$opcacheStats['opcache_enabled'])) {
            $suggestions[] = [
                'level' => 'info',
                'message' => '建议启用 OPcache 以提升 PHP 性能',
            ];
        }
        
        // 页面缓存建议
        if (!($config['cache']['page_enabled'] ?? false)) {
            $suggestions[] = [
                'level' => 'info',
                'message' => '建议启用页面缓存以提升首屏加载速度',
            ];
        }
        
        if (empty($suggestions)) {
            echo '<div style="color: #34d399; font-size: 14px;">✓ 系统性能良好，暂无优化建议</div>';
        } else {
            foreach ($suggestions as $suggestion) {
                $color = $suggestion['level'] === 'error' ? '#ef4444' : ($suggestion['level'] === 'warning' ? '#f59e0b' : '#60a5fa');
                echo '<div style="color: ' . $color . '; font-size: 13px; margin-bottom: 8px; padding-left: 20px; position: relative;">';
                echo '<span style="position: absolute; left: 0;">' . ($suggestion['level'] === 'error' ? '✗' : ($suggestion['level'] === 'warning' ? '⚠' : 'ℹ')) . '</span>';
                echo htmlspecialchars($suggestion['message']);
                echo '</div>';
            }
        }
        ?>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

