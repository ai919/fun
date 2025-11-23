<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../config/app.php';

$pageTitle = '系统日志';
$pageSubtitle = '查看应用错误、警告和信息日志';
$activeMenu = 'system';

$config = require __DIR__ . '/../config/app.php';
$logDir = $config['log']['dir'] ?? __DIR__ . '/../logs';

// 确保日志目录存在
if (!is_dir($logDir)) {
    @mkdir($logDir, 0755, true);
}

// 日志级别
$logLevels = ['error', 'warning', 'info', 'debug'];
$currentLevel = $_GET['level'] ?? 'error';
if (!in_array($currentLevel, $logLevels)) {
    $currentLevel = 'error';
}

// 日志文件路径
$logFile = $logDir . '/' . $currentLevel . '.log';

// 读取日志（最后 500 行）
$logs = [];
if (file_exists($logFile) && is_readable($logFile)) {
    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines && !empty($lines)) {
        // 取最后 500 行
        $lines = array_slice($lines, -500);
        $logs = array_reverse($lines); // 最新的在前
    }
}

// 统计各级别日志数量
$logStats = [];
foreach ($logLevels as $level) {
    $file = $logDir . '/' . $level . '.log';
    if (file_exists($file) && is_readable($file)) {
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logStats[$level] = [
            'count' => $lines ? count($lines) : 0,
            'size' => filesize($file),
            'modified' => filemtime($file),
        ];
    } else {
        $logStats[$level] = [
            'count' => 0,
            'size' => 0,
            'modified' => 0,
        ];
    }
}

// 处理操作
$action = $_GET['action'] ?? '';
if ($action === 'clear' && isset($_GET['level'])) {
    $levelToClear = $_GET['level'];
    if (in_array($levelToClear, $logLevels)) {
        $fileToClear = $logDir . '/' . $levelToClear . '.log';
        if (file_exists($fileToClear)) {
            @file_put_contents($fileToClear, '');
        }
        header('Location: system_logs.php?level=' . $levelToClear);
        exit;
    }
}

ob_start();
?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <span class="admin-table__muted">查看系统运行日志，便于排查问题和监控系统状态。</span>
    </div>
    <div class="admin-toolbar__right">
        <?php if (file_exists($logFile)): ?>
            <a href="?action=clear&level=<?= htmlspecialchars($currentLevel) ?>" 
               class="btn btn-xs" 
               onclick="return confirm('确认清空 <?= strtoupper($currentLevel) ?> 日志？');"
               style="background:#b91c1c;color:#fff;border:none;">
                清空当前日志
            </a>
        <?php endif; ?>
    </div>
</div>

<!-- 日志统计 -->
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">日志统计</h2>
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px;">
        <?php foreach ($logLevels as $level): ?>
            <?php
            $stats = $logStats[$level];
            $isActive = $currentLevel === $level;
            $badgeColor = [
                'error' => '#ef4444',
                'warning' => '#f59e0b',
                'info' => '#3b82f6',
                'debug' => '#6b7280',
            ][$level] ?? '#6b7280';
            ?>
            <a href="?level=<?= htmlspecialchars($level) ?>" 
               style="text-decoration: none; display: block; padding: 12px; background: <?= $isActive ? '#1e293b' : '#020617' ?>; border: 1px solid <?= $isActive ? $badgeColor : 'rgba(55,65,81,0.85)' ?>; border-radius: 8px; transition: all 0.2s;">
                <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                    <span style="font-size: 13px; color: #9ca3af; text-transform: uppercase;"><?= htmlspecialchars($level) ?></span>
                    <?php if ($stats['count'] > 0): ?>
                        <span style="background: <?= $badgeColor ?>; color: #fff; padding: 2px 8px; border-radius: 12px; font-size: 11px; font-weight: 600;">
                            <?= number_format($stats['count']) ?>
                        </span>
                    <?php endif; ?>
                </div>
                <div style="font-size: 18px; font-weight: 600; color: #e5e7eb; margin-bottom: 4px;">
                    <?php
                    if ($stats['size'] > 1024 * 1024) {
                        echo number_format($stats['size'] / (1024 * 1024), 2) . ' MB';
                    } elseif ($stats['size'] > 1024) {
                        echo number_format($stats['size'] / 1024, 2) . ' KB';
                    } else {
                        echo $stats['size'] . ' B';
                    }
                    ?>
                </div>
                <?php if ($stats['modified'] > 0): ?>
                    <div style="font-size: 11px; color: #6b7280;">
                        <?= date('Y-m-d H:i:s', $stats['modified']) ?>
                    </div>
                <?php else: ?>
                    <div style="font-size: 11px; color: #6b7280;">无日志</div>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- 日志内容 -->
<div class="admin-card">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">
        <?= strtoupper($currentLevel) ?> 日志
        <span style="font-size: 12px; font-weight: normal; color: #9ca3af; margin-left: 8px;">
            (显示最近 500 条)
        </span>
    </h2>
    
    <?php if (empty($logs)): ?>
        <div style="padding: 40px; text-align: center; color: #9ca3af;">
            <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
            <div>暂无 <?= strtoupper($currentLevel) ?> 日志</div>
        </div>
    <?php else: ?>
        <div style="background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 8px; padding: 16px; max-height: 600px; overflow-y: auto; font-family: 'Consolas', 'Monaco', monospace; font-size: 12px; line-height: 1.6;">
            <?php foreach ($logs as $log): ?>
                <div style="padding: 8px 0; border-bottom: 1px solid rgba(55,65,81,0.5); color: #e5e7eb;">
                    <?php
                    // 高亮时间戳
                    $log = preg_replace(
                        '/\[(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})\]/',
                        '<span style="color: #60a5fa;">[$1]</span>',
                        htmlspecialchars($log)
                    );
                    // 高亮日志级别
                    $log = preg_replace(
                        '/\[(ERROR|WARNING|INFO|DEBUG)\]/',
                        '<span style="color: #f59e0b; font-weight: 600;">[$1]</span>',
                        $log
                    );
                    // 高亮文件路径
                    $log = preg_replace(
                        '/(file=[^,\]]+)/',
                        '<span style="color: #34d399;">$1</span>',
                        $log
                    );
                    // 高亮行号
                    $log = preg_replace(
                        '/(line=\d+)/',
                        '<span style="color: #a78bfa;">$1</span>',
                        $log
                    );
                    echo $log;
                    ?>
                </div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

