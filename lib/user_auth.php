<?php
// lib/user_auth.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/db_connect.php';

class UserAuth
{
    // 类级别的用户缓存，所有方法共享
    private static $userCache = null;

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

    /**
     * 检查列是否存在
     */
    private static function columnExists(PDO $pdo, string $table, string $column): bool
    {
        static $cache = [];
        $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $key = "{$dbName}.{$table}.{$column}";
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$dbName, $table, $column]);
        $cache[$key] = (bool)$stmt->fetchColumn();
        return $cache[$key];
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

        // 使用类级别的缓存
        if (self::$userCache !== null && (int)(self::$userCache['id'] ?? 0) === $userId) {
            return self::$userCache;
        }

        // 动态构建查询字段，只选择存在的列
        $columns = ['id', 'email', 'nickname', 'created_at'];
        $optionalColumns = ['gender', 'birth_date', 'zodiac', 'chinese_zodiac', 'personality'];
        
        foreach ($optionalColumns as $col) {
            if (self::columnExists($pdo, 'users', $col)) {
                $columns[] = $col;
            }
        }

        $fields = implode(', ', $columns);
        $stmt = $pdo->prepare("SELECT {$fields} FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user) {
            unset($_SESSION['user_id']);
            self::$userCache = null;
            return null;
        }

        self::$userCache = $user;
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

    /**
     * 更新用户名
     */
    public static function updateUsername($userId, $newUsername)
    {
        global $pdo;

        $username = trim($newUsername);

        // 用户名规则：英文 + 数字，3-25 位
        if (!preg_match('/^[A-Za-z0-9]{3,25}$/', $username)) {
            return ['success' => false, 'message' => '用户名需为 3-25 位英文和数字组合'];
        }

        // 检查用户名是否已被使用
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = :email AND id != :id LIMIT 1");
        $stmt->execute([':email' => $username, ':id' => $userId]);
        if ($stmt->fetch()) {
            return ['success' => false, 'message' => '该用户名已被使用'];
        }

        // 更新用户名
        $stmt = $pdo->prepare("UPDATE users SET email = :email WHERE id = :id");
        $stmt->execute([':email' => $username, ':id' => $userId]);

        // 清除类级别的缓存
        self::$userCache = null;

        return ['success' => true];
    }

    /**
     * 更新密码
     */
    public static function updatePassword($userId, $oldPassword, $newPassword)
    {
        global $pdo;

        // 验证旧密码
        $stmt = $pdo->prepare("SELECT password_hash FROM users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$user || !password_verify($oldPassword, $user['password_hash'])) {
            return ['success' => false, 'message' => '原密码不正确'];
        }

        // 验证新密码长度
        $pwdLen = mb_strlen($newPassword);
        if ($pwdLen < 6 || $pwdLen > 20) {
            return ['success' => false, 'message' => '密码长度需在 6-20 位'];
        }

        // 更新密码
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password_hash = :hash WHERE id = :id");
        $stmt->execute([':hash' => $hash, ':id' => $userId]);

        return ['success' => true];
    }

    /**
     * 更新昵称
     */
    public static function updateNickname($userId, $nickname)
    {
        global $pdo;

        $nickname = trim($nickname);

        // 如果昵称不为空，验证长度
        if ($nickname !== '') {
            $nLen = mb_strlen($nickname);
            if ($nLen < 3 || $nLen > 15) {
                return ['success' => false, 'message' => '昵称长度需在 3-15 位'];
            }
        }

        // 更新昵称
        $stmt = $pdo->prepare("UPDATE users SET nickname = :nickname WHERE id = :id");
        $stmt->execute([':nickname' => $nickname ?: null, ':id' => $userId]);

        // 清除类级别的缓存
        self::$userCache = null;

        return ['success' => true];
    }

    /**
     * 更新用户信息（性别、出生日期、星座、属相、人格）
     */
    public static function updateProfile($userId, $data)
    {
        global $pdo;

        $gender = isset($data['gender']) && in_array($data['gender'], ['male', 'female', 'other', '']) ? ($data['gender'] ?: null) : null;
        $birthDate = !empty($data['birth_date']) ? $data['birth_date'] : null;
        $zodiac = !empty($data['zodiac']) ? trim($data['zodiac']) : null;
        $chineseZodiac = !empty($data['chinese_zodiac']) ? trim($data['chinese_zodiac']) : null;
        $personality = !empty($data['personality']) ? trim($data['personality']) : null;

        // 验证出生日期格式
        if ($birthDate && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            return ['success' => false, 'message' => '出生日期格式不正确'];
        }

        // 验证字段长度
        if ($zodiac && mb_strlen($zodiac) > 20) {
            return ['success' => false, 'message' => '星座长度不能超过20个字符'];
        }
        if ($chineseZodiac && mb_strlen($chineseZodiac) > 20) {
            return ['success' => false, 'message' => '属相长度不能超过20个字符'];
        }
        if ($personality && mb_strlen($personality) > 100) {
            return ['success' => false, 'message' => '人格长度不能超过100个字符'];
        }

        // 更新用户信息
        $stmt = $pdo->prepare("
            UPDATE users 
            SET gender = :gender, 
                birth_date = :birth_date, 
                zodiac = :zodiac, 
                chinese_zodiac = :chinese_zodiac, 
                personality = :personality 
            WHERE id = :id
        ");
        $stmt->execute([
            ':gender' => $gender,
            ':birth_date' => $birthDate,
            ':zodiac' => $zodiac,
            ':chinese_zodiac' => $chineseZodiac,
            ':personality' => $personality,
            ':id' => $userId
        ]);

        // 清除类级别的缓存，强制下次重新从数据库获取
        self::$userCache = null;

        return ['success' => true];
    }
}
