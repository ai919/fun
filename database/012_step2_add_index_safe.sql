-- ============================================
-- 步骤 2（安全版本）：使用存储过程安全添加唯一索引
-- 如果索引已存在，会显示提示信息，不会报错
-- ============================================

-- 先删除可能存在的存储过程
DROP PROCEDURE IF EXISTS add_unique_index_if_not_exists;

-- 创建存储过程
CREATE PROCEDURE add_unique_index_if_not_exists(
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
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` ADD UNIQUE KEY `', p_index_name, '` (', p_index_columns, ')');
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✓ 唯一索引 ', p_index_name, ' 已添加到表 ', p_table_name) AS result;
    ELSE
        SELECT CONCAT('○ 唯一索引 ', p_index_name, ' 已存在于表 ', p_table_name, '，跳过') AS result;
    END IF;
END;

-- 执行添加唯一索引操作
CALL add_unique_index_if_not_exists('test_runs', 'uk_share_token', '`share_token`');

-- 清理临时存储过程
DROP PROCEDURE IF EXISTS add_unique_index_if_not_exists;

