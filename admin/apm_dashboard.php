<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/LogAnalyzer.php';

$pageTitle = 'APM 监控面板';
$pageSubtitle = '应用性能监控和实时指标';
$activeMenu = 'system';

$analyzer = new LogAnalyzer();

// 获取时间范围
$hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 1;
$hours = max(1, min(24, $hours));

// 获取性能统计
$performanceStats = $analyzer->getPerformanceStats($hours);
$errorStats = $analyzer->analyze('error', $hours);
$warningStats = $analyzer->analyze('warning', $hours);
$alerts = $analyzer->checkAlerts(10);

ob_start();
?>
    <div class="admin-header">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p class="admin-subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
        <div class="auto-refresh">
            <label>
                <input type="checkbox" id="autoRefresh" checked>
                自动刷新（30秒）
            </label>
        </div>
    </div>

    <!-- 系统健康状态 -->
    <div class="health-status">
        <?php
        $healthScore = 100;
        if ($performanceStats['avg_duration'] > 2.0) $healthScore -= 20;
        if ($errorStats['total'] > 10) $healthScore -= 30;
        if ($performanceStats['slow_requests'] > 5) $healthScore -= 20;
        
        $healthLevel = $healthScore >= 80 ? 'good' : ($healthScore >= 60 ? 'warning' : 'error');
        ?>
        <div class="health-indicator health-<?= $healthLevel ?>">
            <div class="health-score"><?= $healthScore ?></div>
            <div class="health-label">系统健康度</div>
        </div>
    </div>

    <!-- 关键指标 -->
    <div class="metrics-grid">
        <div class="metric-card">
            <div class="metric-icon">⚡</div>
            <div class="metric-content">
                <div class="metric-value"><?= number_format($performanceStats['avg_duration'] ?? 0, 3) ?>s</div>
                <div class="metric-label">平均响应时间</div>
                <div class="metric-trend">
                    <?php if (($performanceStats['avg_duration'] ?? 0) > 2.0): ?>
                    <span class="trend-up">⚠️ 较慢</span>
                    <?php else: ?>
                    <span class="trend-down">✓ 正常</span>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon">📊</div>
            <div class="metric-content">
                <div class="metric-value"><?= number_format($performanceStats['total_requests'] ?? 0) ?></div>
                <div class="metric-label">总请求数</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon">🐌</div>
            <div class="metric-content">
                <div class="metric-value"><?= $performanceStats['slow_requests'] ?? 0 ?></div>
                <div class="metric-label">慢请求（>3s）</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon">❌</div>
            <div class="metric-content">
                <div class="metric-value"><?= $errorStats['total'] ?? 0 ?></div>
                <div class="metric-label">错误数</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon">⚠️</div>
            <div class="metric-content">
                <div class="metric-value"><?= $warningStats['total'] ?? 0 ?></div>
                <div class="metric-label">警告数</div>
            </div>
        </div>

        <div class="metric-card">
            <div class="metric-icon">🗄️</div>
            <div class="metric-content">
                <div class="metric-value"><?= number_format($performanceStats['query_stats']['total'] ?? 0) ?></div>
                <div class="metric-label">数据库查询</div>
            </div>
        </div>
    </div>

    <!-- 告警 -->
    <?php if (!empty($alerts)): ?>
    <div class="alerts-section">
        <h2>告警</h2>
        <?php foreach ($alerts as $alert): ?>
        <div class="alert alert-<?= $alert['level'] === 'high' ? 'error' : 'warning' ?>">
            <strong><?= htmlspecialchars($alert['message']) ?></strong>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 性能详情 -->
    <div class="section">
        <h2>性能详情</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>指标</th>
                    <th>值</th>
                    <th>状态</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>平均响应时间</td>
                    <td><?= number_format($performanceStats['avg_duration'] ?? 0, 3) ?>s</td>
                    <td>
                        <?php if (($performanceStats['avg_duration'] ?? 0) > 2.0): ?>
                        <span class="badge badge-warning">警告</span>
                        <?php else: ?>
                        <span class="badge badge-success">正常</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>最大响应时间</td>
                    <td><?= number_format($performanceStats['max_duration'] ?? 0, 3) ?>s</td>
                    <td>
                        <?php if (($performanceStats['max_duration'] ?? 0) > 5.0): ?>
                        <span class="badge badge-error">严重</span>
                        <?php elseif (($performanceStats['max_duration'] ?? 0) > 3.0): ?>
                        <span class="badge badge-warning">警告</span>
                        <?php else: ?>
                        <span class="badge badge-success">正常</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>最小响应时间</td>
                    <td><?= number_format($performanceStats['min_duration'] ?? 0, 3) ?>s</td>
                    <td><span class="badge badge-info">正常</span></td>
                </tr>
                <tr>
                    <td>慢请求数（>3s）</td>
                    <td><?= $performanceStats['slow_requests'] ?? 0 ?></td>
                    <td>
                        <?php if (($performanceStats['slow_requests'] ?? 0) > 10): ?>
                        <span class="badge badge-error">严重</span>
                        <?php elseif (($performanceStats['slow_requests'] ?? 0) > 5): ?>
                        <span class="badge badge-warning">警告</span>
                        <?php else: ?>
                        <span class="badge badge-success">正常</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>平均查询时间</td>
                    <td><?= number_format($performanceStats['query_stats']['avg_duration'] ?? 0, 3) ?>s</td>
                    <td>
                        <?php if (($performanceStats['query_stats']['avg_duration'] ?? 0) > 1.0): ?>
                        <span class="badge badge-warning">警告</span>
                        <?php else: ?>
                        <span class="badge badge-success">正常</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <tr>
                    <td>慢查询数（>1s）</td>
                    <td><?= $performanceStats['query_stats']['slow_queries'] ?? 0 ?></td>
                    <td>
                        <?php if (($performanceStats['query_stats']['slow_queries'] ?? 0) > 5): ?>
                        <span class="badge badge-error">严重</span>
                        <?php elseif (($performanceStats['query_stats']['slow_queries'] ?? 0) > 0): ?>
                        <span class="badge badge-warning">警告</span>
                        <?php else: ?>
                        <span class="badge badge-success">正常</span>
                        <?php endif; ?>
                    </td>
                </tr>
            </tbody>
        </table>
    </div>

<script>
// 自动刷新
let autoRefreshInterval;
const autoRefreshCheckbox = document.getElementById('autoRefresh');

function startAutoRefresh() {
    if (autoRefreshInterval) {
        clearInterval(autoRefreshInterval);
    }
    if (autoRefreshCheckbox.checked) {
        autoRefreshInterval = setInterval(() => {
            window.location.reload();
        }, 30000); // 30秒
    }
}

autoRefreshCheckbox.addEventListener('change', startAutoRefresh);
startAutoRefresh();
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>

