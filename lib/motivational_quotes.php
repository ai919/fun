<?php
/**
 * 心理激励名言辅助类
 * 
 * 用于管理心理激励名言的获取和缓存
 */
class MotivationalQuotes
{
    private static ?PDO $pdo = null;
    private static array $cache = [];
    private static ?string $randomQuote = null;

    /**
     * 初始化数据库连接
     */
    private static function initPdo(): void
    {
        if (self::$pdo === null) {
            require_once __DIR__ . '/db_connect.php';
            global $pdo;
            self::$pdo = $pdo;
        }
    }

    /**
     * 获取一条随机名言
     * 
     * @return string 名言内容，如果没有则返回空字符串
     */
    public static function getRandomQuote(): string
    {
        // 如果已经获取过，直接返回（同一页面请求中保持相同）
        if (self::$randomQuote !== null) {
            return self::$randomQuote;
        }

        self::initPdo();
        
        try {
            // 从启用的名言中随机选择一条
            $stmt = self::$pdo->query("
                SELECT quote_text 
                FROM motivational_quotes 
                WHERE is_active = 1 
                ORDER BY RAND() 
                LIMIT 1
            ");
            $quote = $stmt->fetchColumn();
            
            if ($quote === false) {
                self::$randomQuote = '';
                return '';
            }
            
            self::$randomQuote = (string)$quote;
            return self::$randomQuote;
        } catch (PDOException $e) {
            // 如果表不存在，返回空字符串
            return '';
        }
    }

    /**
     * 获取所有启用的名言
     * 
     * @return array
     */
    public static function getAllActiveQuotes(): array
    {
        self::initPdo();
        
        try {
            $stmt = self::$pdo->query("
                SELECT id, quote_text, sort_order 
                FROM motivational_quotes 
                WHERE is_active = 1 
                ORDER BY sort_order ASC, id ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * 获取所有名言（包括禁用的）
     * 
     * @return array
     */
    public static function getAllQuotes(): array
    {
        self::initPdo();
        
        try {
            $stmt = self::$pdo->query("
                SELECT id, quote_text, is_active, sort_order, created_at, updated_at 
                FROM motivational_quotes 
                ORDER BY sort_order ASC, id ASC
            ");
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            return [];
        }
    }

    /**
     * 添加名言
     * 
     * @param string $quoteText 名言内容
     * @param bool $isActive 是否启用
     * @param int $sortOrder 排序顺序
     * @return int|false 新插入的ID，失败返回false
     */
    public static function addQuote(string $quoteText, bool $isActive = true, int $sortOrder = 0): int|false
    {
        self::initPdo();
        
        try {
            $stmt = self::$pdo->prepare("
                INSERT INTO motivational_quotes (quote_text, is_active, sort_order) 
                VALUES (?, ?, ?)
            ");
            $stmt->execute([$quoteText, $isActive ? 1 : 0, $sortOrder]);
            return (int)self::$pdo->lastInsertId();
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * 更新名言
     * 
     * @param int $id 名言ID
     * @param string $quoteText 名言内容
     * @param bool $isActive 是否启用
     * @param int $sortOrder 排序顺序
     * @return bool
     */
    public static function updateQuote(int $id, string $quoteText, bool $isActive = true, int $sortOrder = 0): bool
    {
        self::initPdo();
        
        try {
            $stmt = self::$pdo->prepare("
                UPDATE motivational_quotes 
                SET quote_text = ?, is_active = ?, sort_order = ? 
                WHERE id = ?
            ");
            return $stmt->execute([$quoteText, $isActive ? 1 : 0, $sortOrder, $id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * 删除名言
     * 
     * @param int $id 名言ID
     * @return bool
     */
    public static function deleteQuote(int $id): bool
    {
        self::initPdo();
        
        try {
            $stmt = self::$pdo->prepare("DELETE FROM motivational_quotes WHERE id = ?");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            return false;
        }
    }

    /**
     * 切换名言启用状态
     * 
     * @param int $id 名言ID
     * @return bool
     */
    public static function toggleActive(int $id): bool
    {
        self::initPdo();
        
        try {
            $stmt = self::$pdo->prepare("
                UPDATE motivational_quotes 
                SET is_active = NOT is_active 
                WHERE id = ?
            ");
            return $stmt->execute([$id]);
        } catch (PDOException $e) {
            return false;
        }
    }
}

