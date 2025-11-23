-- 添加 SEO 相关设置到 settings 表
-- 这些设置用于全局 SEO 配置

INSERT INTO `settings` (`key_name`, `value`, `description`) VALUES
('seo_site_name', 'DoFun心理实验空间', '网站名称（用于 SEO title 和 OG site_name）'),
('seo_default_title', 'DoFun心理实验空间｜心理 性格 性情：更专业的在线测验实验室', '默认页面标题'),
('seo_default_description', 'DoFun心理实验空间，是一个轻量、有趣的在线测验实验室，提供人格、情感、社交、生活方式等多个方向的心理小测试，帮你以更轻松的方式认识自己。', '默认页面描述（meta description）'),
('seo_default_image', '', '默认 OG 图片 URL（留空则使用 /assets/img/dofun-poster-bg.jpg）'),
('seo_default_keywords', '心理测试,性格测试,在线测验,心理实验,人格测试', '默认关键词（meta keywords）'),
('seo_robots_default', 'index,follow', '默认 robots 设置'),
('seo_og_type_default', 'website', '默认 OG type'),
('seo_twitter_card', 'summary_large_image', 'Twitter Card 类型')
ON DUPLICATE KEY UPDATE `key_name` = `key_name`;

