-- ============================================
-- 安全诊断和修复脚本：为 share_token 添加唯一索引
-- 执行前请先备份数据库！
-- 请确保已选择正确的数据库！
-- ============================================

-- ============================================
-- 步骤 0：检查当前数据库和表是否存在
-- ============================================
SELECT 
    '当前数据库：' AS info,
    DATABASE() AS current_database;

-- 检查 test_runs 表是否存在
SELECT 
    '检查 test_runs 表是否存在：' AS info;

SELECT 
    TABLE_SCHEMA,
    TABLE_NAME,
    TABLE_TYPE,
    ENGINE
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs';

-- 如果上面查询返回空结果，说明表不存在或数据库选择错误
-- 请检查：
-- 1. 是否选择了正确的数据库
-- 2. 表名是否正确（可能是大小写问题）

-- ============================================
-- 步骤 1：检查当前索引状态
-- ============================================
SELECT 
    '当前 test_runs 表的所有索引：' AS info;

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
ORDER BY index_name, seq_in_index;

-- ============================================
-- 步骤 2：检查 share_token 列是否存在
-- ============================================
SELECT 
    '检查 share_token 列：' AS info;

SELECT 
    COLUMN_NAME,
    DATA_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND column_name = 'share_token';

-- ============================================
-- 步骤 3：检查是否有重复的 share_token（非 NULL）
-- ============================================
SELECT 
    '检查重复的 share_token：' AS info;

SELECT 
    share_token,
    COUNT(*) AS duplicate_count
FROM `test_runs`
WHERE share_token IS NOT NULL
GROUP BY share_token
HAVING COUNT(*) > 1;

-- 如果上面查询返回了结果，说明存在重复的 share_token
-- 需要先清理重复数据，然后再添加唯一索引

-- ============================================
-- 步骤 4：统计 share_token 数据情况
-- ============================================
SELECT 
    'share_token 数据统计：' AS info;

SELECT 
    COUNT(*) AS total_rows,
    COUNT(share_token) AS non_null_tokens,
    COUNT(*) - COUNT(share_token) AS null_tokens,
    COUNT(DISTINCT share_token) AS unique_tokens
FROM `test_runs`;

-- ============================================
-- 步骤 5：添加唯一索引（只有在步骤 3 没有发现重复时执行）
-- ============================================
-- 注意：如果步骤 3 发现重复数据，请先清理后再执行此步骤
-- 清理方法：执行 012_fix_duplicate_tokens.sql

-- 先检查索引是否已存在
SELECT 
    '检查 uk_share_token 索引是否已存在：' AS info;

SELECT 
    INDEX_NAME,
    COLUMN_NAME,
    NON_UNIQUE
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND index_name = 'uk_share_token';

-- 如果上面查询返回空结果，说明索引不存在，可以执行下面的 ALTER TABLE
-- 如果已存在，则不需要再添加

-- 取消下面的注释来添加唯一索引（请确保步骤 3 没有发现重复数据）
-- ALTER TABLE `test_runs` 
--   ADD UNIQUE KEY `uk_share_token` (`share_token`);

-- ============================================
-- 步骤 6：验证索引是否创建成功（执行步骤 5 后运行）
-- ============================================
SELECT 
    '验证索引创建结果：' AS info;

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

-- ============================================
-- 注意事项：
-- 1. 执行前请备份数据库
-- 2. 如果步骤 3 发现重复的 share_token，需要先清理
-- 3. 唯一索引允许多个 NULL 值，所以 NULL 值不会影响唯一性
-- 4. 添加唯一索引后，插入重复的 share_token 会报错
-- 5. 如果步骤 0 显示表不存在，请检查数据库选择是否正确
-- ============================================

