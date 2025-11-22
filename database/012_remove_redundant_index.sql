-- ============================================
-- 删除冗余的 share_token 普通索引
-- 因为已经有唯一索引 uk_share_token，普通索引 idx_share_token 是多余的
-- ============================================

-- 检查是否存在冗余索引
SELECT 
    '检查冗余索引：' AS info;

SELECT 
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE,
    CASE 
        WHEN NON_UNIQUE = 0 THEN '唯一索引'
        ELSE '普通索引'
    END AS index_type
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND column_name = 'share_token'
ORDER BY NON_UNIQUE, index_name;

-- 删除冗余的普通索引（如果存在）
-- 注意：唯一索引 uk_share_token 已经提供了唯一性约束和查询优化
-- 普通索引 idx_share_token 是多余的
DROP INDEX IF EXISTS `idx_share_token` ON `test_runs`;

-- 验证删除结果
SELECT 
    '删除后的索引状态：' AS info;

SELECT 
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE,
    CASE 
        WHEN NON_UNIQUE = 0 THEN '✓ 唯一索引'
        ELSE '普通索引'
    END AS index_type
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND column_name = 'share_token'
ORDER BY NON_UNIQUE, index_name;

-- 应该只看到 uk_share_token（唯一索引）

