-- ============================================
-- 检查并修复 share_token 唯一索引
-- 基于 fun_quiz.sql 数据库检查
-- ============================================

-- ============================================
-- 步骤 1：检查当前索引状态
-- ============================================
SELECT 
    '当前 test_runs 表的所有索引：' AS info;

SELECT 
    INDEX_NAME AS '索引名称',
    COLUMN_NAME AS '列名',
    NON_UNIQUE AS '是否非唯一',
    CASE 
        WHEN NON_UNIQUE = 0 THEN '唯一索引 ✓'
        ELSE '普通索引'
    END AS '索引类型'
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND column_name = 'share_token'
ORDER BY index_name;

-- ============================================
-- 步骤 2：检查是否有重复的 share_token（非 NULL）
-- ============================================
SELECT 
    '检查重复的 share_token：' AS info;

SELECT 
    share_token,
    COUNT(*) AS duplicate_count,
    GROUP_CONCAT(id ORDER BY id) AS record_ids
FROM `test_runs`
WHERE share_token IS NOT NULL
GROUP BY share_token
HAVING COUNT(*) > 1;

-- 如果上面查询返回了结果，说明存在重复的 share_token
-- 需要先清理重复数据，然后再添加唯一索引

-- ============================================
-- 步骤 3：统计 share_token 数据情况
-- ============================================
SELECT 
    'share_token 数据统计：' AS info;

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

-- ============================================
-- 步骤 4：删除冗余的普通索引（如果存在）
-- ============================================
-- 如果步骤 1 显示同时存在 uk_share_token 和 idx_share_token
-- 可以删除 idx_share_token（因为唯一索引已经足够）

-- 先检查是否存在 idx_share_token
SELECT 
    '检查是否需要删除冗余索引：' AS info;

SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '发现冗余索引 idx_share_token，可以删除'
        ELSE '没有冗余索引'
    END AS status
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND index_name = 'idx_share_token';

-- 如果上面显示需要删除，执行下面的语句（取消注释）
-- DROP INDEX `idx_share_token` ON `test_runs`;

-- ============================================
-- 步骤 5：确保唯一索引存在（如果不存在则创建）
-- ============================================
-- 先检查唯一索引是否存在
SELECT 
    '检查唯一索引是否存在：' AS info;

SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ 唯一索引 uk_share_token 已存在'
        ELSE '✗ 唯一索引不存在，需要创建'
    END AS status
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND index_name = 'uk_share_token'
  AND NON_UNIQUE = 0;

-- 如果上面显示不存在，执行下面的语句（取消注释）
-- 注意：如果步骤 2 发现重复数据，需要先清理后再执行
-- ALTER TABLE `test_runs` 
--   ADD UNIQUE KEY `uk_share_token` (`share_token`);

-- ============================================
-- 步骤 6：验证最终状态
-- ============================================
SELECT 
    '最终验证：' AS info;

SELECT 
    INDEX_NAME AS '索引名称',
    COLUMN_NAME AS '列名',
    NON_UNIQUE AS '是否非唯一',
    CASE 
        WHEN NON_UNIQUE = 0 THEN '✓ 唯一索引'
        ELSE '普通索引'
    END AS '索引类型'
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND column_name = 'share_token'
ORDER BY NON_UNIQUE, index_name;

-- ============================================
-- 总结
-- ============================================
-- 根据 fun_quiz.sql 文件分析：
-- 1. ✓ share_token 列已存在（char(32)）
-- 2. ✓ 唯一索引 uk_share_token 已存在（第952行）
-- 3. ⚠️  存在冗余普通索引 idx_share_token（第956行），可以删除
-- 4. ✓ 数据中所有非 NULL 的 share_token 都是唯一的
-- 
-- 建议操作：
-- 1. 如果数据库已正确导入，唯一索引应该已经存在
-- 2. 可以删除冗余的 idx_share_token 索引
-- 3. 如果遇到错误，请检查数据库是否正确导入
-- ============================================

