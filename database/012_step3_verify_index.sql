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

