<?php
/**
 * CSRF Protection Library
 * 
 * 提供 CSRF token 生成和验证功能
 */
require_once __DIR__ . '/Constants.php';

class CSRF
{
    /**
     * 获取或生成 CSRF token
     * 
     * @return string CSRF token
     */
    public static function getToken(): string
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(Constants::CSRF_TOKEN_BYTES));
        }
        
        return $_SESSION['csrf_token'];
    }
    
    /**
     * 生成 CSRF token 的隐藏输入字段 HTML
     * 
     * @return string HTML input 标签
     */
    public static function getTokenField(): string
    {
        $token = self::getToken();
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token, ENT_QUOTES, 'UTF-8') . '">';
    }
    
    /**
     * 验证 CSRF token
     * 
     * @param string|null $token 要验证的 token（如果为 null，则从 $_POST 中获取）
     * @return bool 验证是否通过
     */
    public static function validateToken(?string $token = null): bool
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        if (!isset($_SESSION['csrf_token'])) {
            return false;
        }
        
        if ($token === null) {
            $token = $_POST['csrf_token'] ?? null;
        }
        
        if ($token === null) {
            return false;
        }
        
        return hash_equals($_SESSION['csrf_token'], $token);
    }
    
    /**
     * 验证 CSRF token，如果失败则终止执行
     * 
     * @param string|null $token 要验证的 token（如果为 null，则从 $_POST 中获取）
     * @param string $errorMessage 验证失败时的错误消息
     * @return void
     */
    public static function requireToken(?string $token = null, string $errorMessage = 'CSRF token 验证失败'): void
    {
        if (!self::validateToken($token)) {
            http_response_code(403);
            die($errorMessage);
        }
    }
    
    /**
     * 重新生成 CSRF token（用于 token 使用后刷新）
     * 
     * @return string 新的 CSRF token
     */
    public static function regenerateToken(): string
    {
        if (!isset($_SESSION)) {
            session_start();
        }
        
        $_SESSION['csrf_token'] = bin2hex(random_bytes(Constants::CSRF_TOKEN_BYTES));
        return $_SESSION['csrf_token'];
    }
}

