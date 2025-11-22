-- ============================================
-- 诊断脚本：检查数据库和表是否存在（固定数据库名版本）
-- 在 phpMyAdmin 中执行
-- ============================================
-- 说明：此版本直接使用 'fun_quiz' 作为数据库名
-- 如果您的数据库名不同，请替换所有 'fun_quiz' 为实际数据库名
-- ============================================

-- 步骤 1：检查数据库是否存在
SELECT 
    SCHEMA_NAME AS '数据库名',
    DEFAULT_CHARACTER_SET_NAME AS '默认字符集',
    DEFAULT_COLLATION_NAME AS '默认排序规则'
FROM information_schema.SCHEMATA
WHERE SCHEMA_NAME = 'fun_quiz';

-- 步骤 2：检查 test_runs 表是否存在
SELECT 
    TABLE_NAME AS '表名',
    TABLE_ROWS AS '行数',
    TABLE_COLLATION AS '字符集'
FROM information_schema.TABLES
WHERE TABLE_SCHEMA = 'fun_quiz'
  AND TABLE_NAME = 'test_runs';

-- 步骤 3：如果表存在，检查 share_token 列是否存在
SELECT 
    COLUMN_NAME AS '列名',
    DATA_TYPE AS '数据类型',
    CHARACTER_MAXIMUM_LENGTH AS '最大长度',
    IS_NULLABLE AS '允许NULL'
FROM information_schema.COLUMNS
WHERE TABLE_SCHEMA = 'fun_quiz'
  AND TABLE_NAME = 'test_runs'
  AND COLUMN_NAME = 'share_token';

-- 步骤 4：如果表和列都存在，检查索引
SELECT 
    INDEX_NAME AS '索引名称',
    COLUMN_NAME AS '列名',
    NON_UNIQUE AS '是否非唯一',
    CASE 
        WHEN NON_UNIQUE = 0 THEN '唯一索引 ✓'
        ELSE '普通索引'
    END AS '索引类型'
FROM information_schema.statistics
WHERE TABLE_SCHEMA = 'fun_quiz'
  AND TABLE_NAME = 'test_runs'
  AND COLUMN_NAME = 'share_token'
ORDER BY index_name;

