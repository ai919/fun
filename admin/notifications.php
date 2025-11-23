<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/NotificationHelper.php';
require_once __DIR__ . '/../lib/csrf.php';

$errors = [];
$success = '';

// 处理操作
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        $errors[] = 'CSRF token 验证失败';
    } else {
        $action = $_POST['action'] ?? '';
        
        if ($action === 'send') {
            $title = trim($_POST['title'] ?? '');
            $content = trim($_POST['content'] ?? '');
            $type = $_POST['type'] ?? 'info';
            
            if (empty($title)) {
                $errors[] = '通知标题不能为空';
            } else {
                $count = NotificationHelper::sendToAll($title, $content, $type);
                $success = "已向 {$count} 位用户发送通知";
            }
        } elseif ($action === 'delete') {
            $notificationId = isset($_POST['notification_id']) ? (int)$_POST['notification_id'] : 0;
            if ($notificationId > 0) {
                // 获取要删除的通知信息
                $stmt = $pdo->prepare("SELECT title, content, type FROM notifications WHERE id = ?");
                $stmt->execute([$notificationId]);
                $notif = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($notif) {
                    // 删除所有相同内容的通知
                    $deleteStmt = $pdo->prepare("DELETE FROM notifications WHERE title = ? AND (content = ? OR (content IS NULL AND ? IS NULL)) AND type = ?");
                    $deleteStmt->execute([$notif['title'], $notif['content'], $notif['content'], $notif['type']]);
                    $deletedCount = $deleteStmt->rowCount();
                    $success = "已删除 {$deletedCount} 条通知记录";
                } else {
                    $errors[] = '通知不存在';
                }
            }
        }
    }
}

// 获取通知列表（按内容分组）
$page = isset($_GET['page']) ? max(1, (int)$_GET['page']) : 1;
$limit = 50;
$offset = ($page - 1) * $limit;

// 按title和content分组，统计发送数量和未读数量
$sql = "SELECT 
            title,
            content,
            type,
            MIN(created_at) as first_sent_at,
            MAX(created_at) as last_sent_at,
            COUNT(*) as send_count,
            SUM(CASE WHEN is_read = 0 THEN 1 ELSE 0 END) as unread_count,
            GROUP_CONCAT(DISTINCT id ORDER BY id DESC) as notification_ids
        FROM notifications
        GROUP BY title, content, type
        ORDER BY last_sent_at DESC
        LIMIT :limit OFFSET :offset";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$notifications = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取总数（分组后的数量）
$totalStmt = $pdo->query("SELECT COUNT(DISTINCT CONCAT(title, '|', COALESCE(content, ''), '|', type)) FROM notifications");
$totalNotifications = (int)$totalStmt->fetchColumn();
$totalPages = ceil($totalNotifications / $limit);

$pageTitle = '通知管理';
$activeMenu = 'notifications';

