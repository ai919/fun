<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/APM.php';
require_once __DIR__ . '/../lib/LogAnalyzer.php';
require_once __DIR__ . '/../lib/SEOContentOptimizer.php';
require_once __DIR__ . '/../lib/PageCache.php';
require_once __DIR__ . '/../config/app.php';

$pageTitle = '优化建议中心';
$pageSubtitle = '整合所有优化建议和系统健康度检查';
$activeMenu = 'system';

$config = require __DIR__ . '/../config/app.php';

// 收集所有优化建议
$suggestions = [];

// 1. APM 性能建议
try {
    $apmHealth = APM::getHealth();
    if ($apmHealth['score'] < 80) {
        $suggestions[] = [
            'category' => '性能监控',
            'level' => 'warning',
            'title' => '系统健康度较低',
            'message' => '当前系统健康度评分：' . $apmHealth['score'] . '/100。建议检查慢查询和错误日志。',
            'action' => '查看 APM 监控',
            'url' => 'apm_dashboard.php',
        ];
    }
    
    if (($apmHealth['slow_queries'] ?? 0) > 0) {
        $suggestions[] = [
            'category' => '性能监控',
            'level' => 'error',
            'title' => '发现慢查询',
            'message' => '检测到 ' . $apmHealth['slow_queries'] . ' 个慢查询（>1秒），建议优化数据库索引。',
            'action' => '查看查询日志',
            'url' => 'db_query_logs.php',
        ];
    }
    
    if (($apmHealth['avg_response_time'] ?? 0) > 2) {
        $suggestions[] = [
            'category' => '性能监控',
            'level' => 'warning',
            'title' => '响应时间较慢',
            'message' => '平均响应时间：' . number_format($apmHealth['avg_response_time'], 2) . '秒，建议启用页面缓存。',
            'action' => '查看性能优化',
            'url' => 'performance.php',
        ];
    }
} catch (Exception $e) {
    // APM 未启用或出错
}

// 2. 日志分析建议
try {
    $logDir = $config['log']['dir'] ?? __DIR__ . '/../logs';
    $analyzer = new LogAnalyzer($logDir);
    $recentErrors = $analyzer->getRecentErrors(24); // 最近24小时
    
    if (count($recentErrors) > 10) {
        $suggestions[] = [
            'category' => '日志分析',
            'level' => 'error',
            'title' => '错误日志较多',
            'message' => '最近24小时发现 ' . count($recentErrors) . ' 个错误，建议立即检查。',
            'action' => '查看日志分析',
            'url' => 'log_analysis.php',
        ];
    }
    
    $alerts = $analyzer->getAlerts(24);
    if (!empty($alerts)) {
        foreach ($alerts as $alert) {
            $suggestions[] = [
                'category' => '日志分析',
                'level' => $alert['level'],
                'title' => $alert['message'],
                'message' => $alert['details'] ?? '',
                'action' => '查看详情',
                'url' => 'log_analysis.php',
            ];
        }
    }
} catch (Exception $e) {
    // 日志分析出错
}

