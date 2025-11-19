-- --------------------------------------------------------
-- Seed data for DoFun quiz platform
-- --------------------------------------------------------

INSERT INTO tests (slug, title, subtitle, description, tags, title_emoji, cover_image)
VALUES
('money2', '你的金钱焦虑体质有多严重？', '看看你对金钱的安全感有多缺乏', '围绕安全感、消费方式、风险偏好三个维度来审视你的金钱焦虑感。', '金钱,焦虑,自我探索', '💰', '/assets/images/default.png'),
('animal', '你是哪种动物人格？', '通过 4 道小题，看你更像猫还是狗', '轻松测试你更接近可爱猫派、忠诚狗派或自由鸟派。', '性格,趣味', '🐾', '/assets/images/default.png'),
('work', '你是哪一类职场人格？', '从安全感、野心值、抗压方式三个维度看你', '检视你在职场中的风格：稳定协作型或进取挑战型。', '职场,人格', '🧑‍💼', '/assets/images/default.png');

INSERT INTO dimensions (test_id, key_name, title)
VALUES
((SELECT id FROM tests WHERE slug='money2'), 'security', '安全感指数'),
((SELECT id FROM tests WHERE slug='money2'), 'risk', '风险偏好'),
((SELECT id FROM tests WHERE slug='money2'), 'spending', '消费策略'),
((SELECT id FROM tests WHERE slug='animal'), 'cat', '猫系人格'),
((SELECT id FROM tests WHERE slug='animal'), 'dog', '狗系人格'),
((SELECT id FROM tests WHERE slug='animal'), 'bird', '鸟系人格'),
((SELECT id FROM tests WHERE slug='work'), 'stability', '稳定度'),
((SELECT id FROM tests WHERE slug='work'), 'ambition', '野心值'),
((SELECT id FROM tests WHERE slug='work'), 'stress', '抗压方式');

INSERT INTO questions (test_id, order_number, content)
VALUES
((SELECT id FROM tests WHERE slug='money2'), 1, '看到银行卡余额越来越少时，你的直觉反应是？'),
((SELECT id FROM tests WHERE slug='money2'), 2, '如果身边朋友突然开始投资，你会怎么做？'),
((SELECT id FROM tests WHERE slug='animal'), 1, '周末你最期待哪种休闲方式？'),
((SELECT id FROM tests WHERE slug='animal'), 2, '朋友约你临时出行，你的反应是？'),
((SELECT id FROM tests WHERE slug='work'), 1, '在一个高压重要项目来临时，你第一反应是？'),
((SELECT id FROM tests WHERE slug='work'), 2, '同事抢在你前面汇报成果，你会？');

INSERT INTO options (question_id, content, dimension_key, score)
VALUES
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='money2') AND order_number=1),
'立刻紧张，开始列账单，想知道钱去了哪', 'security', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='money2') AND order_number=1),
'提醒自己还有现金流，慢慢理财', 'security', 1),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='money2') AND order_number=2),
'默默观望，不轻易跟风', 'risk', 1),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='money2') AND order_number=2),
'立刻研究项目，担心错过机会', 'risk', 3),

((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='animal') AND order_number=1),
'宅家煲剧，放空自己', 'cat', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='animal') AND order_number=1),
'计划一场短途旅行', 'bird', 2),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='animal') AND order_number=2),
'一定答应，越临时越刺激', 'dog', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='animal') AND order_number=2),
'犹豫一下，只有特别 close 才去', 'cat', 2),

((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='work') AND order_number=1),
'先把流程规划好，一步步推进', 'stability', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='work') AND order_number=1),
'强势接手，主导全局', 'ambition', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='work') AND order_number=2),
'找领导沟通，让自己被看见', 'ambition', 2),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='work') AND order_number=2),
'告诉自己保持平常心，下次更好', 'stress', 2);

INSERT INTO results (test_id, dimension_key, range_min, range_max, title, description)
VALUES
((SELECT id FROM tests WHERE slug='money2'), 'security', 0, 2, '安稳感十足', '你能冷静面对资产波动，拥有不错的底气。'),
((SELECT id FROM tests WHERE slug='money2'), 'security', 3, 6, '高警觉金钱党', '任何一点现金变化都牵动你敏锐的神经。'),
((SELECT id FROM tests WHERE slug='animal'), 'cat', 0, 4, '猫系自由灵魂', '你的节奏慢、喜欢独处，有独特美感。'),
((SELECT id FROM tests WHERE slug='animal'), 'dog', 0, 4, '狗系陪伴达人', '你带来安全感，可靠又热情。'),
((SELECT id FROM tests WHERE slug='work'), 'ambition', 0, 3, '安全责任型', '你稳扎稳打，擅长团队协作。'),
((SELECT id FROM tests WHERE slug='work'), 'ambition', 4, 6, '野心进取型', '你目标明确，乐于争取舞台。');
