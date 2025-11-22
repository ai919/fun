-- ============================================
-- phpMyAdmin 专用版本：为 test_runs.share_token 添加唯一索引（问题 15）
-- 执行前请先备份数据库！
-- ============================================
-- 
-- 问题描述：
-- test_runs.share_token 应该唯一，但没有唯一索引
-- 这可能导致重复的 share_token，影响通过 token 查询结果的准确性
--
-- 修复方案：
-- 为 share_token 添加唯一索引，确保每个 token 都是唯一的
-- 注意：MySQL 的唯一索引允许多个 NULL 值，所以 NULL 值不会影响唯一性约束
-- ============================================

-- ============================================
-- 方法 1：简单直接（推荐在 phpMyAdmin 中使用）
-- 如果索引已存在会报错，但可以忽略
-- ============================================

-- 步骤 1：检查是否有重复的 share_token（非 NULL）
-- 如果返回了结果，说明存在重复，需要先清理
SELECT 
    share_token,
    COUNT(*) AS duplicate_count
FROM test_runs
WHERE share_token IS NOT NULL
GROUP BY share_token
HAVING COUNT(*) > 1;

-- 步骤 2：添加唯一索引
ALTER TABLE `test_runs` 
  ADD UNIQUE KEY `uk_share_token` (`share_token`);

-- ============================================
-- 方法 2：带安全检查（可重复执行）
-- 如果索引已存在，会显示提示信息
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

-- ============================================
-- 验证索引是否创建成功
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

-- ============================================
-- 注意事项：
-- 1. 执行前请备份数据库
-- 2. 如果步骤 1 发现重复的 share_token，需要先清理：
--    - 可以删除重复的记录（保留最新的）
--    - 或者为重复的 share_token 生成新的唯一 token
-- 3. 唯一索引允许多个 NULL 值，所以 NULL 值不会影响唯一性
-- 4. 添加唯一索引后，插入重复的 share_token 会报错
-- 5. 如果使用代码生成 share_token，确保生成逻辑不会产生重复
-- ============================================

