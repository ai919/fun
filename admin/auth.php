<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../backup_config.php';
$backupConfig = require __DIR__ . '/../backup_config.php';

function admin_auth_pdo(): PDO
{
    static $pdo = null;
    global $backupConfig;
    if ($pdo === null) {
        $dbConf = $backupConfig['db'];
        $pdo = new PDO(
            'mysql:host=' . $dbConf['host'] . ';port=' . $dbConf['port'] . ';dbname=' . $dbConf['name'] . ';charset=utf8mb4',
            $dbConf['user'],
            $dbConf['password'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
    }
    return $pdo;
}

function current_admin(): ?array
{
    static $cached = null;
    if ($cached !== null) {
        return $cached;
    }
    if (empty($_SESSION['admin_id'])) {
        return null;
    }
    $pdo = admin_auth_pdo();
    $stmt = $pdo->prepare("SELECT * FROM admin_users WHERE id = :id AND is_active = 1 LIMIT 1");
    $stmt->execute([':id' => (int)$_SESSION['admin_id']]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($user) {
        $cached = $user;
        return $cached;
    }
    session_destroy();
    return null;
}

function is_admin_logged_in(): bool
{
    return current_admin() !== null;
}

function require_admin_login(): void
{
    if (!current_admin()) {
        header('Location: /admin/login.php');
        exit;
    }
}

$currentAdmin = current_admin();