ob_start();
?>
<div class="admin-card" style="margin-bottom: 20px;">
    <h2 class="admin-page-title" style="font-size: 18px; margin-bottom: 16px;">发送通知</h2>
    
    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom: 16px; padding: 12px; background: #d1fae5; color: #065f46; border-radius: 8px;">
            <?= htmlspecialchars($success) ?>
        </div>
    <?php endif; ?>
    
    <?php if (!empty($errors)): ?>
        <div class="alert alert-error" style="margin-bottom: 16px; padding: 12px; background: #fee2e2; color: #991b1b; border-radius: 8px;">
            <?php foreach ($errors as $error): ?>
                <div><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" class="admin-form">
        <?= CSRF::getTokenField() ?>
        <input type="hidden" name="action" value="send">
        
        <input type="hidden" name="user_id" value="0">
        
        <div class="form-field" style="margin-bottom: 16px;">
            <label class="form-label">通知类型</label>
            <select name="type" class="form-select">
                <option value="info">信息</option>
                <option value="success">成功</option>
                <option value="warning">警告</option>
                <option value="error">错误</option>
            </select>
        </div>
        
        <div class="form-field" style="margin-bottom: 16px;">
            <label class="form-label">标题 <span style="color: #ef4444;">*</span></label>
            <input type="text" name="title" class="form-input" required maxlength="255" placeholder="通知标题">
        </div>
        
        <div class="form-field" style="margin-bottom: 16px;">
            <label class="form-label">内容</label>
            <div class="rte-wrapper">
                <div class="rte-toolbar">
                    <button type="button" class="btn btn-xs btn-ghost" data-cmd="bold">粗体</button>
                    <button type="button" class="btn btn-xs btn-ghost" data-cmd="italic">斜体</button>
                    <span class="rte-toolbar__divider"></span>
                    <div class="rte-color-wrapper">
                        <button type="button" class="btn btn-xs btn-ghost rte-color-trigger">颜色</button>
                        <div class="rte-color-picker" style="display: none; position: absolute; background: white; padding: 8px; border: 1px solid #d1d5db; border-radius: 6px; z-index: 1000; margin-top: 4px;">
                            <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 4px;">
                                <button type="button" class="rte-color-btn" data-color="#ef4444" style="width: 20px; height: 20px; background: #ef4444; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                <button type="button" class="rte-color-btn" data-color="#f59e0b" style="width: 20px; height: 20px; background: #f59e0b; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                <button type="button" class="rte-color-btn" data-color="#10b981" style="width: 20px; height: 20px; background: #10b981; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                <button type="button" class="rte-color-btn" data-color="#3b82f6" style="width: 20px; height: 20px; background: #3b82f6; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                <button type="button" class="rte-color-btn" data-color="#8b5cf6" style="width: 20px; height: 20px; background: #8b5cf6; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                <button type="button" class="rte-color-btn" data-color="#ec4899" style="width: 20px; height: 20px; background: #ec4899; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                <button type="button" class="rte-color-btn" data-color="#fde68a" style="width: 20px; height: 20px; background: #fde68a; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                <button type="button" class="rte-color-btn" data-color="#d1fae5" style="width: 20px; height: 20px; background: #d1fae5; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                            </div>
                        </div>
                    </div>
                    <span class="rte-toolbar__divider"></span>
                    <button type="button" class="btn btn-xs btn-ghost" data-cmd="createLink">链接</button>
                    <button type="button" class="btn btn-xs btn-ghost" data-cmd="insertImage">图片</button>
                </div>
                <div id="notification-content-editor" class="rte-editor" contenteditable="true" style="min-height: 100px; border: 1px solid #d1d5db; border-radius: 6px; padding: 12px; background: white;"></div>
                <textarea name="content" class="rte-hidden-textarea" style="display:none;"></textarea>
            </div>
            <p class="form-help">支持富文本（粗体、斜体、颜色、链接、图片）</p>
        </div>
        
        <button type="submit" class="btn btn-primary">发送通知</button>
    </form>
</div>

<div class="admin-card">
    <h2 class="admin-page-title" style="font-size: 18px; margin-bottom: 16px;">通知列表</h2>
    
    <?php if (empty($notifications)): ?>
        <p class="admin-table__muted">暂无通知</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
                <tr>
                    <th>标题</th>
                    <th>内容</th>
                    <th style="width: 100px;">类型</th>
                    <th style="width: 100px;">发送数量</th>
                    <th style="width: 100px;">未读数量</th>
                    <th style="width: 150px;">首次发送</th>
                    <th style="width: 100px;">操作</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $notif): ?>
                    <tr>
                        <td>
                            <strong><?= htmlspecialchars($notif['title']) ?></strong>
                        </td>
                        <td>
                            <?php if (!empty($notif['content'])): ?>
                                <div style="max-width: 300px; overflow: hidden; text-overflow: ellipsis;">
                                    <?= $notif['content'] ?>
                                </div>
                            <?php else: ?>
                                <span style="color: #6b7280;">（无内容）</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <span class="notification-type notification-type-<?= htmlspecialchars($notif['type']) ?>" style="
                                display: inline-block;
                                padding: 4px 8px;
                                border-radius: 4px;
                                font-size: 12px;
                                font-weight: 500;
                            ">
                                <?php
                                $typeLabels = [
                                    'info' => '信息',
                                    'success' => '成功',
                                    'warning' => '警告',
                                    'error' => '错误'
                                ];
                                echo htmlspecialchars($typeLabels[$notif['type']] ?? $notif['type']);
                                ?>
                            </span>
                        </td>
                        <td>
                            <strong><?= (int)$notif['send_count'] ?></strong>
                        </td>
                        <td>
                            <?php if ((int)$notif['unread_count'] > 0): ?>
                                <span style="color: #ef4444; font-weight: 600;"><?= (int)$notif['unread_count'] ?></span>
                            <?php else: ?>
                                <span style="color: #6b7280;">0</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <small style="color: #6b7280;">
                                <?= htmlspecialchars($notif['first_sent_at']) ?>
                            </small>
                        </td>
                        <td class="admin-table__actions">
                            <?php
                            $ids = explode(',', $notif['notification_ids']);
                            $firstId = (int)($ids[0] ?? 0);
                            ?>
                            <form method="POST" style="display: inline;" onsubmit="return confirm('确定要删除这条通知的所有记录吗？');">
                                <?= CSRF::getTokenField() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="notification_id" value="<?= $firstId ?>">
                                <button type="submit" class="btn btn-xs btn-ghost" style="color: #ef4444;">删除</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($totalPages > 1): ?>
            <div style="display: flex; justify-content: center; gap: 8px; margin-top: 20px;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?= $page - 1 ?>" class="btn btn-xs">上一页</a>
                <?php endif; ?>
                <span style="display: inline-flex; align-items: center; padding: 4px 12px; color: #6b7280;">
                    第 <?= $page ?> / <?= $totalPages ?> 页
                </span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?= $page + 1 ?>" class="btn btn-xs">下一页</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<style>
