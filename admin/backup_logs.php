<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';
$backupConfig = require __DIR__ . '/../backup_config.php';

$pageTitle    = '备份记录';
$pageHeading  = '备份记录';
$pageSubtitle = '查看、下载或删除最近的站点备份。系统最多保留 ' . (int)($backupConfig['max_keep'] ?? 5) . ' 份。';
$activeMenu   = 'backups';

// Ensure backup_logs table exists
$dbName = $pdo->query('SELECT DATABASE()')->fetchColumn();
$tableCheck = $pdo->prepare(
    "SELECT COUNT(*) FROM information_schema.TABLES WHERE TABLE_SCHEMA = :db AND TABLE_NAME = 'backup_logs'"
);
$tableCheck->execute([':db' => $dbName]);
if ((int)$tableCheck->fetchColumn() === 0) {
    $pdo->exec(
        "CREATE TABLE backup_logs (
            id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL,
            file_path VARCHAR(255) NOT NULL,
            file_size BIGINT UNSIGNED NOT NULL DEFAULT 0,
            status VARCHAR(20) NOT NULL DEFAULT 'success',
            message VARCHAR(255) DEFAULT NULL,
            ip VARCHAR(45) DEFAULT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;"
    );
}

$stmt = $pdo->query("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 20");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$token     = $backupConfig['token'];
$backupUrl = '/backup.php?token=' . urlencode($token);

ob_start();
?>

<div class="section-card" style="background:#0f172a;color:#e5e7eb; border-color: rgba(148,163,184,0.4); box-shadow: 0 10px 25px rgba(15,23,42,0.5);">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
            <div style="font-size:15px;font-weight:600;margin-bottom:4px;">一键备份 DoFun 网站</div>
            <div style="font-size:12px;color:#9ca3af;max-width:420px;">
                自动打包数据库与站点文件生成 ZIP，建议定期下载保存。
            </div>
        </div>
        <div>
            <button type="button" onclick="confirmBackup()" class="btn btn-primary">
                立即备份
            </button>
        </div>
    </div>
</div>

<?php if (!$logs): ?>
    <div class="alert alert-danger">暂无备份记录。</div>
<?php else: ?>
    <table class="table-admin">
        <thead>
        <tr>
            <th style="width:60px;">ID</th>
            <th>文件名</th>
            <th style="width:120px;">大小</th>
            <th style="width:170px;">时间</th>
            <th style="width:90px;">状态</th>
            <th style="width:120px;">IP</th>
            <th style="width:180px;">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($logs as $log): ?>
            <tr>
                <td><?= (int)$log['id'] ?></td>
                <td><?= htmlspecialchars($log['filename']) ?></td>
                <td>
                    <?php
                    $size = (int)$log['file_size'];
                    if ($size > 1024 * 1024) {
                        echo number_format($size / (1024 * 1024), 2) . ' MB';
                    } elseif ($size > 1024) {
                        echo number_format($size / 1024, 2) . ' KB';
                    } else {
                        echo $size . ' B';
                    }
                    ?>
                </td>
                <td><?= htmlspecialchars($log['created_at']) ?></td>
                <td><?= htmlspecialchars($log['status']) ?></td>
                <td><?= htmlspecialchars($log['ip']) ?></td>
                <td>
                    <?php if ($log['status'] === 'success'): ?>
                        <a class="btn btn-mini" href="/download_backup.php?id=<?= (int)$log['id'] ?>&token=<?= urlencode($token) ?>" target="_blank">下载</a>
                        <a class="btn btn-mini" style="background:#b91c1c;color:#fff;border:none;"
                           href="/delete_backup.php?id=<?= (int)$log['id'] ?>&token=<?= urlencode($token) ?>"
                           onclick="return confirm('确认删除这份备份及其记录？');">删除</a>
                    <?php else: ?>
                        -
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<script>
function confirmBackup() {
    if (!confirm('确认要立即备份站点吗？备份过程中请勿刷新页面。')) {
        return;
    }
    window.open('<?= $backupUrl ?>', '_blank');
}
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
