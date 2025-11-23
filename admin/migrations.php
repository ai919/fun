<?php
/**
 * 数据库迁移管理页面
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/Migration.php';

$pageTitle = '数据库迁移管理';
$activeMenu = 'migrations';

$migration = new Migration($pdo);
$message = '';
$messageType = '';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    try {
        switch ($action) {
            case 'migrate':
                $dryRun = isset($_POST['dry_run']);
                $results = $migration->migrate($dryRun);
                
                if ($dryRun) {
                    $message = '预览模式：' . count($results['executed']) . ' 个迁移待执行';
                    $messageType = 'info';
                } else {
                    if (!empty($results['failed'])) {
                        $message = '迁移失败：' . $results['failed'][0]['error'];
                        $messageType = 'danger';
                    } else {
                        $count = count($results['executed']);
                        $message = $count > 0 
                            ? "成功执行 {$count} 个迁移" 
                            : '没有待执行的迁移';
                        $messageType = $count > 0 ? 'success' : 'info';
                    }
                }
                break;

            case 'rollback':
                $steps = isset($_POST['steps']) ? (int)$_POST['steps'] : null;
                $dryRun = isset($_POST['dry_run']);
                $results = $migration->rollback($steps, $dryRun);
                
                if ($dryRun) {
                    $message = '预览模式：' . count($results['executed']) . ' 个迁移待回滚';
                    $messageType = 'info';
                } else {
                    if (!empty($results['failed'])) {
                        $message = '回滚失败：' . $results['failed'][0]['error'];
                        $messageType = 'danger';
                    } else {
                        $count = count($results['executed']);
                        $message = $count > 0 
                            ? "成功回滚 {$count} 个迁移" 
                            : ($results['message'] ?? '没有可回滚的迁移');
                        $messageType = $count > 0 ? 'success' : 'info';
                    }
                }
                break;

            case 'create':
                $name = trim($_POST['name'] ?? '');
                if (empty($name)) {
                    $message = '迁移名称不能为空';
                    $messageType = 'danger';
                } else {
                    $filepath = $migration->create($name);
                    $message = '迁移文件创建成功：' . basename($filepath);
                    $messageType = 'success';
                }
                break;
        }
    } catch (Exception $e) {
        $message = '操作失败：' . $e->getMessage();
        $messageType = 'danger';
    }
}

// 获取迁移状态
$status = $migration->status();

ob_start();
?>

<?php if ($message): ?>
<div class="alert alert-<?= $messageType ?>">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="admin-card">
    <h2 style="font-size: 15px; font-weight: 600; margin: 0 0 12px;">创建新迁移</h2>
    <form method="POST" style="display: flex; gap: 8px; align-items: center;">
        <input type="hidden" name="action" value="create">
        <input type="text" name="name" class="form-input" placeholder="迁移名称（如：add_user_table）" required style="flex: 1; max-width: 300px;">
        <button type="submit" class="btn btn-primary">创建迁移文件</button>
    </form>
</div>

<div class="admin-card">
    <h2 style="font-size: 15px; font-weight: 600; margin: 0 0 12px;">执行迁移</h2>
    <form method="POST" style="display: inline-block; margin-right: 10px;">
        <input type="hidden" name="action" value="migrate">
        <button type="submit" class="btn btn-primary">执行所有待迁移</button>
    </form>
    <form method="POST" style="display: inline-block;">
        <input type="hidden" name="action" value="migrate">
        <input type="hidden" name="dry_run" value="1">
        <button type="submit" class="btn">预览（不执行）</button>
    </form>
</div>

<div class="admin-card">
    <h2 style="font-size: 15px; font-weight: 600; margin: 0 0 12px;">回滚迁移</h2>
    <form method="POST" style="display: inline-block; margin-right: 10px;">
        <input type="hidden" name="action" value="rollback">
        <button type="submit" class="btn">回滚最后一个批次</button>
    </form>
    <form method="POST" style="display: inline-block;">
        <input type="hidden" name="action" value="rollback">
        <input type="hidden" name="dry_run" value="1">
        <button type="submit" class="btn">预览回滚</button>
    </form>
</div>

<div class="admin-card">
    <h2 style="font-size: 15px; font-weight: 600; margin: 0 0 12px;">迁移状态</h2>
    <table class="admin-table">
        <thead>
            <tr>
                <th>迁移名称</th>
                <th>状态</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($status)): ?>
            <tr>
                <td colspan="2" class="admin-table__muted">暂无迁移文件</td>
            </tr>
            <?php else: ?>
            <?php foreach ($status as $item): ?>
            <tr>
                <td><?= htmlspecialchars($item['migration']) ?></td>
                <td>
                    <?php if ($item['status'] === 'executed'): ?>
                        <span class="badge badge--published">已执行</span>
                    <?php else: ?>
                        <span class="badge badge--draft">待执行</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

