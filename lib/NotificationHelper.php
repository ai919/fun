<?php
/**
 * 通知管理类
 */
require_once __DIR__ . '/db_connect.php';

class NotificationHelper
{
    /**
     * 发送通知给用户
     * @param int $userId 用户ID
     * @param string $title 通知标题
     * @param string $content 通知内容
     * @param string $type 通知类型：info/warning/success/error
     * @return bool
     */
    public static function send(int $userId, string $title, string $content = '', string $type = 'info'): bool
    {
        global $pdo;
        
        $allowedTypes = ['info', 'warning', 'success', 'error'];
        if (!in_array($type, $allowedTypes)) {
            $type = 'info';
        }
        
        $stmt = $pdo->prepare("
            INSERT INTO notifications (user_id, title, content, type)
            VALUES (:user_id, :title, :content, :type)
        ");
        
        return $stmt->execute([
            ':user_id' => $userId,
            ':title' => $title,
            ':content' => $content,
            ':type' => $type
        ]);
    }
    
    /**
     * 发送通知给所有用户
     * @param string $title 通知标题
     * @param string $content 通知内容
     * @param string $type 通知类型
     * @return int 发送的通知数量
     */
    public static function sendToAll(string $title, string $content = '', string $type = 'info'): int
    {
        global $pdo;
        
        // 获取所有用户ID
        $stmt = $pdo->query("SELECT id FROM users");
        $users = $stmt->fetchAll(PDO::FETCH_COLUMN);
        
        $count = 0;
        foreach ($users as $userId) {
            if (self::send((int)$userId, $title, $content, $type)) {
                $count++;
            }
        }
        
        return $count;
    }
    
    /**
     * 获取用户通知列表
     * @param int $userId 用户ID
     * @param int $limit 限制数量
     * @param int $offset 偏移量
     * @param bool $unreadOnly 是否只获取未读通知
     * @return array
     */
    public static function getUserNotifications(int $userId, int $limit = 20, int $offset = 0, bool $unreadOnly = false): array
    {
        global $pdo;
        
        $sql = "SELECT * FROM notifications WHERE user_id = :user_id";
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT :limit OFFSET :offset";
        
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':user_id', $userId, PDO::PARAM_INT);
        $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
        $stmt->execute();
        
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取用户未读通知数量
     * @param int $userId 用户ID
     * @return int
     */
    public static function getUnreadCount(int $userId): int
    {
        global $pdo;
        
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = :user_id AND is_read = 0");
        $stmt->execute([':user_id' => $userId]);
        
        return (int)$stmt->fetchColumn();
    }
    
    /**
     * 标记通知为已读
     * @param int $notificationId 通知ID
     * @param int $userId 用户ID（用于验证）
     * @return bool
     */
    public static function markAsRead(int $notificationId, int $userId): bool
    {
        global $pdo;
        
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE id = :id AND user_id = :user_id
        ");
        
        return $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId
        ]);
    }
    
    /**
     * 标记所有通知为已读
     * @param int $userId 用户ID
     * @return int 更新的通知数量
     */
    public static function markAllAsRead(int $userId): int
    {
        global $pdo;
        
        $stmt = $pdo->prepare("
            UPDATE notifications 
            SET is_read = 1, read_at = NOW() 
            WHERE user_id = :user_id AND is_read = 0
        ");
        $stmt->execute([':user_id' => $userId]);
        
        return $stmt->rowCount();
    }
    
    /**
     * 删除通知
     * @param int $notificationId 通知ID
     * @param int $userId 用户ID（用于验证）
     * @return bool
     */
    public static function delete(int $notificationId, int $userId): bool
    {
        global $pdo;
        
        $stmt = $pdo->prepare("DELETE FROM notifications WHERE id = :id AND user_id = :user_id");
        
        return $stmt->execute([
            ':id' => $notificationId,
            ':user_id' => $userId
        ]);
    }
}

