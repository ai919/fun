<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';
$backupConfig = require __DIR__ . '/../backup_config.php';

$pageTitle    = '备份记录 · DoFun';
$pageHeading  = '备份记录';
$pageSubtitle = '查看近期备份、下载或删除旧备份。系统最多保留 ' . (int)($backupConfig['max_keep'] ?? 5) . ' 个备份。';
$activeMenu   = 'tests';

$stmt = $pdo->query("SELECT * FROM backup_logs ORDER BY created_at DESC LIMIT 20");
$logs = $stmt->fetchAll(PDO::FETCH_ASSOC);
$token = $backupConfig['token'];
$backupUrl = '/backup.php?token=' . urlencode($token);

require __DIR__ . '/layout.php';
?>

<div class="section-card" style="background:#0f172a;color:#e5e7eb; border-color: rgba(148,163,184,0.4); box-shadow: 0 10px 25px rgba(15,23,42,0.5);">
    <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;">
        <div>
            <div style="font-size:15px;font-weight:600;margin-bottom:4px;">一键备份 DoFun 站点</div>
            <div style="font-size:12px;color:#9ca3af;max-width:420px;">
                点击后将自动导出数据库 + 网站文件为一个 ZIP 包。建议定期下载保留一份到本地或云盘。
            </div>
        </div>
        <div>
            <button type="button" onclick="confirmBackup()" class="btn btn-primary">
                立即备份并下载 ZIP →
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
                           onclick="return confirm('确定删除这个备份文件及记录？');">删除</a>
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
    if (!confirm('确定要现在备份站点吗？过程中请不要刷新页面。')) {
        return;
    }
    window.open('<?= $backupUrl ?>', '_blank');
}
</script>

<?php require __DIR__ . '/layout_footer.php'; ?>
