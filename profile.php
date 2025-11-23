<?php
require_once __DIR__ . '/lib/user_auth.php';
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/topbar.php';

$user = UserAuth::requireLogin();

$errors = [];
$success = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        $errors['general'] = 'CSRF token 验证失败，请刷新页面后重试';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_username') {
            $newUsername = trim($_POST['username'] ?? '');
            $result = UserAuth::updateUsername($user['id'], $newUsername);
            if ($result['success']) {
                $success = '用户名更新成功';
                // 重新获取用户信息
                $user = UserAuth::currentUser();
            } else {
                $errors['username'] = $result['message'] ?? '用户名更新失败';
            }
        } elseif ($action === 'update_password') {
            $oldPassword = $_POST['old_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($newPassword !== $confirmPassword) {
                $errors['password'] = '两次输入的密码不一致';
            } else {
                $result = UserAuth::updatePassword($user['id'], $oldPassword, $newPassword);
                if ($result['success']) {
                    $success = '密码更新成功';
                } else {
                    $errors['password'] = $result['message'] ?? '密码更新失败';
                }
            }
        } elseif ($action === 'update_nickname') {
            $nickname = trim($_POST['nickname'] ?? '');
            $result = UserAuth::updateNickname($user['id'], $nickname);
            if ($result['success']) {
                $success = '昵称更新成功';
                // 重新获取用户信息
                $user = UserAuth::currentUser();
            } else {
                $errors['nickname'] = $result['message'] ?? '昵称更新失败';
            }
        }
    }
}

// 获取测验记录统计
$statsStmt = $pdo->prepare("
    SELECT COUNT(*) as total_runs
    FROM test_runs
    WHERE user_id = :uid
");
$statsStmt->execute([':uid' => $user['id']]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
$totalRuns = (int)($stats['total_runs'] ?? 0);

// 获取最近的测验记录（最多5条）
$recentStmt = $pdo->prepare("
    SELECT
        r.id,
        r.created_at,
        r.total_score,
        r.share_token,
        t.title AS test_title,
        t.slug   AS test_slug,
        res.title AS result_title
    FROM test_runs r
    INNER JOIN tests t ON r.test_id = t.id
    LEFT JOIN results res ON r.result_id = res.id
    WHERE r.user_id = :uid
    ORDER BY r.created_at DESC
    LIMIT 5
");
$recentStmt->execute([':uid' => $user['id']]);
$recentRuns = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户资料 - DoFun</title>
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
<body class="page-profile">
<?php render_topbar(); ?>
<div class="profile-container">
    <header class="profile-header">
        <h1>用户资料</h1>
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

    <div class="profile-content">
        <!-- 修改用户名 -->
        <section class="profile-section">
            <h2 class="profile-section-title">修改用户名</h2>
            <form method="POST" class="profile-form">
                <?= CSRF::getTokenField() ?>
                <input type="hidden" name="action" value="update_username">
                <div class="form-group">
                    <label for="username">新用户名</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required
                        pattern="[A-Za-z0-9]{3,25}"
                        title="3-25 位英文和数字组合"
                    >
                    <small class="form-hint">用户名需为 3-25 位英文和数字组合</small>
                    <?php if (isset($errors['username'])): ?>
                        <div class="form-error"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-primary">更新用户名</button>
            </form>
        </section>

        <!-- 修改密码 -->
        <section class="profile-section">
            <h2 class="profile-section-title">修改密码</h2>
            <form method="POST" class="profile-form">
                <?= CSRF::getTokenField() ?>
                <input type="hidden" name="action" value="update_password">
                <div class="form-group">
                    <label for="old_password">原密码</label>
                    <input 
                        type="password" 
                        id="old_password" 
                        name="old_password" 
                        required
                        minlength="6"
                        maxlength="20"
                    >
                </div>
                <div class="form-group">
                    <label for="new_password">新密码</label>
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        required
                        minlength="6"
                        maxlength="20"
                    >
                    <small class="form-hint">密码长度需在 6-20 位</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">确认新密码</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required
                        minlength="6"
                        maxlength="20"
                    >
                    <?php if (isset($errors['password'])): ?>
                        <div class="form-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-primary">更新密码</button>
            </form>
        </section>

        <!-- 修改昵称 -->
        <section class="profile-section">
            <h2 class="profile-section-title">修改昵称</h2>
            <form method="POST" class="profile-form">
                <?= CSRF::getTokenField() ?>
                <input type="hidden" name="action" value="update_nickname">
                <div class="form-group">
                    <label for="nickname">昵称</label>
                    <input 
                        type="text" 
                        id="nickname" 
                        name="nickname" 
                        value="<?= htmlspecialchars($user['nickname'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        maxlength="15"
                    >
                    <small class="form-hint">昵称长度需在 3-15 位，留空则清除昵称</small>
                    <?php if (isset($errors['nickname'])): ?>
                        <div class="form-error"><?= htmlspecialchars($errors['nickname'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-primary">更新昵称</button>
            </form>
        </section>

        <!-- 测验记录 -->
        <section class="profile-section">
            <div class="profile-section-header">
                <h2 class="profile-section-title">测验记录</h2>
                <a href="/my_tests.php" class="btn-link">查看全部 →</a>
            </div>
            
            <div class="profile-stats">
                <div class="stat-item">
                    <span class="stat-label">总测验次数</span>
                    <span class="stat-value"><?= $totalRuns ?></span>
                </div>
            </div>

            <?php if (empty($recentRuns)): ?>
                <p class="profile-empty">你还没有任何测验记录，去首页找一个喜欢的测验试试吧～</p>
                <p><a href="/">返回首页</a></p>
            <?php else: ?>
                <div class="profile-runs-list">
                    <?php foreach ($recentRuns as $run): ?>
                        <div class="profile-run-item">
                            <div class="run-item-main">
                                <h3 class="run-item-title">
                                    <a href="/test.php?slug=<?= urlencode($run['test_slug']) ?>">
                                        <?= htmlspecialchars($run['test_title'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </h3>
                                <div class="run-item-meta">
                                    <?php if ($run['result_title']): ?>
                                        <span class="run-item-result">
                                            <?php
                                            $shareLink = !empty($run['share_token'])
                                                ? '/result.php?token=' . urlencode($run['share_token'])
                                                : '';
                                            ?>
                                            <?php if ($shareLink): ?>
                                                <a href="<?= $shareLink ?>">
                                                    <?= htmlspecialchars($run['result_title'], ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($run['result_title'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($run['total_score'] !== null): ?>
                                        <span class="run-item-score">得分: <?= (int)$run['total_score'] ?></span>
                                    <?php endif; ?>
                                    <span class="run-item-time"><?= htmlspecialchars($run['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalRuns > 5): ?>
                    <div style="margin-top: 16px; text-align: center;">
                        <a href="/my_tests.php" class="btn-secondary">查看全部记录</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</div>
</body>
</html>

