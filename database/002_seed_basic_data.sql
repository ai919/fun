-- --------------------------------------------------------
-- Seed data for DoFun quiz platform
-- --------------------------------------------------------

INSERT INTO tests (slug, title, subtitle, description, title_color, tags, status, sort_order)
VALUES
('love', '感情依恋风格', '看看你在亲密关系中的状态', '一组关于亲密、安全感与距离感的题目。', '#ef4444', '情感,恋爱', 'published', 10),
('work', '职场人格速测', '了解你的工作偏好', '通过几个小问题，快速了解在工作中的状态。', '#3b82f6', '职场,性格', 'published', 8);

INSERT INTO dimensions (test_id, key_name, title)
VALUES
((SELECT id FROM tests WHERE slug='love'), 'anxiety', '亲密焦虑'),
((SELECT id FROM tests WHERE slug='love'), 'avoidance', '亲密回避'),
((SELECT id FROM tests WHERE slug='work'), 'focus', '专注模式'),
((SELECT id FROM tests WHERE slug='work'), 'pace', '节奏偏好');

INSERT INTO questions (test_id, order_number, content)
VALUES
((SELECT id FROM tests WHERE slug='love'), 1, '当对方晚回消息时，你会怎么想？'),
((SELECT id FROM tests WHERE slug='love'), 2, '你更在意情绪共鸣还是空间自由？'),
((SELECT id FROM tests WHERE slug='work'), 1, '面对突发任务时，你的第一反应是？'),
((SELECT id FROM tests WHERE slug='work'), 2, '你喜欢的工作节奏是？');

INSERT INTO options (question_id, content, dimension_key, score)
VALUES
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND order_number=1),
 '我会有点不安，想确认对方是否还在乎', 'anxiety', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND order_number=1),
 '我会提醒自己不要胡思乱想', 'anxiety', 1),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND order_number=1),
 '这正好让我有点独处的时间', 'avoidance', 2),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND order_number=2),
 '我需要感受到时时刻刻的关心', 'anxiety', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='love') AND order_number=2),
 '我希望彼此都保留一些自由空间', 'avoidance', 3),

((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='work') AND order_number=1),
 '迅速分解任务并列出计划', 'focus', 3),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='work') AND order_number=1),
 '先观察情况，再慢慢进入状态', 'pace', 2),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='work') AND order_number=2),
 '喜欢高压与挑战，更易兴奋', 'focus', 2),
((SELECT id FROM questions WHERE test_id=(SELECT id FROM tests WHERE slug='work') AND order_number=2),
 '希望流程稳定、有条不紊', 'pace', 3);

INSERT INTO results (test_id, code, title, description, min_score, max_score)
VALUES
((SELECT id FROM tests WHERE slug='love'), 'secure', '稳定依恋', '你既能表达需求，也能给彼此空间。', 0, 4),
((SELECT id FROM tests WHERE slug='love'), 'anxious', '偏向焦虑', '你需要更多确认与回应，记得先照顾好自己。', 5, 8),
((SELECT id FROM tests WHERE slug='work'), 'sprinter', '工作冲刺型', '偏爱高压和挑战，享受冲刺的快感。', 0, 4),
((SELECT id FROM tests WHERE slug='work'), 'steady', '稳健节奏型', '你愿意长期投入，保持节奏与秩序。', 5, 8);
