<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/db_connect.php';

$pageTitle  = '管理员';
$pageSubtitle = '管理后台账号，支持新增、修改、启用/禁用与重置密码';
$activeMenu = 'admin_users';

$errors = [];
$success = '';

// Handle create/update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action   = $_POST['action'] ?? '';
    $username = trim($_POST['username'] ?? '');
    $display  = trim($_POST['display_name'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if ($action === 'create') {
        if ($username === '' || $password === '') {
            $errors[] = '请输入用户名和密码。';
        } else {
            $dup = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :u');
            $dup->execute([':u' => $username]);
            if ((int)$dup->fetchColumn() > 0) {
                $errors[] = '用户名已存在。';
            }
        }
        if (!$errors) {
            $stmt = $pdo->prepare('INSERT INTO admin_users (username, display_name, password_hash, is_active, created_at) VALUES (:u, :d, :p, 1, NOW())');
            $stmt->execute([
                ':u' => $username,
                ':d' => $display !== '' ? $display : $username,
                ':p' => password_hash($password, PASSWORD_DEFAULT),
            ]);
            $success = '已新增管理员：' . htmlspecialchars($username);
        }
    } elseif ($action === 'update') {
        $userId = (int)($_POST['user_id'] ?? 0);
        if ($userId <= 0) {
            $errors[] = '缺少用户 ID。';
        } else {
            $isActive = isset($_POST['is_active']) ? (int)$_POST['is_active'] : 0;
            if ($username === '') {
                $errors[] = '用户名不能为空。';
            } else {
                $dup = $pdo->prepare('SELECT COUNT(*) FROM admin_users WHERE username = :u AND id != :id');
                $dup->execute([':u' => $username, ':id' => $userId]);
                if ((int)$dup->fetchColumn() > 0) {
                    $errors[] = '用户名已存在。';
                }
            }
            if (!$errors) {
                $fields = [
                    'username = :u',
                    'display_name = :d',
                    'is_active = :a',
                ];
                $params = [
                    ':u'  => $username,
                    ':d'  => $display !== '' ? $display : $username,
                    ':a'  => $isActive ? 1 : 0,
                    ':id' => $userId,
                ];
                if ($password !== '') {
                    $fields[]      = 'password_hash = :p';
                    $params[':p']  = password_hash($password, PASSWORD_DEFAULT);
                }
                $sql = 'UPDATE admin_users SET ' . implode(', ', $fields) . ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $success = '已更新管理员：' . htmlspecialchars($username);
            }
        }
    }
}

$admins = $pdo->query('SELECT * FROM admin_users ORDER BY id ASC')->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>
<?php if ($success): ?>
    <div class="alert alert-success"><?= $success ?></div>
<?php endif; ?>

<div class="admin-card admin-card--form" style="margin-bottom:14px;">
    <div class="form-section">
        <div class="form-section__title">新增管理员</div>
        <form method="post" class="form-grid">
            <input type="hidden" name="action" value="create">
            <div class="form-field">
                <label class="form-label">用户名 *</label>
                <input type="text" name="username" class="form-input" required>
            </div>
            <div class="form-field">
                <label class="form-label">显示名</label>
                <input type="text" name="display_name" class="form-input" placeholder="不填默认同用户名">
            </div>
            <div class="form-field">
                <label class="form-label">密码 *</label>
                <input type="password" name="password" class="form-input" required>
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">创建</button>
            </div>
        </form>
    </div>
</div>

<div class="admin-card">
    <div class="form-section__title" style="margin-bottom:8px;">管理员列表</div>
    <?php if (empty($admins)): ?>
        <p class="admin-table__muted">当前没有管理员，请先新增一个账号。</p>
    <?php else: ?>
        <table class="admin-table admin-table--compact">
            <thead>
            <tr>
                <th style="width:50px;">ID</th>
                <th>用户名</th>
                <th>显示名</th>
                <th style="width:90px;">状态</th>
                <th style="width:260px;">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($admins as $admin): ?>
                <tr>
                    <td><?= (int)$admin['id'] ?></td>
                    <td><?= htmlspecialchars($admin['username']) ?></td>
                    <td><?= htmlspecialchars($admin['display_name']) ?></td>
                    <td>
                        <span class="badge <?= $admin['is_active'] ? 'badge--published' : 'badge--archived' ?>">
                            <?= $admin['is_active'] ? '启用' : '停用' ?>
                        </span>
                    </td>
                    <td>
                        <form method="post" class="admin-table__actions" style="gap:6px; flex-wrap:wrap; align-items:center;">
                            <input type="hidden" name="action" value="update">
                            <input type="hidden" name="user_id" value="<?= (int)$admin['id'] ?>">
                            <input type="text" name="username" class="form-input" style="width:120px;"
                                   value="<?= htmlspecialchars($admin['username']) ?>">
                            <input type="text" name="display_name" class="form-input" style="width:120px;"
                                   value="<?= htmlspecialchars($admin['display_name']) ?>" placeholder="显示名">
                            <select name="is_active" class="form-select" style="width:90px;">
                                <option value="1" <?= $admin['is_active'] ? 'selected' : '' ?>>启用</option>
                                <option value="0" <?= !$admin['is_active'] ? 'selected' : '' ?>>停用</option>
                            </select>
                            <input type="password" name="password" class="form-input" style="width:140px;" placeholder="重置密码(可选)">
                            <button type="submit" class="btn btn-xs btn-primary">保存</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
