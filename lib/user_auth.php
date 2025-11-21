<?php
// lib/user_auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';

class UserAuth
{
    public static function register($email, $password, $nickname = null)
    {
        global $pdo;

        $email = trim(strtolower($email));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return ['success' => false, 'message' => '邮箱格式不正确'];
        }
        if (mb_strlen($password) < 6) {
            return ['success' => false, 'message' => '密码至少 6 位'];
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => '这个邮箱已经注册过了'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password_hash, nickname)
            VALUES (:email, :hash, :nickname)
        ");
        $stmt->execute([
            ':email'    => $email,
            ':hash'     => $hash,
            ':nickname' => $nickname ?: null,
        ]);

        $userId = (int)$pdo->lastInsertId();
        $_SESSION['user_id'] = $userId;

        return ['success' => true, 'user_id' => $userId];
    }

    public static function login($email, $password)
    {
        global $pdo;
        $email = trim(strtolower($email));
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => '邮箱或密码不正确'];
        }

        $_SESSION['user_id'] = (int)$user['id'];

        $update = $pdo->prepare("UPDATE users SET last_login_at = NOW() WHERE id = :id");
        $update->execute([':id' => $user['id']]);

        return ['success' => true, 'user_id' => (int)$user['id']];
    }

    public static function logout(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        unset($_SESSION['user_id']);
    }

    public static function currentUser(): ?array
    {
        global $pdo;
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
        if (empty($_SESSION['user_id'])) {
            return null;
        }
        $userId = (int)$_SESSION['user_id'];

        static $cached = null;
        if ($cached !== null && (int)($cached['id'] ?? 0) === $userId) {
            return $cached;
        }

        $stmt = $pdo->prepare("SELECT id, email, nickname, created_at FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            unset($_SESSION['user_id']);
            return null;
        }

        $cached = $user;
        return $user;
    }

    public static function requireLogin(): array
    {
        $user = self::currentUser();
        if (!$user) {
            header('Location: /login.php');
            exit;
        }
        return $user;
    }
}
