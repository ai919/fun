-- ============================================
-- phpMyAdmin 安全版本：为 test_runs 表添加索引（问题 11）
-- 带安全检查，可重复执行
-- 执行前请先备份数据库！
-- ============================================
-- 
-- 使用方法：
-- 1. 在 phpMyAdmin 中，选择你的数据库
-- 2. 点击 "SQL" 标签页
-- 3. 复制下面的 SQL 语句，分步执行
-- ============================================

-- ============================================
-- 步骤 1：创建存储过程（一次性执行）
-- 注意：在 phpMyAdmin 中，需要先执行这一步
-- ============================================

-- 先删除可能存在的存储过程
DROP PROCEDURE IF EXISTS add_index_if_not_exists;

-- 创建存储过程
-- 注意：在 phpMyAdmin 中，DELIMITER 可能不工作
-- 如果遇到问题，请使用方法 1（简单版本）

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
END

-- ============================================
-- 步骤 2：执行添加索引操作（执行完步骤1后执行）
-- ============================================

CALL add_index_if_not_exists('test_runs', 'idx_share_token', '`share_token`');
CALL add_index_if_not_exists('test_runs', 'idx_created_at', '`created_at`');

-- ============================================
-- 步骤 3：清理临时存储过程（可选）
-- ============================================

DROP PROCEDURE IF EXISTS add_index_if_not_exists;

-- ============================================
-- 步骤 4：验证索引是否创建成功
-- ============================================

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

