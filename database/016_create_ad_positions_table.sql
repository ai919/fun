-- 创建广告位表
CREATE TABLE IF NOT EXISTS `ad_positions` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `position_key` varchar(100) NOT NULL COMMENT '广告位标识（如：home_top, test_middle等）',
  `position_name` varchar(200) NOT NULL COMMENT '广告位名称',
  `ad_code` text COMMENT '广告代码（HTML/JavaScript）',
  `ad_type` enum('code','image','text') DEFAULT 'code' COMMENT '广告类型：code=代码广告, image=图片广告, text=文字广告',
  `image_url` varchar(500) DEFAULT NULL COMMENT '图片广告URL',
  `link_url` varchar(500) DEFAULT NULL COMMENT '链接地址',
  `alt_text` varchar(200) DEFAULT NULL COMMENT '图片alt文本',
  `is_enabled` tinyint(1) DEFAULT 1 COMMENT '是否启用',
  `display_pages` varchar(500) DEFAULT NULL COMMENT '显示页面（逗号分隔：home,test,result）',
  `priority` int(11) DEFAULT 0 COMMENT '优先级（数字越大越优先）',
  `max_display_count` int(11) DEFAULT 0 COMMENT '最大显示次数（0=不限制）',
  `start_date` datetime DEFAULT NULL COMMENT '开始日期',
  `end_date` datetime DEFAULT NULL COMMENT '结束日期',
  `created_at` datetime DEFAULT CURRENT_TIMESTAMP,
  `updated_at` datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `position_key` (`position_key`),
  KEY `idx_enabled` (`is_enabled`),
  KEY `idx_display_pages` (`display_pages`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='广告位配置表';

-- 插入默认广告位配置
INSERT INTO `ad_positions` (`position_key`, `position_name`, `ad_code`, `ad_type`, `is_enabled`, `display_pages`, `priority`) VALUES
('home_top', '首页顶部横幅', NULL, 'code', 0, 'home', 10),
('home_middle', '首页中间广告', NULL, 'code', 0, 'home', 8),
('home_bottom', '首页底部广告', NULL, 'code', 0, 'home', 5),
('test_top', '测验页顶部', NULL, 'code', 0, 'test', 10),
('test_middle', '测验页中间', NULL, 'code', 0, 'test', 8),
('test_bottom', '测验页底部', NULL, 'code', 0, 'test', 5),
('result_top', '结果页顶部', NULL, 'code', 0, 'result', 10),
('result_middle', '结果页中间', NULL, 'code', 0, 'result', 8),
('result_bottom', '结果页底部', NULL, 'code', 0, 'result', 5);

