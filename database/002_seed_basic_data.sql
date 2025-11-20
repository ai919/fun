-- --------------------------------------------------------
-- Seed data for DoFun quiz平台
-- --------------------------------------------------------

INSERT INTO tests (slug, title, subtitle, description, title_color, tags, status, sort_order, scoring_mode, scoring_config)
VALUES
('love', '亲密关系测试', '看看你在亲密关系中的状态', '一组关于亲密、安全感的问题。', '#ef4444', '恋爱,关系', 'published', 10, 'simple', NULL),
('work', '职场人格速测', '了解你的工作偏好', '通过几个小问题，了解在工作中的状态。', '#3b82f6', '职场,性格', 'published', 8, 'simple', NULL),
('core-personality-structure', '核心人格结构', '维度型人格结构', '通过 I/E、R/F 两个轴心组合定位你的核心人格。', '#10b981', '人格,维度', 'published', 6, 'dimensions', JSON_OBJECT('dimensions', JSON_ARRAY('I','E','R','F'), 'tie_breaker', JSON_OBJECT('IE','I','RF','R')));

INSERT INTO questions (test_id, question_text, sort_order)
VALUES
((SELECT id FROM tests WHERE slug='love'), '当对方晚回消息时，你会怎么想？', 1),
((SELECT id FROM tests WHERE slug='love'), '习惯怎样表达亲密需求？', 2),
((SELECT id FROM tests WHERE slug='work'), '面对突发任务时，你的第一反应是？', 1),
((SELECT id FROM tests WHERE slug='work'), '你喜欢的工作节奏是？', 2),
((SELECT id FROM tests WHERE slug='core-personality-structure'), '陌生环境里你更偏向于？', 1),
((SELECT id FROM tests WHERE slug='core-personality-structure'), '当需要迅速决策时，你会？', 2);

INSERT INTO question_options (question_id, option_key, option_text, map_result_code, score_value)
VALUES
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND sort_order=1), 'A', '我会有点不安，想确认对方是否还在乎', 'A', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND sort_order=1), 'B', '提醒自己不要胡思乱想', 'A', 1),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND sort_order=1), 'C', '这正好让我有点独处时间', 'B', 2),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND sort_order=2), 'A', '我需要时时刻刻的关心', 'A', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND sort_order=2), 'B', '我希望彼此保留一些自由', 'B', 3),

((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='work') AND sort_order=1), 'A', '迅速分解任务并列出计划', 'A', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='work') AND sort_order=1), 'B', '先观察情况，再进入状态', 'B', 2),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='work') AND sort_order=2), 'A', '喜欢高压挑战，更容易兴奋', 'A', 2),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='work') AND sort_order=2), 'B', '想要稳定流程，有条不紊', 'B', 3),

((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='core-personality-structure') AND sort_order=1), 'A', '主动探索，与人连接', 'E:2,R:1', 1),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='core-personality-structure') AND sort_order=1), 'B', '安静观察，评估环境', 'I:2,F:1', 1),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='core-personality-structure') AND sort_order=2), 'A', '凭直觉抓住机会', 'E:1,R:2', 1),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='core-personality-structure') AND sort_order=2), 'B', '谨慎分析后制定策略', 'I:1,F:2', 1);

INSERT INTO results (test_id, code, title, description, min_score, max_score)
VALUES
((SELECT id FROM tests WHERE slug='love'), 'A', '稳定依恋', '你既能表达需求，也能给彼此空间。', 0, 0),
((SELECT id FROM tests WHERE slug='love'), 'B', '偏向回避', '你更习惯通过留白来保持安全感。', 0, 0),
((SELECT id FROM tests WHERE slug='work'), 'A', '工作冲刺型', '偏爱高压和挑战，享受冲刺的快感。', 0, 0),
((SELECT id FROM tests WHERE slug='work'), 'B', '稳健节奏型', '愿意长期投入，保持节奏与秩序。', 0, 0),
((SELECT id FROM tests WHERE slug='core-personality-structure'), 'IR', '内省现实型 IR', '沉稳务实，习惯从内在视角判断世界。', 0, 0),
((SELECT id FROM tests WHERE slug='core-personality-structure'), 'IF', '内省幻想型 IF', '思维细腻，依靠直觉与情感组合决策。', 0, 0),
((SELECT id FROM tests WHERE slug='core-personality-structure'), 'ER', '外向现实型 ER', '行动派，喜欢在互动中迅速推进计划。', 0, 0),
((SELECT id FROM tests WHERE slug='core-personality-structure'), 'EF', '外向幻想型 EF', '富有想象力，擅长激发团队的创意动能。', 0, 0);
