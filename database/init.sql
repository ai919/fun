-- ----------------------------
-- 0. admin_users
-- ----------------------------
DROP TABLE IF EXISTS `admin_users`;
CREATE TABLE `admin_users` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `username` VARCHAR(50) NOT NULL UNIQUE,
  `password_hash` VARCHAR(255) NOT NULL,
  `is_active` TINYINT(1) DEFAULT 1,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Default admin account: admin / admin123 (please change after login)
INSERT INTO `admin_users` (`username`, `password_hash`, `is_active`) VALUES
('admin', '$2y$10$EYLV6iPPoScUpQeFs0m6JOiF2/di1fd17vJdaH69cSLK6HqXXrH0G', 1);
-- --------------------------------------------------------
-- Database initialization for fun_quiz system
-- Author: ChatGPT
-- --------------------------------------------------------

SET NAMES utf8mb4;
SET FOREIGN_KEY_CHECKS = 0;

-- ----------------------------
-- 1. tests
-- ----------------------------
DROP TABLE IF EXISTS `tests`;
CREATE TABLE `tests` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `slug` VARCHAR(50) NOT NULL UNIQUE,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  `cover_image` VARCHAR(255),
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Insert base test (LOVE test)
INSERT INTO `tests` (`slug`, `title`, `description`, `cover_image`)
VALUES (
  'love',
  '你在亲密关系中的隐藏模式',
  '一个洞察你在关系中情感反应、依恋模式、亲密冲突的心理测试�?,
  'default.png'
);


-- ----------------------------
-- 2. dimensions
-- ----------------------------
DROP TABLE IF EXISTS `dimensions`;
CREATE TABLE `dimensions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `test_id` INT NOT NULL,
  `key_name` VARCHAR(50) NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT,
  FOREIGN KEY (`test_id`) REFERENCES `tests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Insert LOVE test dimensions
INSERT INTO `dimensions` (`test_id`, `key_name`, `title`, `description`) VALUES
((SELECT id FROM tests WHERE slug='love'), 'anxiety', '情感焦虑�?, '你在关系中对爱是否稳定、有安全感的衡量�?),
((SELECT id FROM tests WHERE slug='love'), 'avoidance', '亲密回避�?, '你在情感中的独立需求与距离感倾向�?);


-- ----------------------------
-- 3. questions
-- ----------------------------
DROP TABLE IF EXISTS `questions`;
CREATE TABLE `questions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `test_id` INT NOT NULL,
  `order_number` INT NOT NULL,
  `content` TEXT NOT NULL,
  FOREIGN KEY (`test_id`) REFERENCES `tests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Insert LOVE test questions
INSERT INTO `questions` (`test_id`, `order_number`, `content`) VALUES
((SELECT id FROM tests WHERE slug='love'), 1, '当爱的人没有及时回复你消息时，你内心最真实的反应？'),
((SELECT id FROM tests WHERE slug='love'), 2, '如果你和喜欢的人产生误会，你通常会怎么做？'),
((SELECT id FROM tests WHERE slug='love'), 3, '你更害怕哪一种情景？'),
((SELECT id FROM tests WHERE slug='love'), 4, '在一段稳定的关系里，你最常出现的状态？'),
((SELECT id FROM tests WHERE slug='love'), 5, '你对「亲密」最真实的态度是什么？');


-- ----------------------------
-- 4. options
-- ----------------------------
DROP TABLE IF EXISTS `options`;
CREATE TABLE `options` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `question_id` INT NOT NULL,
  `content` TEXT NOT NULL,
  `dimension_key` VARCHAR(50),
  `score` INT DEFAULT 0,
  FOREIGN KEY (`question_id`) REFERENCES `questions`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Insert LOVE options
-- Q1
INSERT INTO `options` (`question_id`, `content`, `dimension_key`, `score`) VALUES
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND order_number=1),
 '开始焦躁，疯狂想对方是不是不爱�?, 'anxiety', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND order_number=1),
 '会有点不安，但仍能做自己的事', 'anxiety', 2),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND order_number=1),
 '完全不会在意，觉得对方应该有自己的事�?, 'avoidance', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND order_number=1),
 '理智分析情况，等待回�?, 'avoidance', 1);

