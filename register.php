<?php
require_once __DIR__ . '/lib/user_auth.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/topbar.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        $error = 'CSRF token 验证失败，请刷新页面后重试';
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';
        $nickname = $_POST['nickname'] ?? null;

        $result = UserAuth::register($username, $password, $nickname);
        if ($result['success']) {
            header('Location: /my_tests.php');
            exit;
        } else {
            $error = $result['message'] ?? '注册失败，请稍后再试';
        }
    }
}

$user = UserAuth::currentUser();
if ($user) {
    header('Location: /my_tests.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>注册 DoFun账号</title>
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
<body class="page-auth">
<?php render_topbar(); ?>
<div class="auth-container">
    <h1>注册 DoFun账号</h1>
    <?php if (!empty($error)): ?>
        <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <?php echo CSRF::getTokenField(); ?>
        <div class="form-group">
            <label>用户名（英文+数字 3-25 位）</label>
            <input type="text" name="username" required maxlength="25" minlength="3" placeholder="如：df1234">
        </div>
        <div class="form-group">
            <label>密码（6-20 位）</label>
            <input type="password" name="password" required minlength="6" maxlength="20">
        </div>
        <div class="form-group">
            <label>昵称（可选，3-15 位）</label>
            <input type="text" name="nickname" placeholder="怎么称呼你" minlength="3" maxlength="15">
        </div>
        <button type="submit" class="btn btn-primary auth-submit-btn">注册</button>
    </form>
    <p class="auth-footer">
        已有账号？ <a href="/login.php">直接登录</a>
        &nbsp;&nbsp;&nbsp;
        <a href="/">返回首页</a>
    </p>
</div>
</body>
</html>
