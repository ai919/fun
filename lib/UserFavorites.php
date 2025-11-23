<?php
/**
 * 用户收藏功能
 */
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/user_auth.php';

class UserFavorites {
    /**
     * 添加收藏
     */
    public static function addFavorite(int $testId, ?int $userId = null): array {
        global $pdo;
        
        if ($userId === null) {
            $user = UserAuth::currentUser();
            $userId = $user ? (int)$user['id'] : null;
        }
        
        if (!$userId) {
            return ['success' => false, 'message' => '请先登录'];
        }
        
        try {
            // 检查是否已收藏
            $stmt = $pdo->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND test_id = ? LIMIT 1");
            $stmt->execute([$userId, $testId]);
            if ($stmt->fetch()) {
                return ['success' => false, 'message' => '已收藏'];
            }
            
            // 添加收藏
            $stmt = $pdo->prepare("INSERT INTO user_favorites (user_id, test_id) VALUES (?, ?)");
            $stmt->execute([$userId, $testId]);
            
            return ['success' => true, 'message' => '收藏成功'];
        } catch (Exception $e) {
            error_log('UserFavorites::addFavorite error: ' . $e->getMessage());
            return ['success' => false, 'message' => '收藏失败'];
        }
    }
    
    /**
     * 取消收藏
     */
    public static function removeFavorite(int $testId, ?int $userId = null): array {
        global $pdo;
        
        if ($userId === null) {
            $user = UserAuth::currentUser();
            $userId = $user ? (int)$user['id'] : null;
        }
        
        if (!$userId) {
            return ['success' => false, 'message' => '请先登录'];
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM user_favorites WHERE user_id = ? AND test_id = ?");
            $stmt->execute([$userId, $testId]);
            
            return ['success' => true, 'message' => '已取消收藏'];
        } catch (Exception $e) {
            error_log('UserFavorites::removeFavorite error: ' . $e->getMessage());
            return ['success' => false, 'message' => '取消收藏失败'];
        }
    }
    
    /**
     * 检查是否已收藏
     */
    public static function isFavorited(int $testId, ?int $userId = null): bool {
        global $pdo;
        
        if ($userId === null) {
            $user = UserAuth::currentUser();
            $userId = $user ? (int)$user['id'] : null;
        }
        
        if (!$userId) {
            return false;
        }
        
        $stmt = $pdo->prepare("SELECT id FROM user_favorites WHERE user_id = ? AND test_id = ? LIMIT 1");
        $stmt->execute([$userId, $testId]);
        return (bool)$stmt->fetch();
    }
    
    /**
     * 获取用户的收藏列表
     */
    public static function getUserFavorites(?int $userId = null, int $limit = 50, int $offset = 0): array {
        global $pdo;
        
        if ($userId === null) {
            $user = UserAuth::currentUser();
            $userId = $user ? (int)$user['id'] : null;
        }
        
        if (!$userId) {
            return [];
        }
        
        $stmt = $pdo->prepare(
            "SELECT t.*, uf.created_at as favorited_at
             FROM user_favorites uf
             INNER JOIN tests t ON uf.test_id = t.id
             WHERE uf.user_id = ? AND t.status = 'published'
             ORDER BY uf.created_at DESC
             LIMIT ? OFFSET ?"
        );
        $stmt->execute([$userId, $limit, $offset]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
}

