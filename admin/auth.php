<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require __DIR__ . '/../lib/db_connect.php';
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
    global $pdo;
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
