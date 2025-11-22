-- ============================================
-- 在 phpMyAdmin 中修复 share_token 唯一索引
-- ⚠️ 执行前请先运行检查脚本确认状态
-- ============================================

-- 操作 1：删除冗余的普通索引（如果存在）
-- 如果检查脚本显示有 idx_share_token，执行下面这行
DROP INDEX IF EXISTS `idx_share_token` ON `test_runs`;

-- 操作 2：添加唯一索引（如果不存在）
-- ⚠️ 注意：如果存在重复的 share_token，这个操作会失败
-- 执行前请确保步骤 2 的检查没有返回重复数据
ALTER TABLE `test_runs` 
  ADD UNIQUE KEY `uk_share_token` (`share_token`);

-- 如果上面报错说索引已存在，说明唯一索引已经创建好了，可以忽略错误

