-- ============================================
-- 步骤 1：检查是否有重复的 share_token
-- 如果返回了结果，说明存在重复，需要先清理
-- ============================================
SELECT 
    share_token,
    COUNT(*) AS duplicate_count
FROM test_runs
WHERE share_token IS NOT NULL
GROUP BY share_token
HAVING COUNT(*) > 1;

