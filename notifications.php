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
<div class="container" style="max-width: 900px; margin: 100px auto 40px; padding: 0 20px;">
    <header style="margin-bottom: 30px;">
        <h1 style="font-size: 28px; font-weight: 700; color: var(--text-primary); margin-bottom: 8px;">我的通知</h1>
        <?php if ($unreadCount > 0): ?>
            <p style="color: var(--text-secondary); font-size: 14px;">
                您有 <strong style="color: #ef4444;"><?= $unreadCount ?></strong> 条未读通知
            </p>
        <?php else: ?>
            <p style="color: var(--text-secondary); font-size: 14px;">暂无未读通知</p>
        <?php endif; ?>
    </header>

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom: 20px; padding: 12px 16px; background: #d1fae5; color: #065f46; border-radius: 8px; border: 1px solid #6ee7b7;">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-error" style="margin-bottom: 20px; padding: 12px 16px; background: #fee2e2; color: #991b1b; border-radius: 8px; border: 1px solid #fca5a5;">
            <?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div></div>
        <?php if ($unreadCount > 0): ?>
            <form method="POST" style="display: inline;">
                <?= CSRF::getTokenField() ?>
                <input type="hidden" name="action" value="mark_all_read">
                <button type="submit" class="btn-secondary" style="font-size: 14px; padding: 8px 16px;">全部标记为已读</button>
            </form>
        <?php endif; ?>
    </div>

    <?php if (empty($notifications)): ?>
        <div style="text-align: center; padding: 60px 20px; background: var(--bg-secondary); border-radius: 12px; border: 1px solid var(--border-color);">
            <p style="color: var(--text-secondary); font-size: 16px;">暂无通知</p>
        </div>
    <?php else: ?>
        <div class="notifications-list">
            <?php foreach ($notifications as $notif): ?>
                <div class="notification-item<?= !$notif['is_read'] ? ' unread' : '' ?>" style="
                    background: var(--bg-secondary);
                    border: 1px solid var(--border-color);
                    border-radius: 12px;
                    padding: 16px;
                    margin-bottom: 12px;
                    transition: all 0.3s ease;
                ">
                    <?php if (!$notif['is_read']): ?>
                        <div style="display: inline-block; width: 8px; height: 8px; background: #ef4444; border-radius: 50%; margin-right: 8px; vertical-align: middle;"></div>
                    <?php endif; ?>
                    <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                        <div style="flex: 1;">
                            <h3 style="font-size: 16px; font-weight: 600; color: var(--text-primary); margin-bottom: 6px;">
                                <?= htmlspecialchars($notif['title'], ENT_QUOTES, 'UTF-8') ?>
                            </h3>
                            <?php if (!empty($notif['content'])): ?>
                                <div style="font-size: 14px; color: var(--text-secondary); line-height: 1.6; margin-bottom: 8px;">
                                    <?= $notif['content'] ?>
                                </div>
                            <?php endif; ?>
                            <div style="display: flex; align-items: center; gap: 12px; margin-top: 8px;">
                                <span style="font-size: 12px; color: var(--text-secondary);">
                                    <?= htmlspecialchars($notif['created_at'], ENT_QUOTES, 'UTF-8') ?>
                                </span>
                                <?php if ($notif['type']): ?>
                                    <span class="notification-type notification-type-<?= htmlspecialchars($notif['type'], ENT_QUOTES, 'UTF-8') ?>" style="
                                        display: inline-block;
                                        padding: 2px 8px;
                                        border-radius: 4px;
                                        font-size: 11px;
                                        font-weight: 500;
                                    ">
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
                        <div style="display: flex; gap: 8px; margin-left: 16px;">
                            <?php if (!$notif['is_read']): ?>
                                <form method="POST" style="display: inline;">
                                    <?= CSRF::getTokenField() ?>
                                    <input type="hidden" name="action" value="mark_read">
                                    <input type="hidden" name="notification_id" value="<?= (int)$notif['id'] ?>">
                                    <button type="submit" class="btn-xs" style="font-size: 12px; padding: 4px 10px;">标记已读</button>
                                </form>
                            <?php endif; ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这条通知吗？');">
                                <?= CSRF::getTokenField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_id" value="<?= (int)$notif['id'] ?>">
                                <button type="submit" class="btn-xs btn-ghost" style="font-size: 12px; padding: 4px 10px; color: #ef4444;">删除</button>
                            </form>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: center; gap: 8px; margin-top: 30px;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="btn-secondary" style="padding: 8px 16px;">上一页</a>
                <?php endif; ?>
                <span style="display: inline-flex; align-items: center; padding: 8px 16px; color: var(--text-secondary);">
                    第 <?= $page ?> / <?= $totalPages ?> 页
                </span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="btn-secondary" style="padding: 8px 16px;">下一页</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.notification-item.unread {
    border-left: 3px solid #ef4444;
    background: rgba(239, 68, 68, 0.05);
}

[data-theme="dark"] .notification-item.unread {
    background: rgba(239, 68, 68, 0.1);
}

.notification-type-info {
    background: #dbeafe;
    color: #1e40af;
}

[data-theme="dark"] .notification-type-info {
    background: rgba(59, 130, 246, 0.2);
    color: #93c5fd;
}

.notification-type-success {
    background: #d1fae5;
    color: #065f46;
}

[data-theme="dark"] .notification-type-success {
    background: rgba(16, 185, 129, 0.2);
    color: #6ee7b7;
}

.notification-type-warning {
    background: #fef3c7;
    color: #92400e;
}

[data-theme="dark"] .notification-type-warning {
    background: rgba(245, 158, 11, 0.2);
    color: #fcd34d;
}

.notification-type-error {
    background: #fee2e2;
    color: #991b1b;
}

[data-theme="dark"] .notification-type-error {
    background: rgba(239, 68, 68, 0.2);
    color: #fca5a5;
}
</style>
</body>
</html>

