<?php
require_once __DIR__ . '/lib/user_auth.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/i18n.php';
require_once __DIR__ . '/lib/topbar.php';

// 初始化国际化
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
I18n::setLanguage(I18n::detectLanguage());

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        $error = I18n::t('error.csrf_failed');
    } else {
        $username = $_POST['username'] ?? '';
        $password = $_POST['password'] ?? '';

        $result = UserAuth::login($username, $password);
        if ($result['success']) {
            header('Location: /profile.php');
            exit;
        } else {
            $error = $result['message'] ?? I18n::t('error.login_failed');
        }
    }
}

$user = UserAuth::currentUser();
if ($user) {
    header('Location: /profile.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>登录 DoFun</title>
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
    <h1>登录 DoFun</h1>
    <?php if (!empty($error)): ?>
        <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <?php echo CSRF::getTokenField(); ?>
        <div class="form-group">
            <label for="username"><?php echo I18n::t('form.username'); ?></label>
            <input type="text" id="username" name="username" required placeholder="<?php echo I18n::t('form.username'); ?>" maxlength="25" minlength="3" aria-required="true">
        </div>
        <div class="form-group">
            <label for="password"><?php echo I18n::t('form.password'); ?></label>
            <input type="password" id="password" name="password" required maxlength="20" minlength="6" aria-required="true">
        </div>
        <button type="submit" class="btn btn-primary auth-submit-btn" aria-label="<?php echo I18n::t('button.login'); ?>"><?php echo I18n::t('button.login'); ?></button>
    </form>
    <p class="auth-footer">
        还没有账号？ <a href="/register.php">立即注册</a>
        &nbsp;&nbsp;&nbsp;
        <a href="/">返回首页</a>
    </p>
</div>
</body>
</html>
