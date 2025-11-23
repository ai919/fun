<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/Database.php';

$pageTitle = '数据库查询日志';
$pageSubtitle = '查看数据库查询日志和性能分析';
$activeMenu = 'system';

// 处理操作
$action = $_GET['action'] ?? '';
if ($action === 'enable') {
    Database::enableQueryLog();
    header('Location: db_query_logs.php');
    exit;
} elseif ($action === 'disable') {
    Database::disableQueryLog();
    header('Location: db_query_logs.php');
    exit;
} elseif ($action === 'clear') {
    Database::enableQueryLog(); // 清空日志
    header('Location: db_query_logs.php');
    exit;
}

// 获取查询日志
$queryLog = Database::getQueryLog();

// 检查是否启用了查询日志
$isEnabled = Database::isQueryLogEnabled();

// 统计信息
$totalQueries = count($queryLog);
$totalTime = 0;
$slowQueries = [];
foreach ($queryLog as $log) {
    $totalTime += $log['time'] ?? 0;
    if (isset($log['time']) && $log['time'] > 0.1) {
        $slowQueries[] = $log;
    }
}

$avgTime = $totalQueries > 0 ? $totalTime / $totalQueries : 0;

ob_start();
?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <span class="admin-table__muted">查看数据库查询日志，分析查询性能和优化慢查询。</span>
    </div>
    <div class="admin-toolbar__right">
        <?php if ($isEnabled): ?>
            <a href="?action=disable" class="btn btn-xs" style="background:#6b7280;color:#fff;border:none;margin-right:8px;">
                禁用日志
            </a>
            <a href="?action=clear" class="btn btn-xs" style="background:#b91c1c;color:#fff;border:none;margin-right:8px;"
               onclick="return confirm('确认清空查询日志？');">
                清空日志
            </a>
        <?php else: ?>
            <a href="?action=enable" class="btn btn-xs btn-primary">
                启用日志
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 统计信息 -->
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">查询统计</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
        <div style="padding: 12px; background: #1e293b; border: 1px solid rgba(55,65,81,0.85); border-radius: 8px;">
            <div style="font-size: 12px; color: #9ca3af; margin-bottom: 6px;">总查询数</div>
            <div style="font-size: 24px; font-weight: 600; color: #e5e7eb;">
                <?= number_format($totalQueries) ?>
            </div>
        </div>
        <div style="padding: 12px; background: #1e293b; border: 1px solid rgba(55,65,81,0.85); border-radius: 8px;">
            <div style="font-size: 12px; color: #9ca3af; margin-bottom: 6px;">总耗时</div>
            <div style="font-size: 24px; font-weight: 600; color: #e5e7eb;">
                <?= number_format($totalTime * 1000, 2) ?> ms
            </div>
        </div>
        <div style="padding: 12px; background: #1e293b; border: 1px solid rgba(55,65,81,0.85); border-radius: 8px;">
            <div style="font-size: 12px; color: #9ca3af; margin-bottom: 6px;">平均耗时</div>
            <div style="font-size: 24px; font-weight: 600; color: #e5e7eb;">
                <?= number_format($avgTime * 1000, 2) ?> ms
            </div>
        </div>
        <div style="padding: 12px; background: #1e293b; border: 1px solid rgba(55,65,81,0.85); border-radius: 8px;">
            <div style="font-size: 12px; color: #9ca3af; margin-bottom: 6px;">慢查询 (>100ms)</div>
            <div style="font-size: 24px; font-weight: 600; color: <?= count($slowQueries) > 0 ? '#ef4444' : '#34d399' ?>;">
                <?= number_format(count($slowQueries)) ?>
            </div>
        </div>
    </div>
</div>

<!-- 查询日志 -->
<div class="admin-card">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">
        查询日志
        <?php if (!$isEnabled): ?>
            <span style="font-size: 12px; font-weight: normal; color: #9ca3af; margin-left: 8px;">
                (未启用 - 点击"启用日志"开始记录)
            </span>
        <?php endif; ?>
    </h2>
    
    <?php if (empty($queryLog)): ?>
        <div style="padding: 40px; text-align: center; color: #9ca3af;">
            <div style="font-size: 48px; margin-bottom: 16px;">📊</div>
            <div>暂无查询日志</div>
            <?php if (!$isEnabled): ?>
                <div style="margin-top: 12px; font-size: 13px;">
                    <a href="?action=enable" class="btn btn-primary btn-sm">启用查询日志</a>
                </div>
            <?php endif; ?>
        </div>
    <?php else: ?>
        <div style="background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 8px; padding: 16px; max-height: 600px; overflow-y: auto;">
            <?php foreach (array_reverse($queryLog) as $index => $log): ?>
                <?php
                $time = $log['time'] ?? 0;
                $timeMs = $time * 1000;
                $isSlow = $time > 0.1;
                $timeColor = $isSlow ? '#ef4444' : ($time > 0.05 ? '#f59e0b' : '#34d399');
                ?>
                <div style="padding: 12px; margin-bottom: 12px; background: #1e293b; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; border-left: 3px solid <?= $timeColor ?>;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <div style="font-size: 11px; color: #9ca3af;">
                            查询 #<?= count($queryLog) - $index ?>
                        </div>
                        <div style="display: flex; align-items: center; gap: 12px;">
                            <span style="font-size: 12px; color: #9ca3af;">耗时:</span>
                            <span style="font-size: 13px; font-weight: 600; color: <?= $timeColor ?>;">
                                <?= number_format($timeMs, 2) ?> ms
                            </span>
                            <?php if ($isSlow): ?>
                                <span style="background: #ef4444; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 10px; font-weight: 600;">
                                    慢查询
                                </span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <div style="margin-bottom: 8px;">
                        <div style="font-size: 11px; color: #9ca3af; margin-bottom: 4px;">SQL:</div>
                        <div style="font-family: 'Consolas', 'Monaco', monospace; font-size: 12px; color: #e5e7eb; background: #020617; padding: 8px; border-radius: 4px; overflow-x: auto;">
                            <?= htmlspecialchars($log['sql'] ?? 'N/A') ?>
                        </div>
                    </div>
                    <?php if (!empty($log['params'])): ?>
                        <div>
                            <div style="font-size: 11px; color: #9ca3af; margin-bottom: 4px;">参数:</div>
                            <div style="font-family: 'Consolas', 'Monaco', monospace; font-size: 11px; color: #60a5fa; background: #020617; padding: 8px; border-radius: 4px; overflow-x: auto;">
                                <?= htmlspecialchars(json_encode($log['params'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) ?>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 慢查询分析 -->
<?php if (!empty($slowQueries)): ?>
    <div class="admin-card" style="margin-top: 16px;">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">
            慢查询分析 (<?= count($slowQueries) ?> 个)
        </h2>
        <div style="background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 8px; padding: 16px; max-height: 400px; overflow-y: auto;">
            <?php foreach (array_reverse($slowQueries) as $log): ?>
                <div style="padding: 12px; margin-bottom: 12px; background: #1e293b; border: 1px solid #ef4444; border-radius: 6px;">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                        <span style="font-size: 12px; color: #9ca3af;">耗时: <?= number_format(($log['time'] ?? 0) * 1000, 2) ?> ms</span>
                    </div>
                    <div style="font-family: 'Consolas', 'Monaco', monospace; font-size: 12px; color: #e5e7eb;">
                        <?= htmlspecialchars($log['sql'] ?? 'N/A') ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

