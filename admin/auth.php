<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$adminConfig = require __DIR__ . '/../config/admin.php';

function is_admin_logged_in(): bool
{
    return !empty($_SESSION['admin_logged_in']);
}

function require_admin_login(): void
{
    if (!is_admin_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}
