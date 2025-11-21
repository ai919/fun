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

        // 这里的 $email 实际用于用户名
        $username = trim($email);

        // 用户名规则：英文 + 数字，6 位
        if (!preg_match('/^[A-Za-z0-9]{3,25}$/', $username)) {
            return ['success' => false, 'message' => '用户名需为 3-25 位英文和数字组合'];
        }
        $pwdLen = mb_strlen($password);
        if ($pwdLen < 6 || $pwdLen > 20) {
            return ['success' => false, 'message' => '密码长度需在 6-20 位'];
        }
        if ($nickname !== null && $nickname !== '') {
            $nLen = mb_strlen($nickname);
            if ($nLen < 3 || $nLen > 15) {
                return ['success' => false, 'message' => '昵称长度需在 3-15 位'];
            }
        }

        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $username]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => '该用户名已被注册'];
        }

        $hash = password_hash($password, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO users (email, password_hash, nickname)
            VALUES (:email, :hash, :nickname)
        ");
        $stmt->execute([
            ':email'    => $username,
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
        $username = trim($email);
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = :email LIMIT 1");
        $stmt->execute([':email' => $username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || empty($user['password_hash']) || !password_verify($password, $user['password_hash'])) {
            return ['success' => false, 'message' => '用户名或密码不正确'];
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
