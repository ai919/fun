<?php
require_once __DIR__ . '/lib/user_auth.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = $_POST['email'] ?? '';
    $password = $_POST['password'] ?? '';
    $nickname = $_POST['nickname'] ?? null;

    $result = UserAuth::register($email, $password, $nickname);
    if ($result['success']) {
        header('Location: /my_tests.php');
        exit;
    } else {
        $error = $result['message'] ?? '注册失败，请稍后再试';
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
</head>
<body class="page-auth">
<div class="auth-container">
    <h1>注册 DoFun账号</h1>
    <?php if (!empty($error)): ?>
        <div class="auth-error"><?php echo htmlspecialchars($error); ?></div>
    <?php endif; ?>
    <form method="post">
        <div class="form-group">
            <label>邮箱</label>
            <input type="email" name="email" required placeholder="you@example.com">
        </div>
        <div class="form-group">
            <label>密码（至少 6 位）</label>
            <input type="password" name="password" required minlength="6">
        </div>
        <div class="form-group">
            <label>昵称（可选）</label>
            <input type="text" name="nickname" placeholder="怎么称呼你">
        </div>
        <button type="submit" class="btn btn-primary">注册并开始测验</button>
    </form>
    <p class="auth-footer">
        已有账号？<a href="/login.php">直接登录</a>
    </p>
    <p class="auth-footer">
        <a href="/">返回首页</a>
    </p>
</div>
</body>
</html>