// 3. SEO 优化建议
try {
    $tests = $pdo->query("
        SELECT id, title, description, slug, cover_image, status
        FROM tests
        WHERE status = 'published'
        LIMIT 50
    ")->fetchAll(PDO::FETCH_ASSOC);
    
    $lowScoreTests = [];
    foreach ($tests as $test) {
        $report = SEOContentOptimizer::generateReport($test);
        if ($report['score'] < 70) {
            $lowScoreTests[] = [
                'id' => $test['id'],
                'title' => $test['title'],
                'score' => $report['score'],
            ];
        }
    }
    
    if (count($lowScoreTests) > 0) {
        $suggestions[] = [
            'category' => 'SEO 优化',
            'level' => 'warning',
            'title' => '部分测验 SEO 分数较低',
            'message' => '发现 ' . count($lowScoreTests) . ' 个测验 SEO 分数低于 70 分，建议优化标题和描述。',
            'action' => '查看 SEO 优化',
            'url' => 'seo_optimizer.php',
        ];
    }
    
    // 检查是否有测验缺少 slug
    $testsWithoutSlug = $pdo->query("
        SELECT COUNT(*) FROM tests WHERE (slug IS NULL OR slug = '') AND status = 'published'
    ")->fetchColumn();
    
    if ($testsWithoutSlug > 0) {
        $suggestions[] = [
            'category' => 'SEO 优化',
            'level' => 'warning',
            'title' => '部分测验缺少 Slug',
            'message' => '发现 ' . $testsWithoutSlug . ' 个已发布测验缺少 URL Slug，影响 SEO 和 URL 重定向。',
            'action' => '查看 URL 重定向',
            'url' => 'url_redirects.php',
        ];
    }
} catch (Exception $e) {
    // SEO 分析出错
}

// 4. 缓存优化建议
try {
    $pageCacheStats = PageCache::getStats();
    $cacheEnabled = $config['cache']['page_enabled'] ?? false;
    
    if (!$cacheEnabled) {
        $suggestions[] = [
            'category' => '缓存优化',
            'level' => 'info',
            'title' => '页面缓存未启用',
            'message' => '建议启用页面缓存以提升页面加载速度。',
            'action' => '查看缓存管理',
            'url' => 'page_cache_manage.php',
        ];
    }
    
    if ($cacheEnabled && ($pageCacheStats['expired_files'] ?? 0) > 10) {
        $suggestions[] = [
            'category' => '缓存优化',
            'level' => 'info',
            'title' => '有过期缓存文件',
            'message' => '发现 ' . $pageCacheStats['expired_files'] . ' 个过期缓存文件，建议清理以释放空间。',
            'action' => '管理页面缓存',
            'url' => 'page_cache_manage.php',
        ];
    }
} catch (Exception $e) {
    // 缓存检查出错
}

// 5. 系统配置建议
if (!($config['apm']['enabled'] ?? false)) {
    $suggestions[] = [
        'category' => '系统配置',
        'level' => 'info',
        'title' => 'APM 监控未启用',
        'message' => '建议启用 APM 监控以实时了解系统性能。',
        'action' => '查看系统配置',
        'url' => 'system_config.php',
    ];
}

// 按级别和类别排序
usort($suggestions, function($a, $b) {
    $levelOrder = ['error' => 0, 'warning' => 1, 'info' => 2];
    $levelDiff = ($levelOrder[$a['level']] ?? 99) - ($levelOrder[$b['level'] ?? 99]);
    if ($levelDiff !== 0) {
        return $levelDiff;
    }
    return strcmp($a['category'], $b['category']);
});

// 统计
$suggestionStats = [
    'total' => count($suggestions),
    'error' => count(array_filter($suggestions, fn($s) => $s['level'] === 'error')),
    'warning' => count(array_filter($suggestions, fn($s) => $s['level'] === 'warning')),
    'info' => count(array_filter($suggestions, fn($s) => $s['level'] === 'info')),
];

ob_start();
?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <span class="admin-table__muted">整合所有优化建议，帮助您快速发现和解决系统问题。</span>
    </div>
</div>

<!-- 建议统计 -->
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">优化建议统计</h2>
    <table class="admin-table admin-table--kpi">
        <tbody>
        <tr>
            <td>
                <div class="admin-kpi-number"><?= $suggestionStats['total'] ?></div>
                <div class="admin-kpi-label">总建议数</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: #ef4444;"><?= $suggestionStats['error'] ?></div>
                <div class="admin-kpi-label">错误级别</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: #f59e0b;"><?= $suggestionStats['warning'] ?></div>
                <div class="admin-kpi-label">警告级别</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: #60a5fa;"><?= $suggestionStats['info'] ?></div>
                <div class="admin-kpi-label">信息级别</div>
            </td>
        </tr>
        </tbody>
    </table>
</div>

<!-- 优化建议列表 -->
<?php if (empty($suggestions)): ?>
    <div class="admin-card">
        <div style="padding: 60px; text-align: center; color: #9ca3af;">
            <div style="font-size: 64px; margin-bottom: 16px;">✅</div>
            <div style="font-size: 18px; font-weight: 600; color: #e5e7eb; margin-bottom: 8px;">
                系统运行良好
            </div>
            <div>暂无优化建议，系统状态正常。</div>
        </div>
    </div>
<?php else: ?>
    <div class="admin-card">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">优化建议列表</h2>
        
        <?php
        $currentCategory = '';
        foreach ($suggestions as $suggestion):
            if ($currentCategory !== $suggestion['category']):
                if ($currentCategory !== ''):
                    echo '</div>'; // 关闭上一个分类
                endif;
                $currentCategory = $suggestion['category'];
        ?>
            <div style="margin-bottom: 24px;">
                <h3 style="font-size: 14px; font-weight: 600; color: #e5e7eb; margin-bottom: 12px; padding-bottom: 8px; border-bottom: 1px solid #374151;">
                    <?= htmlspecialchars($suggestion['category']) ?>
                </h3>
        <?php endif; ?>
        
        <div style="background: #1f2937; padding: 16px; border-radius: 6px; margin-bottom: 12px; border-left: 4px solid <?= $suggestion['level'] === 'error' ? '#ef4444' : ($suggestion['level'] === 'warning' ? '#f59e0b' : '#60a5fa') ?>;">
            <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 8px;">
                <div style="flex: 1;">
                    <div style="display: flex; align-items: center; gap: 8px; margin-bottom: 8px;">
                        <span style="font-size: 18px;">
                            <?= $suggestion['level'] === 'error' ? '✗' : ($suggestion['level'] === 'warning' ? '⚠' : 'ℹ') ?>
                        </span>
                        <h4 style="font-size: 15px; font-weight: 600; color: #e5e7eb; margin: 0;">
                            <?= htmlspecialchars($suggestion['title']) ?>
                        </h4>
                    </div>
                    <div style="font-size: 13px; color: #d1d5db; line-height: 1.6;">
                        <?= htmlspecialchars($suggestion['message']) ?>
                    </div>
                </div>
                <?php if (!empty($suggestion['url'])): ?>
                    <a href="<?= htmlspecialchars($suggestion['url']) ?>" 
                       class="btn btn-xs"
                       style="background: #3b82f6; color: #fff; border: none; white-space: nowrap; margin-left: 16px;">
                        <?= htmlspecialchars($suggestion['action']) ?>
                    </a>
                <?php endif; ?>
            </div>
        </div>
        
        <?php endforeach; ?>
        <?php if ($currentCategory !== ''): ?>
            </div> <!-- 关闭最后一个分类 -->
        <?php endif; ?>
    </div>
<?php endif; ?>

<!-- 快速链接 -->
<div class="admin-card" style="margin-top: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">相关工具</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
        <a href="apm_dashboard.php" class="btn btn-xs" style="background: #3b82f6; color: #fff; border: none; text-align: center; padding: 12px;">
            APM 监控
        </a>
        <a href="log_analysis.php" class="btn btn-xs" style="background: #3b82f6; color: #fff; border: none; text-align: center; padding: 12px;">
            日志分析
        </a>
        <a href="seo_optimizer.php" class="btn btn-xs" style="background: #3b82f6; color: #fff; border: none; text-align: center; padding: 12px;">
            SEO 优化
        </a>
        <a href="performance.php" class="btn btn-xs" style="background: #3b82f6; color: #fff; border: none; text-align: center; padding: 12px;">
            性能优化
        </a>
        <a href="page_cache_manage.php" class="btn btn-xs" style="background: #3b82f6; color: #fff; border: none; text-align: center; padding: 12px;">
            页面缓存
        </a>
        <a href="url_redirects.php" class="btn btn-xs" style="background: #3b82f6; color: #fff; border: none; text-align: center; padding: 12px;">
            URL 重定向
        </a>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

