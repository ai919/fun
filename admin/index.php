<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';

$pageTitle    = '控制台 · DoFun';
$pageHeading  = '控制台';
$pageSubtitle = '快速查看测试概况与运维入口。';
$activeMenu   = 'dashboard';

$testCountStmt = $pdo->query("SELECT COUNT(*) FROM tests");
$testCount = (int)$testCountStmt->fetchColumn();

$questionCountStmt = $pdo->query("SELECT COUNT(*) FROM questions");
$questionCount = (int)$questionCountStmt->fetchColumn();

$latestBackupStmt = $pdo->query("SELECT created_at FROM backup_logs ORDER BY created_at DESC LIMIT 1");
$latestBackup = $latestBackupStmt->fetchColumn();

$config     = require __DIR__ . '/../backup_config.php';
$backupUrl  = '/backup.php?token=' . urlencode($config['token']);
$backupKeep = (int)($config['max_keep'] ?? 5);

require __DIR__ . '/layout.php';
?>

<div class="section-card" style="background:#0f172a;color:#e5e7eb; border-color: rgba(148,163,184,0.4); box-shadow: 0 10px 25px rgba(15,23,42,0.5);">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
            <div style="font-size:15px;font-weight:600;margin-bottom:4px;">一键备份 DoFun 站点</div>
            <div style="font-size:12px;color:#9ca3af;max-width:420px;">
                点击后将自动导出数据库 + 网站文件为一个 ZIP 包。系统最多保留 <?= $backupKeep ?> 个备份。
                <?php if ($latestBackup): ?>
                    <br>上一次备份：<?= htmlspecialchars($latestBackup) ?>
                <?php endif; ?>
            </div>
        </div>
        <div>
            <button type="button" onclick="confirmBackup()" class="btn btn-primary">
                立即备份并下载 ZIP →
            </button>
            <a class="btn btn-ghost" href="/admin/backup_logs.php">查看备份记录</a>
        </div>
    </div>
</div>

<div class="section-card">
    <h2>站点概况</h2>
    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px;margin-top:12px;">
        <div class="stat-card">
            <div class="stat-card-title">测验总数</div>
            <div class="stat-card-value"><?= $testCount ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">题目条数</div>
            <div class="stat-card-value"><?= $questionCount ?></div>
        </div>
        <div class="stat-card">
            <div class="stat-card-title">最近一次备份</div>
            <div class="stat-card-value" style="font-size:16px;">
                <?= $latestBackup ? htmlspecialchars($latestBackup) : '暂无记录' ?>
            </div>
        </div>
    </div>
</div>

<script>
function confirmBackup() {
    if (!confirm('确定要现在备份站点吗？过程中请不要刷新页面。')) {
        return;
    }
    window.open('<?= $backupUrl ?>', '_blank');
}
</script>

<?php require __DIR__ . '/layout_footer.php'; ?>
