-- ============================================
-- 为 test_runs.share_token 添加唯一索引（问题 15）
-- 执行前请先备份数据库！
-- ============================================
-- 
-- 问题描述：
-- test_runs.share_token 应该唯一，但没有唯一索引
-- 这可能导致重复的 share_token，影响通过 token 查询结果的准确性
--
-- 修复方案：
-- 为 share_token 添加唯一索引，确保每个 token 都是唯一的
-- 注意：MySQL 的唯一索引允许多个 NULL 值，所以 NULL 值不会影响唯一性约束
-- ============================================

-- ============================================
-- 步骤 1：检查是否有重复的 share_token（非 NULL）
-- ============================================
SELECT 
    share_token,
    COUNT(*) AS duplicate_count
FROM test_runs
WHERE share_token IS NOT NULL
GROUP BY share_token
HAVING COUNT(*) > 1;

-- 如果上面查询返回了结果，说明存在重复的 share_token
-- 需要先清理重复数据，然后再添加唯一索引
-- 清理方法：为重复的 share_token 生成新的唯一 token

-- ============================================
-- 步骤 2：添加唯一索引
-- ============================================
ALTER TABLE `test_runs` 
  ADD UNIQUE KEY `uk_share_token` (`share_token`);

-- ============================================
-- 步骤 3：验证索引是否创建成功
-- ============================================
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE,
    SEQ_IN_INDEX
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND index_name = 'uk_share_token'
ORDER BY seq_in_index;

-- ============================================
-- 注意事项：
-- 1. 执行前请备份数据库
-- 2. 如果步骤 1 发现重复的 share_token，需要先清理
-- 3. 唯一索引允许多个 NULL 值，所以 NULL 值不会影响唯一性
-- 4. 添加唯一索引后，插入重复的 share_token 会报错
-- ============================================

