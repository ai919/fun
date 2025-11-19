<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function current_admin(): ?array
{
    return $_SESSION['admin_user'] ?? null;
}

function is_admin_logged_in(): bool
{
    return current_admin() !== null;
}

function require_admin_login(): void
{
    if (!is_admin_logged_in()) {
        header('Location: /admin/login.php');
        exit;
    }
}