-- Q2
INSERT INTO `options` (`question_id`, `content`, `dimension_key`, `score`) VALUES
((SELECT id FROM questions WHERE order_number=2 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '立刻解释并试图确认关�?, 'anxiety', 3),
((SELECT id FROM questions WHERE order_number=2 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '会焦虑但努力假装没事', 'anxiety', 2),
((SELECT id FROM questions WHERE order_number=2 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '变得冷淡，想要拉开距离', 'avoidance', 3),
((SELECT id FROM questions WHERE order_number=2 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '冷静沟通并继续生活', 'avoidance', 1);

-- Q3
INSERT INTO `options` VALUES
(NULL, (SELECT id FROM questions WHERE order_number=3 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '对方突然不爱你了', 'anxiety', 3),
(NULL, (SELECT id FROM questions WHERE order_number=3 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '对方突然靠太近、需要你很多陪伴', 'avoidance', 3),
(NULL, (SELECT id FROM questions WHERE order_number=3 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '互相对对方失�?, 'anxiety', 2),
(NULL, (SELECT id FROM questions WHERE order_number=3 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '对方太依赖你', 'avoidance', 2);

-- Q4
INSERT INTO `options` VALUES
(NULL, (SELECT id FROM questions WHERE order_number=4 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '患得患失，总想确认对方爱你', 'anxiety', 3),
(NULL, (SELECT id FROM questions WHERE order_number=4 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '常常因为小事情绪上下波动', 'anxiety', 2),
(NULL, (SELECT id FROM questions WHERE order_number=4 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '保持距离，避免太深的情感卷入', 'avoidance', 3),
(NULL, (SELECT id FROM questions WHERE order_number=4 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '理性稳定，有时会略显冷�?, 'avoidance', 1);

-- Q5
INSERT INTO `options` VALUES
(NULL, (SELECT id FROM questions WHERE order_number=5 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '亲密很危险，靠近就会失去', 'anxiety', 3),
(NULL, (SELECT id FROM questions WHERE order_number=5 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '我渴望亲密，但害怕被抛弃', 'anxiety', 2),
(NULL, (SELECT id FROM questions WHERE order_number=5 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '亲密让我不舒服，我更喜欢独立', 'avoidance', 3),
(NULL, (SELECT id FROM questions WHERE order_number=5 AND test_id=(SELECT id FROM tests WHERE slug='love')),
 '我享受亲密，但不过度依赖', 'avoidance', 1);


-- ----------------------------
-- 5. results
-- ----------------------------
DROP TABLE IF EXISTS `results`;
CREATE TABLE `results` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `test_id` INT NOT NULL,
  `dimension_key` VARCHAR(50) NOT NULL,
  `range_min` INT NOT NULL,
  `range_max` INT NOT NULL,
  `title` VARCHAR(255) NOT NULL,
  `description` TEXT NOT NULL,
  FOREIGN KEY (`test_id`) REFERENCES `tests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


-- Insert LOVE results
INSERT INTO `results` 
(`test_id`, `dimension_key`, `range_min`, `range_max`, `title`, `description`) VALUES
((SELECT id FROM tests WHERE slug='love'), 'anxiety', 0, 3,
 '情绪稳定依恋',
 '你在亲密关系中相对稳定，不易陷入过度焦虑，你拥有平衡的依赖与信任感�?),

((SELECT id FROM tests WHERE slug='love'), 'anxiety', 4, 7,
 '轻度焦虑依恋',
 '你对关系需要一定的安全感验证，但仍能维持基本的亲密与稳定�?),

((SELECT id FROM tests WHERE slug='love'), 'anxiety', 8, 20,
 '高焦虑依�?,
 '你非常需要确认爱，对亲密细节敏感，容易陷入患得患失的循环�?),

((SELECT id FROM tests WHERE slug='love'), 'avoidance', 0, 3,
 '亲密舒适型',
 '你愿意靠近别人，也能保持自己的独立，是成熟亲密关系的典型特征�?),

((SELECT id FROM tests WHERE slug='love'), 'avoidance', 4, 7,
 '轻度回避�?,
 '你有时会不自觉后退一步，渴望亲密但习惯保持距离�?),

((SELECT id FROM tests WHERE slug='love'), 'avoidance', 8, 20,
 '高回避依�?,
 '你强烈需要空间，害怕束缚，更像一个“情感上的孤独行者”�?);


-- ----------------------------
-- 6. submissions（用于记录用户答题）
-- ----------------------------
DROP TABLE IF EXISTS `submissions`;
CREATE TABLE `submissions` (
  `id` INT AUTO_INCREMENT PRIMARY KEY,
  `test_id` INT NOT NULL,
  `results_json` TEXT NOT NULL,
  `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (`test_id`) REFERENCES `tests`(`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;


SET FOREIGN_KEY_CHECKS = 1;
