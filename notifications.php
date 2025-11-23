<?php
require_once __DIR__ . '/lib/user_auth.php';
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/topbar.php';
require_once __DIR__ . '/lib/NotificationHelper.php';

$user = UserAuth::requireLogin();

$errors = [];
$success = '';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        $errors['general'] = 'CSRF token 验证失败，请刷新页面后重试';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'mark_read') {
            $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
            if ($notificationId > 0) {
                NotificationHelper::markAsRead($notificationId, $user['id']);
                $success = '通知已标记为已读';
            }
        } elseif ($action === 'mark_all_read') {
            $count = NotificationHelper::markAllAsRead($user['id']);
            $success = "已标记 {$count} 条通知为已读";
        } elseif ($action === 'delete') {
            $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
            if ($notificationId > 0) {
                NotificationHelper::delete($notificationId, $user['id']);
                $success = '通知已删除';
            }
        }
    }
}

// 获取通知列表
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 20;
$offset = ($page - 1) * $limit;

$notifications = NotificationHelper::getUserNotifications($user['id'], $limit, $offset);
$unreadCount = NotificationHelper::getUnreadCount($user['id']);

// 获取总数用于分页
$totalStmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ?");
$totalStmt->execute([$user['id']]);
$totalNotifications = (int)$totalStmt->fetchColumn();
$totalPages = ceil($totalNotifications / $limit);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>我的通知 - DoFun</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/theme-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeBtn = document.getElementById('theme-toggle-btn');
            if (themeBtn) {
                themeBtn.addEventListener('click', function() {
                    window.ThemeToggle.toggle();
                });
            }
        });
    </script>
</head>
<body class="page-notifications">
<?php render_topbar(); ?>
<div class="container">
    <header>
        <h1>我的通知</h1>
        <?php if ($unreadCount > 0): ?>
            <p>
                您有 <strong><?= $unreadCount ?></strong> 条未读通知
            </p>
        <?php else: ?>
            <p>暂无未读通知</p>
        <?php endif; ?>
    </header>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-error">
            <?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="notifications-actions">
        <div></div>
        <?php if ($unreadCount > 0): ?>
            <form method="POST" style="display: inline;">
                <?= CSRF::getTokenField() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn-secondary">全部标记为已读</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div class="notifications-empty">
            <p>暂无通知</p>
        </div>
    <?php else: ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item<?= !$notif['is_read'] ? ' unread' : '' ?>">
                    <?php if (!$notif['is_read']): ?>
                        <span class="notification-unread-dot"></span>
                    <?php endif; ?>
                    <div class="notification-content">
                        <div class="notification-main">
                            <h3 class="notification-title">
                                <?= htmlspecialchars($notif['title'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <?php if (!empty($notif['content'])): ?>
                                <div class="notification-text">
                                    <?= $notif['content'] ?>
                                </div>
                            <?php endif; ?>
                            <div class="notification-meta">
                                <span class="notification-time">
                                    <?= htmlspecialchars($notif['created_at'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if ($notif['type']): ?>
                                    <span class="notification-type notification-type-<?= htmlspecialchars($notif['type'], ENT_QUOTES, 'UTF-8') ?>">
                                        <?php
                                        $typeLabels = [
                                            'info' => '信息',
                                            'success' => '成功',
                                            'warning' => '警告',
                                            'error' => '错误'
                                        ];
                                        echo htmlspecialchars($typeLabels[$notif['type']] ?? $notif['type'], ENT_QUOTES, 'UTF-8');
                                        ?>
                                    </span>
                                <?php endif; ?>
                            </div>
                        </div>
                        <div class="notification-actions">
                            <?php if (!$notif['is_read']): ?>
                                <form method="POST" style="display: inline;">
                                    <?= CSRF::getTokenField() ?>
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?= (int)$notif['id'] ?>">
                                    <button type="submit" class="btn-xs">标记已读</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这条通知吗？');">
                                <?= CSRF::getTokenField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_id" value="<?= (int)$notif['id'] ?>">
                                <button type="submit" class="btn-xs btn-ghost btn-danger">删除</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div class="notifications-pagination">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="btn-secondary">上一页</a>
                <?php endif; ?>
                <span>
                    第 <?= $page ?> / <?= $totalPages ?> 页
                </span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="btn-secondary">下一页</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>

