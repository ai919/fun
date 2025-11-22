-- ============================================
-- 检查 share_token 唯一索引状态
-- ============================================

-- 检查索引是否存在
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE,
    SEQ_IN_INDEX,
    CASE 
        WHEN NON_UNIQUE = 0 THEN '唯一索引 ✓'
        ELSE '普通索引'
    END AS index_type
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND index_name = 'uk_share_token'
ORDER BY seq_in_index;

-- 如果上面的查询返回了结果，说明索引已存在
-- 如果返回空，说明索引不存在

