-- ============================================
-- phpMyAdmin 专用版本：为 test_runs 表添加索引（问题 11）
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

-- ============================================
-- 方法 1：简单直接（推荐在 phpMyAdmin 中使用）
-- 如果索引已存在会报错，但可以忽略
-- ============================================

-- 添加 share_token 索引
ALTER TABLE `test_runs` 
  ADD INDEX `idx_share_token` (`share_token`);

-- 添加 created_at 索引
ALTER TABLE `test_runs` 
  ADD INDEX `idx_created_at` (`created_at`);

-- ============================================
-- 验证索引是否创建成功
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

