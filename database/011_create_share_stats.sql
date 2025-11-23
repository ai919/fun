-- 分享统计表
CREATE TABLE IF NOT EXISTS `share_stats` (
  `id` bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
  `test_run_id` bigint(20) UNSIGNED DEFAULT NULL,
  `share_token` char(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `platform` varchar(50) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '分享平台：wechat, weibo, qq, copy_link, etc.',
  `referrer` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL COMMENT '来源页面',
  `ip_address` varchar(45) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `user_agent` varchar(255) COLLATE utf8mb4_unicode_ci DEFAULT NULL,
  `created_at` datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_share_token` (`share_token`),
  KEY `idx_test_run_id` (`test_run_id`),
  KEY `idx_platform` (`platform`),
  KEY `idx_created_at` (`created_at`),
  CONSTRAINT `fk_share_stats_test_run` FOREIGN KEY (`test_run_id`) REFERENCES `test_runs` (`id`) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='分享统计表';

