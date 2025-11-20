<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require __DIR__ . '/../lib/db_connect.php';

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
    <title>DoFun 管理登录</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        body {
            margin:0;
            font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif;
            background:#020617;
            color:#e5e7eb;
            display:flex;
            align-items:center;
            justify-content:center;
            min-height:100vh;
        }
        .card {
            width:100%;
            max-width:360px;
            background:#020617;
            border-radius:14px;
            padding:22px 20px 24px;
            box-shadow:0 18px 45px rgba(15,23,42,0.9);
            border:1px solid rgba(148,163,184,0.35);
        }
        h1 {
            margin:0 0 6px;
            font-size:18px;
        }
        .sub {
            font-size:12px;
            color:#9ca3af;
            margin-bottom:16px;
        }
        label {
            display:block;
            font-size:13px;
            margin-bottom:4px;
        }
        input[type="text"], input[type="password"] {
            width:100%;
            padding:8px 10px;
            border-radius:8px;
            border:1px solid rgba(148,163,184,0.55);
            background:#020617;
            color:#f9fafb;
            outline:none;
            font-size:14px;
        }
        input:focus {
            border-color:#6366f1;
            box-shadow:0 0 0 1px rgba(99,102,241,0.4);
        }
        .btn {
            width:100%;
            margin-top:14px;
            padding:9px 0;
            border-radius:999px;
            border:none;
            background:#4f46e5;
            color:#fff;
            font-size:14px;
            font-weight:600;
            cursor:pointer;
            box-shadow:0 12px 26px rgba(79,70,229,0.6);
        }
        .error {
            margin-top:10px;
            font-size:12px;
            color:#f97373;
        }
    </style>
</head>
<body>
<div class="card">
    <h1>DoFun 管理后台</h1>
    <div class="sub">请登录以管理测验、结果与备份。</div>
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
