<?php
require_once __DIR__ . '/lib/user_auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';

    $result = UserAuth::login($username, $password);
    if ($result['success']) {
        header('Location: /my_tests.php');
        exit;
    } else {
        $error = $result['message'] ?? '登录失败，请稍后再试';
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
            <label>用户名</label>
            <input type="text" name="username" required placeholder="注册时设置的用户名" maxlength="25" minlength="3">
        </div>
        <div class="form-group">
            <label>密码</label>
            <input type="password" name="password" required maxlength="20" minlength="6">
        </div>
        <button type="submit" class="btn btn-primary auth-submit-btn">登录</button>
    </form>
    <p class="auth-footer">
        还没有账号？ <a href="/register.php">立即注册</a>
        &nbsp;&nbsp;&nbsp;
        <a href="/">返回首页</a>
    </p>
</div>
</body>
</html>
