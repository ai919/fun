-- ============================================
-- 添加缺失的外键约束（问题 13）
-- 执行前请先备份数据库！
-- ============================================
-- 
-- 问题描述：
-- - test_runs.user_id 应该引用 users.id，但没有外键约束
-- - question_answers 表缺少外键约束，可能导致数据不一致
--
-- 外键约束的作用：
-- 1. 数据完整性：防止插入无效的引用
-- 2. 级联操作：自动处理关联数据的删除/更新
-- 3. 查询优化：帮助数据库优化器制定更好的执行计划
-- 4. 文档化：明确表之间的关系
--
-- 注意事项：
-- - 添加外键前需要确保现有数据完整性（没有孤立记录）
-- - 如果存在孤立数据，需要先清理或修复
-- ============================================

-- ============================================
-- 使用存储过程检查外键是否存在，避免重复添加
-- ============================================
DELIMITER $$

DROP PROCEDURE IF EXISTS add_foreign_key_if_not_exists$$
CREATE PROCEDURE add_foreign_key_if_not_exists(
    IN p_table_name VARCHAR(64),
    IN p_constraint_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_referenced_table VARCHAR(64),
    IN p_referenced_column VARCHAR(64),
    IN p_on_delete_action VARCHAR(20)
)
BEGIN
    DECLARE v_fk_count INT DEFAULT 0;
    
    -- 检查外键是否存在
    SELECT COUNT(*) INTO v_fk_count
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE table_schema = DATABASE()
      AND table_name = p_table_name
      AND constraint_name = p_constraint_name
      AND referenced_table_name IS NOT NULL;
    
    IF v_fk_count = 0 THEN
        SET @sql = CONCAT(
            'ALTER TABLE `', p_table_name, '` ',
            'ADD CONSTRAINT `', p_constraint_name, '` ',
            'FOREIGN KEY (`', p_column_name, '`) ',
            'REFERENCES `', p_referenced_table, '` (`', p_referenced_column, '`) ',
            'ON DELETE ', p_on_delete_action
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✓ 外键 ', p_constraint_name, ' 已添加到表 ', p_table_name) AS message;
    ELSE
        SELECT CONCAT('○ 外键 ', p_constraint_name, ' 已存在于表 ', p_table_name, '，跳过') AS message;
    END IF;
END$$

DELIMITER ;

-- ============================================
-- 执行添加外键操作
-- ============================================

-- 1. test_runs.user_id → users.id
-- 使用 ON DELETE SET NULL，因为 user_id 可以为空
-- 删除用户时，保留测试记录但清空用户关联（匿名化）
CALL add_foreign_key_if_not_exists(
    'test_runs',
    'fk_runs_user',
    'user_id',
    'users',
    'id',
    'SET NULL'
);

-- 2. question_answers.test_run_id → test_runs.id
-- 使用 ON DELETE CASCADE，删除测试记录时自动删除答案
CALL add_foreign_key_if_not_exists(
    'question_answers',
    'fk_answers_run',
    'test_run_id',
    'test_runs',
    'id',
    'CASCADE'
);

-- 3. question_answers.question_id → questions.id
-- 使用 ON DELETE CASCADE，删除题目时自动删除答案
CALL add_foreign_key_if_not_exists(
    'question_answers',
    'fk_answers_question',
    'question_id',
    'questions',
    'id',
    'CASCADE'
);

-- 4. question_answers.test_id → tests.id
-- 使用 ON DELETE CASCADE，删除测试时自动删除答案
-- 注意：这个外键可能不是必需的（因为可以通过 test_run_id 关联），
-- 但可以确保数据一致性
CALL add_foreign_key_if_not_exists(
    'question_answers',
    'fk_answers_test',
    'test_id',
    'tests',
    'id',
    'CASCADE'
);

-- ============================================
-- 清理临时存储过程
-- ============================================
DROP PROCEDURE IF EXISTS add_foreign_key_if_not_exists;

-- ============================================
-- 验证外键约束是否添加成功
-- ============================================
SELECT 
    TABLE_NAME,
    CONSTRAINT_NAME,
    COLUMN_NAME,
    REFERENCED_TABLE_NAME,
    REFERENCED_COLUMN_NAME,
    DELETE_RULE
FROM information_schema.KEY_COLUMN_USAGE
WHERE table_schema = DATABASE()
  AND referenced_table_name IS NOT NULL
  AND (
    (TABLE_NAME = 'test_runs' AND CONSTRAINT_NAME = 'fk_runs_user')
    OR (TABLE_NAME = 'question_answers' AND CONSTRAINT_NAME IN ('fk_answers_run', 'fk_answers_question', 'fk_answers_test'))
  )
ORDER BY TABLE_NAME, CONSTRAINT_NAME;

-- ============================================
-- 注意事项：
-- 1. 执行前请备份数据库
-- 2. 如果表中已有孤立数据（引用了不存在的记录），添加外键会失败
-- 3. 如果添加失败，需要先清理孤立数据：
--    - 查找孤立记录：SELECT * FROM test_runs WHERE user_id IS NOT NULL AND user_id NOT IN (SELECT id FROM users);
--    - 清理或修复后再执行脚本
-- 4. 外键约束会影响删除操作性能，但能保证数据完整性
-- ============================================

