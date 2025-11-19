<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminConfig = require __DIR__ . '/../config/admin.php';

$errors  = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $password = $_POST['password'] ?? '';

    if ($password === '') {
        $errors[] = '密码不能为空。';
    } elseif ($password !== $adminConfig['password']) {
        $errors[] = '密码错误，请重试。';
    } else {
        $_SESSION['admin_logged_in'] = true;
        $success = '登录成功，正在跳转到后台首页…';
        header('Refresh: 1; url=/admin/new_test.php');
    }
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>DoFun 空间 · 后台登录</title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" href="/favicon.ico">
    <style>
        .admin-login-body {
            min-height: 100vh;
            margin: 0;
            background: var(--bg-main);
            font-family: var(--font-stack);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 40px 16px;
        }
        .login-card {
            width: 100%;
            max-width: 420px;
            background: #fff;
            border: 1px solid var(--border);
            border-radius: 16px;
            padding: 32px;
            box-shadow: 0 20px 45px rgba(15,23,42,0.1);
        }
        .login-title {
            font-size: 22px;
            font-weight: 700;
            margin: 0;
            color: var(--text-main);
        }
        .login-subtitle {
            font-size: 13px;
            color: var(--text-muted);
            margin: 4px 0 24px;
        }
        .login-form .field {
            margin-bottom: 18px;
        }
        .login-form button {
            width: 100%;
        }
    </style>
</head>
<body class="admin-login-body">
<div class="login-card">
    <h1 class="login-title">DoFun 空间 · 后台</h1>
    <p class="login-subtitle">DoFun Admin Panel</p>

    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $error): ?>
                <div><?= htmlspecialchars($error) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post" class="login-form">
        <div class="field">
            <label for="password">后台密码</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">登录</button>
    </form>
</div>
</body>
</html>
