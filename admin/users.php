<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/csrf.php';

$pageTitle = '用户管理';
$pageSubtitle = '管理系统注册用户，支持查看、编辑、重置密码与删除';
$activeMenu = 'users';

$errors = [];
$success = '';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        $errors[] = 'CSRF token 验证失败，请刷新页面后重试';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'update') {
            $userId = (int)($_POST['user_id'] ?? 0);
            $email = trim($_POST['email'] ?? '');
            $nickname = trim($_POST['nickname'] ?? '');
            $password = trim($_POST['password'] ?? '');
            
            if ($userId <= 0) {
                $errors[] = '缺少用户 ID';
            } elseif ($email === '') {
                $errors[] = '用户名不能为空';
            } elseif (!preg_match('/^[A-Za-z0-9]{3,25}$/', $email)) {
                $errors[] = '用户名需为 3-25 位英文和数字组合';
            } else {
                // 检查用户名是否已被使用
                $dup = $pdo->prepare('SELECT COUNT(*) FROM users WHERE email = :e AND id != :id');
                $dup->execute([':e' => $email, ':id' => $userId]);
                if ((int)$dup->fetchColumn() > 0) {
                    $errors[] = '用户名已被使用';
                }
            }
            
            if ($nickname !== '' && (mb_strlen($nickname) < 3 || mb_strlen($nickname) > 15)) {
                $errors[] = '昵称长度需在 3-15 位';
            }
            
            if ($password !== '' && (mb_strlen($password) < 6 || mb_strlen($password) > 20)) {
                $errors[] = '密码长度需在 6-20 位';
            }
            
            if (!$errors) {
                $fields = ['email = :e', 'nickname = :n'];
                $params = [
                    ':e' => $email,
                    ':n' => $nickname !== '' ? $nickname : null,
                    ':id' => $userId,
                ];
                
                if ($password !== '') {
                    $fields[] = 'password_hash = :p';
                    $params[':p'] = password_hash($password, PASSWORD_DEFAULT);
                }
                
                $sql = 'UPDATE users SET ' . implode(', ', $fields) . ' WHERE id = :id';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $success = '已更新用户：' . htmlspecialchars($email);
            }
        } elseif ($action === 'delete') {
            $userId = (int)($_POST['user_id'] ?? 0);
            if ($userId <= 0) {
                $errors[] = '缺少用户 ID';
            } else {
                // 检查是否有测验记录
                $runCount = $pdo->prepare('SELECT COUNT(*) FROM test_runs WHERE user_id = :id');
                $runCount->execute([':id' => $userId]);
                $count = (int)$runCount->fetchColumn();
                
                if ($count > 0) {
                    $errors[] = "该用户有 {$count} 条测验记录，无法删除。如需删除，请先清理相关记录。";
                } else {
                    $stmt = $pdo->prepare('DELETE FROM users WHERE id = :id');
                    $stmt->execute([':id' => $userId]);
                    $success = '已删除用户';
                }
            }
        }
    }
}

