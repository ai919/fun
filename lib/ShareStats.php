<?php
/**
 * 分享统计功能
 */
require_once __DIR__ . '/db_connect.php';

class ShareStats {
    /**
     * 记录分享事件
     */
    public static function recordShare(string $shareToken, ?string $platform = null, ?int $testRunId = null): bool {
        global $pdo;
        
        try {
            // 获取test_run_id（如果未提供）
            if ($testRunId === null && $shareToken) {
                $stmt = $pdo->prepare("SELECT id FROM test_runs WHERE share_token = ? LIMIT 1");
                $stmt->execute([$shareToken]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                $testRunId = $result ? (int)$result['id'] : null;
            }
            
            $ipAddress = $_SERVER['REMOTE_ADDR'] ?? null;
            $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? null;
            $referrer = $_SERVER['HTTP_REFERER'] ?? null;
            
            $stmt = $pdo->prepare(
                "INSERT INTO share_stats (test_run_id, share_token, platform, referrer, ip_address, user_agent)
                 VALUES (:test_run_id, :share_token, :platform, :referrer, :ip, :ua)"
            );
            
            return $stmt->execute([
                ':test_run_id' => $testRunId,
                ':share_token' => $shareToken,
                ':platform' => $platform,
                ':referrer' => $referrer,
                ':ip' => $ipAddress,
                ':ua' => $userAgent,
            ]);
        } catch (Exception $e) {
            error_log('ShareStats::recordShare error: ' . $e->getMessage());
            return false;
        }
    }
    
    /**
     * 获取分享统计
     */
    public static function getStats(?int $testId = null, ?string $shareToken = null, ?string $platform = null): array {
        global $pdo;
        
        $conditions = [];
        $params = [];
        
        if ($testId) {
            $conditions[] = "tr.test_id = :test_id";
            $params[':test_id'] = $testId;
        }
        
        if ($shareToken) {
            $conditions[] = "ss.share_token = :share_token";
            $params[':share_token'] = $shareToken;
        }
        
        if ($platform) {
            $conditions[] = "ss.platform = :platform";
            $params[':platform'] = $platform;
        }
        
        $whereClause = !empty($conditions) ? 'WHERE ' . implode(' AND ', $conditions) : '';
        
        $sql = "
            SELECT 
                ss.platform,
                COUNT(*) as share_count,
                COUNT(DISTINCT ss.share_token) as unique_shares,
                COUNT(DISTINCT ss.ip_address) as unique_ips
            FROM share_stats ss
            LEFT JOIN test_runs tr ON ss.test_run_id = tr.id
            {$whereClause}
            GROUP BY ss.platform
            ORDER BY share_count DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
    
    /**
     * 获取测验的总分享数
     */
    public static function getTestShareCount(int $testId): int {
        global $pdo;
        
        $stmt = $pdo->prepare(
            "SELECT COUNT(*) FROM share_stats ss
             INNER JOIN test_runs tr ON ss.test_run_id = tr.id
             WHERE tr.test_id = ?"
        );
        $stmt->execute([$testId]);
        return (int)$stmt->fetchColumn();
    }
}

