<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../lib/db_connect.php';

$errors   = [];
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $errors[] = '请输入用户名和密码。';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = ? AND is_active = 1 LIMIT 1");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($password, $user['password_hash'])) {
            $errors[] = '用户名或密码错误。';
        } else {
            $_SESSION['admin_user'] = [
                'id'           => (int)$user['id'],
                'username'     => $user['username'],
                'display_name' => $user['display_name'] ?: $user['username'],
            ];
            header('Location: /admin/index.php');
            exit;
        }
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

    <form method="post" class="login-form">
        <div class="field">
            <label for="username">用户名</label>
            <input type="text" id="username" name="username" required value="<?= htmlspecialchars($username) ?>">
        </div>
        <div class="field">
            <label for="password">密码</label>
            <input type="password" id="password" name="password" required>
        </div>
        <button type="submit" class="btn btn-primary">登录</button>
    </form>
</div>
</body>
</html>
