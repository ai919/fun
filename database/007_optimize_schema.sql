-- ============================================
-- 数据库结构优化脚本
-- 基于代码检查报告生成
-- 执行前请先备份数据库！
-- ============================================

-- 1. 为 test_runs 表添加索引（问题 11：缺少索引）
-- 使用存储过程检查索引是否存在，避免重复添加
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
        SELECT CONCAT('索引 ', p_index_name, ' 已添加到表 ', p_table_name) AS message;
    ELSE
        SELECT CONCAT('索引 ', p_index_name, ' 已存在于表 ', p_table_name, '，跳过') AS message;
    END IF;
END$$

DELIMITER ;

-- 执行添加索引操作
CALL add_index_if_not_exists('test_runs', 'idx_share_token', '`share_token`');
CALL add_index_if_not_exists('test_runs', 'idx_created_at', '`created_at`');

-- 清理临时存储过程
DROP PROCEDURE IF EXISTS add_index_if_not_exists;

-- 2. 为 share_token 添加唯一约束（防止重复）
-- 注意：如果表中已有数据，需要先清理重复的token
ALTER TABLE `test_runs` 
  ADD UNIQUE KEY `uk_share_token` (`share_token`);

-- 3. 检查并修复 share_token 字段长度
-- 代码中生成的是16字节的十六进制字符串（32字符）
-- 但当前schema中是 CHAR(16)，需要改为 CHAR(32)
ALTER TABLE `test_runs` 
  MODIFY COLUMN `share_token` CHAR(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL;

-- 4. 添加外键约束（如果还没有）
-- 注意：确保 users 表已存在且有数据完整性
ALTER TABLE `test_runs`
  ADD CONSTRAINT `fk_runs_user` 
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
  ON DELETE SET NULL;

-- 5. 为 question_options 添加 option_key 索引（如果经常用于查询）
ALTER TABLE `question_options`
  ADD INDEX `idx_option_key` (`option_key`);

-- 6. 为 results 表添加 code 索引（dimensions模式使用）
ALTER TABLE `results`
  ADD INDEX `idx_code` (`code`);

-- 7. 为 results 表添加复合索引（用于分数区间查询）
ALTER TABLE `results`
  ADD INDEX `idx_test_score_range` (`test_id`, `min_score`, `max_score`);

-- 8. 优化 test_run_scores 表的索引
ALTER TABLE `test_run_scores`
  ADD INDEX `idx_dimension_key` (`dimension_key`);

-- 9. 为 users 表的 email 字段添加索引（如果还没有）
-- 注意：email 字段应该已经有 UNIQUE 约束，但如果没有索引，需要添加
-- ALTER TABLE `users` ADD INDEX `idx_email` (`email`); -- 通常UNIQUE已经包含索引

-- 10. 检查 questions 表字段名一致性
-- 如果发现使用了 'content' 字段，需要统一为 'question_text'
-- 执行前请先备份数据！
-- ALTER TABLE `questions` CHANGE COLUMN `content` `question_text` TEXT NOT NULL;

-- ============================================
-- 注意事项：
-- 1. 执行前请备份数据库
-- 2. 如果表中已有数据，某些ALTER操作可能需要时间
-- 3. 如果 share_token 已有重复值，需要先清理
-- 4. 外键约束需要确保数据完整性
-- ============================================

