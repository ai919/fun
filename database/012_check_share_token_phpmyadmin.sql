-- ============================================
-- 在 phpMyAdmin 中检查 share_token 唯一索引
-- 直接复制粘贴到 phpMyAdmin SQL 编辑器执行
-- ⚠️ 请先确认已选择正确的数据库（fun_quiz）
-- ============================================

-- 步骤 0：检查表是否存在
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN CONCAT('✓ 表 test_runs 存在 (', TABLE_ROWS, ' 行)')
        ELSE '✗ 表 test_runs 不存在，请检查数据库是否正确导入'
    END AS '表状态'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'test_runs';

-- 步骤 1：检查当前索引状态
SELECT 
    INDEX_NAME AS '索引名称',
    COLUMN_NAME AS '列名',
    NON_UNIQUE AS '是否非唯一',
    CASE 
        WHEN NON_UNIQUE = 0 THEN '唯一索引 ✓'
        ELSE '普通索引'
    END AS '索引类型'
FROM information_schema.statistics
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'test_runs'
  AND COLUMN_NAME = 'share_token'
ORDER BY index_name;

-- 步骤 2：检查是否有重复的 share_token（非 NULL）
-- ⚠️ 如果表不存在，这一步会报错，请先确认步骤 0 显示表存在
SELECT 
    share_token,
    COUNT(*) AS duplicate_count,
    GROUP_CONCAT(id ORDER BY id) AS record_ids
FROM `test_runs`
WHERE share_token IS NOT NULL
GROUP BY share_token
HAVING COUNT(*) > 1;

-- 步骤 3：统计 share_token 数据情况
SELECT 
    COUNT(*) AS total_rows,
    COUNT(share_token) AS non_null_tokens,
    COUNT(*) - COUNT(share_token) AS null_tokens,
    COUNT(DISTINCT share_token) AS unique_tokens,
    CASE 
        WHEN COUNT(share_token) = COUNT(DISTINCT share_token) THEN '✓ 无重复'
        ELSE '✗ 存在重复'
    END AS duplicate_status
FROM `test_runs`
WHERE share_token IS NOT NULL;

-- 步骤 4：检查是否需要删除冗余索引
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '发现冗余索引 idx_share_token，可以删除'
        ELSE '没有冗余索引'
    END AS status
FROM information_schema.statistics
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'test_runs'
  AND INDEX_NAME = 'idx_share_token';

-- 步骤 5：检查唯一索引是否存在
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ 唯一索引 uk_share_token 已存在'
        ELSE '✗ 唯一索引不存在，需要创建'
    END AS status
FROM information_schema.statistics
WHERE TABLE_SCHEMA = DATABASE()
  AND TABLE_NAME = 'test_runs'
  AND INDEX_NAME = 'uk_share_token'
  AND NON_UNIQUE = 0;

