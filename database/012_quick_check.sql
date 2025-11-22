-- ============================================
-- 快速检查：确认数据库和表
-- ============================================

-- 1. 显示当前数据库
SELECT DATABASE() AS '当前数据库';

-- 2. 列出所有表（查找 test_runs）
SELECT 
    TABLE_NAME AS '表名',
    TABLE_TYPE AS '类型',
    ENGINE AS '存储引擎'
FROM information_schema.tables
WHERE table_schema = DATABASE()
ORDER BY table_name;

-- 3. 检查 test_runs 表是否存在
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ test_runs 表存在'
        ELSE '✗ test_runs 表不存在'
    END AS '检查结果'
FROM information_schema.tables
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs';

-- 4. 如果表存在，检查 share_token 列
SELECT 
    CASE 
        WHEN COUNT(*) > 0 THEN '✓ share_token 列存在'
        ELSE '✗ share_token 列不存在'
    END AS '检查结果'
FROM information_schema.columns
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND column_name = 'share_token';

