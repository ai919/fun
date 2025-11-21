<?php
require_once __DIR__ . '/lib/user_auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = UserAuth::login($email, $password);
    if ($result['success']) {
        header('Location: /my_tests.php');
        exit;
    } else {
        $error = $result['message'] ?? '登录失败，请稍后再试';
    }
}

$user = UserAuth::currentUser();
$redirectTarget = '/my_tests.php';
if ($user) {
    header('Location: ' . $redirectTarget);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>登录 DoFun</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="page-auth">
<div class="auth-container">
    <h1>登录 DoFun</h1>
    <?php if (!empty($error)): ?>
        <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>邮箱</label>
            <input type="email" name="email" required placeholder="you@example.com">
        </div>
        <div class="form-group">
            <label>密码</label>
            <input type="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">登录</button>
    </form>
    <p class="auth-footer">
        还没有账号？<a href="/register.php">免费注册</a>
    </p>
    <p class="auth-footer">
        <a href="/">返回首页</a>
    </p>
</div>
</body>
</html>
