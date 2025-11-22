-- ============================================
-- phpMyAdmin 专用版本：字段类型优化（问题 12）
-- 支持小数分数，为未来扩展做准备
-- 执行前请先备份数据库！
-- ============================================
-- 
-- 问题描述：
-- - test_runs.total_score 使用 int(11)，但 ScoreEngine 返回 float
-- - test_run_scores.score_value 也是 int(11)，但维度分数未来可能是小数
-- - results.min_score/max_score 也是 int(11)，需要与 total_score 类型一致
--
-- 优化原因：
-- - 当前权重都是整数（+2, +1, -1, -2），暂时不会出错
-- - 但未来可能使用：
--   * 题目权重系数（如 0.5×）
--   * Z-Score 标准化
--   * 维度百分比保留两位小数
--   * 多重矩阵映射（如 Big Five 机制）
-- - 如果使用 int，小数会被截断，导致隐性 bug
--
-- 解决方案：
-- 将所有分数相关字段改为 DECIMAL(10,2)，支持两位小数
-- ============================================

-- ============================================
-- 方法 1：简单直接（推荐在 phpMyAdmin 中使用）
-- 如果字段已经是 DECIMAL(10,2) 会报错，但可以忽略
-- ============================================

-- 1. 修改 test_runs.total_score
ALTER TABLE `test_runs` 
  MODIFY COLUMN `total_score` DECIMAL(10,2) DEFAULT NULL;

-- 2. 修改 test_run_scores.score_value
ALTER TABLE `test_run_scores` 
  MODIFY COLUMN `score_value` DECIMAL(10,2) NOT NULL DEFAULT 0;

-- 3. 修改 results.min_score
ALTER TABLE `results` 
  MODIFY COLUMN `min_score` DECIMAL(10,2) DEFAULT NULL;

-- 4. 修改 results.max_score
ALTER TABLE `results` 
  MODIFY COLUMN `max_score` DECIMAL(10,2) DEFAULT NULL;

-- ============================================
-- 验证字段类型是否修改成功
-- ============================================
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE table_schema = DATABASE()
  AND (
    (TABLE_NAME = 'test_runs' AND COLUMN_NAME = 'total_score')
    OR (TABLE_NAME = 'test_run_scores' AND COLUMN_NAME = 'score_value')
    OR (TABLE_NAME = 'results' AND COLUMN_NAME IN ('min_score', 'max_score'))
  )
ORDER BY TABLE_NAME, COLUMN_NAME;

-- ============================================
-- 注意事项：
-- 1. 执行前请备份数据库
-- 2. 如果表中已有大量数据，ALTER 操作可能需要一些时间
-- 3. 修改后，现有整数数据会自动转换为 DECIMAL（如 100 -> 100.00）
-- 4. 代码中已经使用 (float) 转换，所以不需要修改 PHP 代码
-- ============================================