// 获取搜索关键词
$search = trim($_GET['search'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

// 构建查询
$where = [];
$params = [];

if ($search !== '') {
    $where[] = '(email LIKE :search OR nickname LIKE :search)';
    $params[':search'] = '%' . $search . '%';
}

$whereClause = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// 获取总数
$countSql = "SELECT COUNT(*) FROM users {$whereClause}";
$countStmt = $pdo->prepare($countSql);
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($total / $perPage));

// 获取用户列表
$sql = "SELECT u.*, 
        (SELECT COUNT(*) FROM test_runs WHERE user_id = u.id) AS test_count
        FROM users u 
        {$whereClause}
        ORDER BY u.id DESC 
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
foreach ($params as $key => $value) {
    $stmt->bindValue($key, $value);
}
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$users = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <form method="get" style="display: flex; gap: 8px; align-items: center;">
            <input type="text" name="search" class="admin-input admin-input--search"
                   placeholder="搜索用户名或昵称..."
                   value="<?= htmlspecialchars($search) ?>">
            <button type="submit" class="btn btn-primary">搜索</button>
            <?php if ($search): ?>
                <a href="users.php" class="btn btn-secondary">清除</a>
            <?php endif; ?>
        </form>
    </div>
    <div class="admin-toolbar__right">
        <span class="admin-table__muted">共 <?= $total ?> 位用户</span>
    </div>
</div>

<div class="admin-card">
    <?php if (empty($users)): ?>
        <p class="admin-table__muted"><?= $search ? '未找到匹配的用户' : '当前没有用户' ?></p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>用户名</th>
                <th>昵称</th>
                <th style="width:120px;">测验次数</th>
                <th style="width:160px;">注册时间</th>
                <th style="width:160px;">最后登录</th>
                <th style="width:400px;">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($users as $user): ?>
                <tr>
                    <td><?= (int)$user['id'] ?></td>
                    <td>
                        <div class="admin-table__title">
                            <?= htmlspecialchars($user['email']) ?>
                        </div>
                    </td>
                    <td>
                        <?= $user['nickname'] ? htmlspecialchars($user['nickname']) : '<span class="admin-table__muted">未设置</span>' ?>
                    </td>
                    <td>
                        <a href="user_detail.php?id=<?= (int)$user['id'] ?>" class="admin-kpi-link">
                            <?= (int)$user['test_count'] ?> 次
                        </a>
                    </td>
                    <td>
                        <span class="admin-table__muted">
                            <?= $user['created_at'] ? date('Y-m-d H:i', strtotime($user['created_at'])) : '-' ?>
                        </span>
                    </td>
                    <td>
                        <span class="admin-table__muted">
                            <?= $user['last_login_at'] ? date('Y-m-d H:i', strtotime($user['last_login_at'])) : '从未登录' ?>
                        </span>
                    </td>
                    <td>
                        <div class="admin-table__actions" style="flex-wrap: wrap; gap: 6px;">
                            <a href="user_detail.php?id=<?= (int)$user['id'] ?>" class="btn btn-xs btn-primary">详情</a>
                            <button type="button" class="btn btn-xs btn-secondary" onclick="showEditModal(<?= (int)$user['id'] ?>, '<?= htmlspecialchars($user['email'], ENT_QUOTES) ?>', '<?= htmlspecialchars($user['nickname'] ?? '', ENT_QUOTES) ?>')">编辑</button>
                            <form method="post" style="display: inline-block;" onsubmit="return confirm('确定要删除该用户吗？此操作不可恢复！')">
                                <?php echo CSRF::getTokenField(); ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="user_id" value="<?= (int)$user['id'] ?>">
                                <button type="submit" class="btn btn-xs" style="background: #ef4444; color: white;">删除</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($totalPages > 1): ?>
            <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: center; align-items: center;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-secondary">上一页</a>
                <?php endif; ?>
                <span class="admin-table__muted">第 <?= $page ?> / <?= $totalPages ?> 页</span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?><?= $search ? '&search=' . urlencode($search) : '' ?>" class="btn btn-secondary">下一页</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- 编辑用户模态框 -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.5); z-index: 1000; align-items: center; justify-content: center;">
    <div class="admin-card" style="max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin: 0; color: var(--admin-text-primary);">编辑用户</h2>
            <button type="button" onclick="closeEditModal()" style="background: none; border: none; font-size: 24px; cursor: pointer; color: var(--admin-text-secondary);">&times;</button>
        </div>
        <form method="post" id="editForm">
            <?php echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="user_id" id="edit_user_id">
            <div class="form-field">
                <label class="form-label">用户名 *</label>
                <input type="text" name="email" id="edit_email" class="form-input" required>
            </div>
            <div class="form-field">
                <label class="form-label">昵称</label>
                <input type="text" name="nickname" id="edit_nickname" class="form-input" placeholder="可选">
            </div>
            <div class="form-field">
                <label class="form-label">重置密码</label>
                <input type="password" name="password" id="edit_password" class="form-input" placeholder="留空则不修改密码">
            </div>
            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存</button>
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">取消</button>
            </div>
        </form>
    </div>
</div>

<script>
function showEditModal(userId, email, nickname) {
    document.getElementById('edit_user_id').value = userId;
    document.getElementById('edit_email').value = email;
    document.getElementById('edit_nickname').value = nickname || '';
    document.getElementById('edit_password').value = '';
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// 点击模态框外部关闭
document.getElementById('editModal')?.addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

