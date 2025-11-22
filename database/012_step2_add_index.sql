-- ============================================
-- 步骤 2：添加唯一索引
-- 只有在步骤 1 没有发现重复数据时才能执行
-- ============================================
-- 
-- 注意：如果遇到错误 #1061 - Duplicate key name 'uk_share_token'
-- 说明索引已经存在，这是正常的！可以忽略这个错误。
-- 请执行 database/012_check_share_token_index.sql 来验证索引状态。
-- ============================================

ALTER TABLE `test_runs` 
  ADD UNIQUE KEY `uk_share_token` (`share_token`);

