<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$config = require __DIR__ . '/../backup_config.php';
$dbConf = $config['db'];

try {
    $pdo = new PDO(
        'mysql:host=' . $dbConf['host'] . ';port=' . $dbConf['port'] . ';dbname=' . $dbConf['name'] . ';charset=utf8mb4',
        $dbConf['user'],
        $dbConf['password'],
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
} catch (PDOException $e) {
    die('数据库连接失败');
}

if (!empty($_SESSION['admin_id'])) {
    header('Location: /admin/index.php');
    exit;
}

$error    = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE username = :u AND is_active = 1 LIMIT 1");
    $stmt->execute([':u' => $username]);
    $admin = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($admin && password_verify($password, $admin['password_hash'])) {
        $_SESSION['admin_id'] = (int)$admin['id'];
        header('Location: /admin/index.php');
        exit;
    } else {
        $error = '账号或密码错误';
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

    <form method="post">
        <div style="margin-bottom:10px;">
            <label>账号</label>
            <input type="text" name="username" value="<?= htmlspecialchars($username) ?>" required>
        </div>
        <div style="margin-bottom:4px;">
            <label>密码</label>
            <input type="password" name="password" required>
        </div>
        <button class="btn" type="submit">登录</button>
        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>
    </form>
</div>
</body>
</html>
