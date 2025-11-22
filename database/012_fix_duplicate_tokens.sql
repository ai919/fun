-- ============================================
-- 清理重复的 share_token（如果存在）
-- 执行前请先备份数据库！
-- ============================================
-- 
-- 此脚本用于清理重复的 share_token
-- 策略：保留每个 share_token 的第一条记录（按 id 排序），
--       为其他重复记录生成新的唯一 token
-- ============================================

-- 步骤 1：查看重复的 share_token
SELECT 
    share_token,
    COUNT(*) AS duplicate_count,
    GROUP_CONCAT(id ORDER BY id) AS affected_ids
FROM test_runs
WHERE share_token IS NOT NULL
GROUP BY share_token
HAVING COUNT(*) > 1;

-- 步骤 2：为重复的记录生成新的唯一 token
-- 注意：MySQL 的 RANDOM_BYTES 函数在 MySQL 5.6.17+ 和 MariaDB 10.2.3+ 才可用
-- 如果版本不支持，请使用下面的替代方案

-- 方案 A：使用 RANDOM_BYTES（MySQL 5.6.17+ / MariaDB 10.2.3+）
-- UPDATE test_runs AS t1
-- INNER JOIN (
--     SELECT 
--         share_token,
--         MIN(id) AS keep_id
--     FROM test_runs
--     WHERE share_token IS NOT NULL
--     GROUP BY share_token
--     HAVING COUNT(*) > 1
-- ) AS duplicates ON t1.share_token = duplicates.share_token
-- SET t1.share_token = LOWER(HEX(RANDOM_BYTES(16)))
-- WHERE t1.id != duplicates.keep_id
--   AND t1.share_token IS NOT NULL;

-- 方案 B：将重复的 share_token 设置为 NULL（推荐，更安全）
-- 这样可以避免在 SQL 中生成 token，后续由应用代码重新生成
UPDATE test_runs AS t1
INNER JOIN (
    SELECT 
        share_token,
        MIN(id) AS keep_id
    FROM test_runs
    WHERE share_token IS NOT NULL
    GROUP BY share_token
    HAVING COUNT(*) > 1
) AS duplicates ON t1.share_token = duplicates.share_token
SET t1.share_token = NULL
WHERE t1.id != duplicates.keep_id
  AND t1.share_token IS NOT NULL;

-- 注意：执行方案 B 后，被设置为 NULL 的记录需要由应用代码重新生成 share_token
-- 或者可以手动为这些记录生成新的 token（使用 PHP 脚本或应用界面）

-- 步骤 3：验证清理结果
SELECT 
    '清理后的重复检查：' AS info;

SELECT 
    share_token,
    COUNT(*) AS duplicate_count
FROM test_runs
WHERE share_token IS NOT NULL
GROUP BY share_token
HAVING COUNT(*) > 1;

-- 如果返回空结果，说明没有重复了，可以继续执行添加唯一索引的脚本

