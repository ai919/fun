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
        $errors[] = '密码错误。';
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
    <title>后台登录 · fun_quiz</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" href="/favicon.ico">
    <style>
        .login-box {
            max-width: 420px;
            margin: 60px auto 0;
            padding: 20px;
            border-radius: 8px;
            border: 1px solid #eee;
            background: #fafafa;
        }
        .errors, .success {
            padding: 10px;
            border-radius: 6px;
            margin-bottom: 12px;
        }
        .errors {
            background: #ffecec;
            border: 1px solid #ffb4b4;
        }
        .success {
            background: #e7f9ec;
            border: 1px solid #9ad5aa;
        }
        .field { margin-bottom: 12px; }
        .field label { display: block; margin-bottom: 4px; }
        .field input[type="password"] {
            width: 100%;
            padding: 6px 8px;
            box-sizing: border-box;
        }
    </style>
</head>
<body>
<div class="login-box">
    <h1>后台登录</h1>
    <?php if ($errors): ?>
        <div class="errors">
            <?php foreach ($errors as $e): ?>
                <div><?= htmlspecialchars($e) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <form method="post">
        <div class="field">
            <label for="password">后台密码</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit">登录</button>
    </form>
</div>
</body>
</html>