.notification-type-info {
    background: #dbeafe;
    color: #1e40af;
}

.notification-type-success {
    background: #d1fae5;
    color: #065f46;
}

.notification-type-warning {
    background: #fef3c7;
    color: #92400e;
}

.notification-type-error {
    background: #fee2e2;
    color: #991b1b;
}

.rte-wrapper {
    position: relative;
}

.rte-toolbar {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px;
    background: #f9fafb;
    border: 1px solid #d1d5db;
    border-bottom: none;
    border-radius: 6px 6px 0 0;
}

.rte-toolbar__divider {
    width: 1px;
    height: 20px;
    background: #d1d5db;
}

.rte-color-wrapper {
    position: relative;
}

.rte-editor {
    min-height: 100px;
    border: 1px solid #d1d5db;
    border-radius: 0 0 6px 6px;
    padding: 12px;
    background: white;
    font-size: 14px;
    line-height: 1.5;
    overflow-y: auto;
}

.rte-editor:focus {
    outline: none;
    border-color: #4f46e5;
    box-shadow: 0 0 0 1px rgba(79, 70, 229, 0.15);
}

.rte-editor p {
    margin: 0 0 4px;
}

.rte-editor img {
    max-width: 100%;
    border-radius: 8px;
    margin: 4px 0;
}

.rte-editor a {
    color: #2563eb;
    text-decoration: underline;
}
</style>
<script>
document.addEventListener('DOMContentLoaded', function() {
    var editor = document.getElementById('notification-content-editor');
    var hidden = document.querySelector('textarea[name="content"]');
    var toolbar = editor?.previousElementSibling;
    var form = editor?.closest('form');
    
    if (!editor || !hidden || !toolbar || !form) return;
    
    function syncHidden() {
        hidden.value = editor.innerHTML;
    }
    
    editor.addEventListener('input', syncHidden);
    editor.addEventListener('blur', syncHidden);
    
    toolbar.addEventListener('click', function(e) {
        var btn = e.target.closest('[data-cmd]');
        if (!btn) return;
        var cmd = btn.getAttribute('data-cmd');
        var val = btn.getAttribute('data-value') || null;
        
        editor.focus();
        
        if (cmd === 'createLink') {
            var url = window.prompt('请输入链接URL（例如：https://example.com）');
            if (url) {
                if (!/^https?:\/\//i.test(url)) {
                    url = 'https://' + url;
                }
                document.execCommand('createLink', false, url);
            }
        } else if (cmd === 'insertImage') {
            var imgUrl = window.prompt('请输入图片URL');
            if (imgUrl) {
                document.execCommand('insertImage', false, imgUrl);
            }
        } else if (cmd === 'foreColor' || cmd === 'backColor') {
            if (val) {
                document.execCommand(cmd, false, val);
            }
        } else {
            document.execCommand(cmd, false, null);
        }
        
        syncHidden();
    });
    
    // 颜色选择器
    var colorTrigger = toolbar.querySelector('.rte-color-trigger');
    var colorPicker = toolbar.querySelector('.rte-color-picker');
    if (colorTrigger && colorPicker) {
        colorTrigger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            var isVisible = colorPicker.style.display !== 'none';
            document.querySelectorAll('.rte-color-picker').forEach(function(picker) {
                picker.style.display = 'none';
            });
            colorPicker.style.display = isVisible ? 'none' : 'block';
        });
        
        colorPicker.querySelectorAll('.rte-color-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var color = this.getAttribute('data-color');
                editor.focus();
                document.execCommand('foreColor', false, color);
                colorPicker.style.display = 'none';
                syncHidden();
            });
        });
        
        document.addEventListener('click', function(e) {
            if (!colorPicker.contains(e.target) && !colorTrigger.contains(e.target)) {
                colorPicker.style.display = 'none';
            }
        });
    }
    
    form.addEventListener('submit', function() {
        syncHidden();
    });
});
</script>
<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

