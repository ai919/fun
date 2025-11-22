-- ============================================
-- 迁移脚本：为 test_runs 表添加索引（问题 11）
-- 执行前请先备份数据库！
-- ============================================
-- 
-- 问题描述：
-- - test_runs.share_token 没有索引，但经常用于查询（result.php:11）
-- - test_runs.created_at 没有索引，但用于排序和统计
--
-- 性能影响：
-- - share_token 查询：每次通过 token 查询结果时都需要全表扫描
-- - created_at 查询：统计和排序操作性能较差
-- ============================================

SET NAMES utf8mb4;

-- 方法 1：使用存储过程（推荐，可重复执行）
DELIMITER $$

DROP PROCEDURE IF EXISTS add_index_if_not_exists$$
CREATE PROCEDURE add_index_if_not_exists(
    IN p_table_name VARCHAR(64),
    IN p_index_name VARCHAR(64),
    IN p_index_columns VARCHAR(255)
)
BEGIN
    DECLARE v_index_count INT DEFAULT 0;
    
    SELECT COUNT(*) INTO v_index_count
    FROM information_schema.statistics
    WHERE table_schema = DATABASE()
      AND table_name = p_table_name
      AND index_name = p_index_name;
    
    IF v_index_count = 0 THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD INDEX `', p_index_name, '` (', p_index_columns, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✓ 索引 ', p_index_name, ' 已添加到表 ', p_table_name) AS result;
    ELSE
        SELECT CONCAT('○ 索引 ', p_index_name, ' 已存在于表 ', p_table_name, '，跳过') AS result;
    END IF;
END$$

DELIMITER ;

-- 执行添加索引操作
CALL add_index_if_not_exists('test_runs', 'idx_share_token', '`share_token`');
CALL add_index_if_not_exists('test_runs', 'idx_created_at', '`created_at`');

-- 清理临时存储过程
DROP PROCEDURE IF EXISTS add_index_if_not_exists;

-- ============================================
-- 方法 2：直接执行（如果确定索引不存在，可以取消注释使用）
-- ============================================
-- ALTER TABLE `test_runs` 
--   ADD INDEX `idx_share_token` (`share_token`),
--   ADD INDEX `idx_created_at` (`created_at`);
-- ============================================

-- 验证索引是否创建成功
SELECT 
    TABLE_NAME,
    INDEX_NAME,
    COLUMN_NAME,
    SEQ_IN_INDEX
FROM information_schema.statistics
WHERE table_schema = DATABASE()
  AND table_name = 'test_runs'
  AND index_name IN ('idx_share_token', 'idx_created_at')
ORDER BY index_name, seq_in_index;

