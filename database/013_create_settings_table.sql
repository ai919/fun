-- 网站设置表（用于存储 Google Analytics 等配置）
CREATE TABLE IF NOT EXISTS `settings` (
  `id` int(10) UNSIGNED NOT NULL AUTO_INCREMENT,
  `key_name` varchar(100) NOT NULL COMMENT '设置键名',
  `value` text DEFAULT NULL COMMENT '设置值',
  `description` varchar(255) DEFAULT NULL COMMENT '设置描述',
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uk_key_name` (`key_name`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='网站设置表';

-- 插入默认设置
INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES
('google_analytics_enabled', '0', '是否启用 Google Analytics'),
('google_analytics_code', '', 'Google Analytics 跟踪代码（GA4 测量 ID 或完整脚本代码）')
ON DUPLICATE KEY UPDATE `key_name` = `key_name`;

