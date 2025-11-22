-- ============================================
-- phpMyAdmin 专用版本：添加缺失的外键约束（问题 13）
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
-- 方法 1：简单直接（推荐在 phpMyAdmin 中使用）
-- 如果外键已存在会报错，但可以忽略
-- ============================================

-- 1. test_runs.user_id → users.id
-- 使用 ON DELETE SET NULL，因为 user_id 可以为空
-- 删除用户时，保留测试记录但清空用户关联（匿名化）
ALTER TABLE `test_runs`
  ADD CONSTRAINT `fk_runs_user` 
  FOREIGN KEY (`user_id`) REFERENCES `users` (`id`) 
  ON DELETE SET NULL;

-- 2. question_answers.test_run_id → test_runs.id
-- 使用 ON DELETE CASCADE，删除测试记录时自动删除答案
ALTER TABLE `question_answers`
  ADD CONSTRAINT `fk_answers_run` 
  FOREIGN KEY (`test_run_id`) REFERENCES `test_runs` (`id`) 
  ON DELETE CASCADE;

-- 3. question_answers.question_id → questions.id
-- 使用 ON DELETE CASCADE，删除题目时自动删除答案
ALTER TABLE `question_answers`
  ADD CONSTRAINT `fk_answers_question` 
  FOREIGN KEY (`question_id`) REFERENCES `questions` (`id`) 
  ON DELETE CASCADE;

-- 4. question_answers.test_id → tests.id
-- 使用 ON DELETE CASCADE，删除测试时自动删除答案
-- 注意：这个外键可能不是必需的（因为可以通过 test_run_id 关联），
-- 但可以确保数据一致性
ALTER TABLE `question_answers`
  ADD CONSTRAINT `fk_answers_test` 
  FOREIGN KEY (`test_id`) REFERENCES `tests` (`id`) 
  ON DELETE CASCADE;

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

