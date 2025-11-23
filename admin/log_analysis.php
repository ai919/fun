<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/LogAnalyzer.php';

$pageTitle = '日志分析';
$pageSubtitle = '分析系统日志，追踪错误和性能问题';
$activeMenu = 'system';

$analyzer = new LogAnalyzer();

// 获取时间范围
$hours = isset($_GET['hours']) ? (int)$_GET['hours'] : 24;
$hours = max(1, min(168, $hours)); // 1-168 小时（7天）

// 获取日志级别
$level = $_GET['level'] ?? 'error';
$allowedLevels = ['error', 'warning', 'info', 'debug'];
if (!in_array($level, $allowedLevels)) {
    $level = 'error';
}

// 分析日志
$stats = $analyzer->analyze($level, $hours);
$topErrors = $analyzer->getTopErrors(10);
$errorTrend = $analyzer->getErrorTrend($hours);
$alerts = $analyzer->checkAlerts(10);
$performanceStats = $analyzer->getPerformanceStats($hours);

ob_start();
?>
    <div class="admin-header">
        <h1><?= htmlspecialchars($pageTitle) ?></h1>
        <p class="admin-subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
    </div>

    <!-- 过滤器 -->
    <div class="filter-bar">
        <form method="get" class="filter-form">
            <label>
                日志级别：
                <select name="level">
                    <?php foreach ($allowedLevels as $l): ?>
                    <option value="<?= $l ?>" <?= $level === $l ? 'selected' : '' ?>>
                        <?= ucfirst($l) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                时间范围：
                <select name="hours">
                    <option value="1" <?= $hours === 1 ? 'selected' : '' ?>>最近 1 小时</option>
                    <option value="6" <?= $hours === 6 ? 'selected' : '' ?>>最近 6 小时</option>
                    <option value="24" <?= $hours === 24 ? 'selected' : '' ?>>最近 24 小时</option>
                    <option value="72" <?= $hours === 72 ? 'selected' : '' ?>>最近 3 天</option>
                    <option value="168" <?= $hours === 168 ? 'selected' : '' ?>>最近 7 天</option>
                </select>
            </label>
            <button type="submit">刷新</button>
        </form>
    </div>

    <!-- 告警 -->
    <?php if (!empty($alerts)): ?>
    <div class="alerts-section">
        <h2>告警</h2>
        <?php foreach ($alerts as $alert): ?>
        <div class="alert alert-<?= $alert['level'] === 'high' ? 'error' : 'warning' ?>">
            <strong><?= htmlspecialchars($alert['message']) ?></strong>
            <?php if (isset($alert['count'])): ?>
            <span class="badge"><?= $alert['count'] ?></span>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- 统计概览 -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= number_format($stats['total']) ?></div>
            <div class="stat-label">总日志数</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($stats['unique_ips']) ?></div>
            <div class="stat-label">唯一 IP</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($stats['unique_user_agents']) ?></div>
            <div class="stat-label">唯一 User-Agent</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= count($topErrors) ?></div>
            <div class="stat-label">错误类型</div>
        </div>
    </div>

    <!-- 最常见的错误 -->
    <?php if (!empty($topErrors)): ?>
    <div class="section">
        <h2>最常见的错误（Top 10）</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>错误消息</th>
                    <th>出现次数</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($topErrors as $message => $count): ?>
                <tr>
                    <td><?= htmlspecialchars($message) ?></td>
                    <td><span class="badge"><?= $count ?></span></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- 错误趋势 -->
    <?php if (!empty($errorTrend)): ?>
    <div class="section">
        <h2>错误趋势（按小时）</h2>
        <table class="data-table">
            <thead>
                <tr>
                    <th>时间</th>
                    <th>错误数</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($errorTrend as $hour => $count): ?>
                <tr>
                    <td><?= htmlspecialchars($hour) ?></td>
                    <td>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: <?= min(100, ($count / max(1, max($errorTrend))) * 100) ?>%"></div>
                            <span class="progress-text"><?= $count ?></span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>

    <!-- 性能统计 -->
    <?php if ($performanceStats['total_requests'] > 0): ?>
    <div class="section">
        <h2>性能统计</h2>
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($performanceStats['total_requests']) ?></div>
                <div class="stat-label">总请求数</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($performanceStats['avg_duration'], 3) ?>s</div>
                <div class="stat-label">平均响应时间</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($performanceStats['max_duration'], 3) ?>s</div>
                <div class="stat-label">最大响应时间</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $performanceStats['slow_requests'] ?></div>
                <div class="stat-label">慢请求（>3s）</div>
            </div>
        </div>
        <div class="stats-grid" style="margin-top: 20px;">
            <div class="stat-card">
                <div class="stat-value"><?= number_format($performanceStats['query_stats']['total']) ?></div>
                <div class="stat-label">数据库查询总数</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= number_format($performanceStats['query_stats']['avg_duration'], 3) ?>s</div>
                <div class="stat-label">平均查询时间</div>
            </div>
            <div class="stat-card">
                <div class="stat-value"><?= $performanceStats['query_stats']['slow_queries'] ?></div>
                <div class="stat-label">慢查询（>1s）</div>
            </div>
        </div>
    </div>
    <?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>

