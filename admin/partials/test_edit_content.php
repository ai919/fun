<?php
require_once __DIR__ . '/../../lib/csrf.php';
require_once __DIR__ . '/../../lib/Constants.php';
require_once __DIR__ . '/../../lib/CacheHelper.php';
$testId   = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : null;
$section  = isset($section) ? $section : (isset($_GET['section']) ? trim((string)$_GET['section']) : 'basic');
if (!in_array($section, ['basic', 'questions', 'results'], true)) {
    $section = 'basic';
}
$errors   = [];
$statuses = Constants::getTestStatusLabels();

function admin_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $key = "{$dbName}.{$table}.{$column}";
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$dbName, $table, $column]);
    $cache[$key] = (bool)$stmt->fetchColumn();
    return $cache[$key];
}
$hasEmojiCol = admin_column_exists($pdo, 'tests', 'emoji');
$hasTitleColorCol = admin_column_exists($pdo, 'tests', 'title_color');
// 30个常用emoji，一行10个显示
$emojiOptions = [
    '' => '（不选择）',
    '😀', '😍', '🤔', '🥲', '👍', '🔥', '✨', '💤', '🧠', '💘',
    '🌙', '🎲', '📚', '😈', '🌈', '⭐', '🎯', '🎧', '🪐', '💡',
    '🎨', '🎭', '🎪', '🎬', '🎮', '🏆', '🎁', '🎉', '🎊', '💝', '❤️'
];
$scoringModes = Constants::getScoringModeLabels();

$formData = [
    'title'          => '',
    'slug'           => '',
    'subtitle'       => '',
    'description'    => '',
    'emoji'          => '',
    'title_color'    => '#6366F1',
    'tags'           => '',
    'status'         => Constants::TEST_STATUS_DRAFT,
    'sort_order'     => 0,
    'scoring_mode'   => Constants::SCORING_MODE_SIMPLE,
    'scoring_config' => '',
    'display_mode'   => Constants::DISPLAY_MODE_SINGLE_PAGE,
];

$existingTest = null;
if ($testId) {
    $stmt = $pdo->prepare('SELECT * FROM tests WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $testId]);
    $existingTest = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existingTest) {
        $errors[] = '未找到对应的测验。';
    } else {
        foreach ($formData as $key => $defaultValue) {
            if (array_key_exists($key, $existingTest)) {
                $formData[$key] = $existingTest[$key] ?? '';
            }
        }
        if ($formData['title_color'] === null) {
            $formData['title_color'] = '';
        }
    }
}

if (!$testId && $formData['title_color'] === '') {
    $formData['title_color'] = '#6366F1';
}

// 从 session 中读取错误和表单数据（如果有的话，来自 POST 处理失败后的重定向）
if (isset($_SESSION['test_edit_errors'])) {
    $errors = $_SESSION['test_edit_errors'];
    unset($_SESSION['test_edit_errors']);
}
if (isset($_SESSION['test_edit_form_data'])) {
    $formData = array_merge($formData, $_SESSION['test_edit_form_data']);
    unset($_SESSION['test_edit_form_data']);
    // 如果从 session 读取了数据，需要重新检查 existingTest（因为可能是新建）
    if ($testId) {
        $stmt = $pdo->prepare('SELECT * FROM tests WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $testId]);
        $existingTest = $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
?>

<?php if ($testId && !$existingTest): ?>
    <div class="alert alert-danger">未找到需要编辑的测验。</div>
    <a href="/admin/tests.php" class="btn btn-ghost btn-xs">返回列表</a>
    <?php return; ?>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php
$emojiSelectValue = array_key_exists($formData['emoji'], $emojiOptions) ? $formData['emoji'] : '';
$colorClearDefault = $formData['title_color'] === '' ? '1' : '0';

$questions = [];
$results = [];
if ($testId && $existingTest) {
    $stmtQ = $pdo->prepare("
        SELECT id, question_text, sort_order
        FROM questions
        WHERE test_id = :test_id
        ORDER BY sort_order ASC, id ASC
    ");
    $stmtQ->execute([':test_id' => $testId]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    $stmtR = $pdo->prepare("
        SELECT id, code, title, description, image_url, min_score, max_score
        FROM results
        WHERE test_id = :test_id
        ORDER BY id ASC
    ");
    $stmtR->execute([':test_id' => $testId]);
    $results = $stmtR->fetchAll(PDO::FETCH_ASSOC);
}
?>

<div class="admin-subtabs">
    <a href="test_edit.php?id=<?= $testId ?>&section=basic"
       class="admin-subtab__item <?= $section === 'basic' ? 'is-active' : '' ?>">基础信息</a>
    <a href="test_edit.php?id=<?= $testId ?>&section=questions"
       class="admin-subtab__item <?= $section === 'questions' ? 'is-active' : '' ?>">
        题目概览
        <?php if (!empty($questions)): ?>
            <span class="admin-subtab__badge"><?= count($questions) ?></span>
        <?php endif; ?>
    </a>
    <a href="test_edit.php?id=<?= $testId ?>&section=results"
       class="admin-subtab__item <?= $section === 'results' ? 'is-active' : '' ?>">
        结果概览
        <?php if (!empty($results)): ?>
            <span class="admin-subtab__badge"><?= count($results) ?></span>
        <?php endif; ?>
    </a>
</div>

<?php if ($section === 'basic'): ?>
    <div class="admin-card admin-card--form">
        <form method="post">
            <?php require_once __DIR__ . '/../../lib/csrf.php'; echo CSRF::getTokenField(); ?>
            <input type="hidden" name="action" value="save_basic">
            <div class="form-section">
                <div class="form-section__title">基础信息</div>
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label">标题 *</label>
                        <input type="text" name="title" class="form-input" value="<?= htmlspecialchars($formData['title']) ?>" required>
                        <p class="form-help">展示在前台卡片与测验页顶部。</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">Slug *</label>
                        <input type="text" name="slug" class="form-input" value="<?= htmlspecialchars($formData['slug']) ?>" required>
                        <p class="form-help">用于 URL，只能包含小写英文字母、数字、短横线、下划线。</p>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section__title">外观与标签</div>
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label">展示方式</label>
                        <div class="radio-group inline">
                            <label>
                                <input type="radio" name="display_mode" value="<?= Constants::DISPLAY_MODE_SINGLE_PAGE ?>"
                                       <?= ($formData['display_mode'] ?? Constants::DISPLAY_MODE_SINGLE_PAGE) === Constants::DISPLAY_MODE_SINGLE_PAGE ? 'checked' : '' ?>>
                                一页显示全部题目
                            </label>
                            <label style="margin-left:16px;">
                                <input type="radio" name="display_mode" value="<?= Constants::DISPLAY_MODE_STEP_BY_STEP ?>"
                                       <?= ($formData['display_mode'] ?? '') === Constants::DISPLAY_MODE_STEP_BY_STEP ? 'checked' : '' ?>>
                                一题一页 · 逐题作答
                            </label>
                        </div>
                        <p class="form-help">「一题一页」模式下，前台一次只显示一道题，答完点击下一题，最终仍然一次提交。</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">副标题</label>
                        <input type="text" name="subtitle" class="form-input" value="<?= htmlspecialchars($formData['subtitle']) ?>">
                    </div>
                    <div class="form-field">
                        <label class="form-label">标题颜色</label>
                        <input type="hidden" name="title_color_clear" value="<?= $colorClearDefault ?>">
                        <div style="display:flex;align-items:center;gap:8px;">
                            <input type="color"
                                   class="form-input"
                                   style="width:80px;padding:4px;"
                                   name="title_color"
                                   value="<?= htmlspecialchars($formData['title_color'] !== '' ? $formData['title_color'] : '#6366F1') ?>"
                                   oninput="this.form.title_color_clear.value='0';">
                            <button type="button" class="btn btn-ghost btn-xs"
                                    onclick="this.closest('form').title_color_clear.value='1'; this.closest('form').title_color.value='#6366F1';">
                                清空
                            </button>
                        </div>
                        <p class="form-help">清空后前台使用默认颜色。</p>
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label">Emoji</label>
                        <div class="emoji-select-wrapper">
                            <select name="emoji" id="emoji-select" class="form-select emoji-select">
                                <option value="">（不选择）</option>
                                <?php 
                                $emojiList = array_filter($emojiOptions, function($key) { return $key !== ''; }, ARRAY_FILTER_USE_KEY);
                                foreach ($emojiList as $emoji): 
                                ?>
                                    <option value="<?= htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') ?>" <?= $emojiSelectValue === $emoji ? 'selected' : '' ?>><?= htmlspecialchars($emoji) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="emoji-dropdown-grid" id="emoji-dropdown-grid" style="display: none;">
                                <?php 
                                $emojiList = array_filter($emojiOptions, function($key) { return $key !== ''; }, ARRAY_FILTER_USE_KEY);
                                foreach ($emojiList as $emoji): 
                                ?>
                                    <button type="button" class="emoji-dropdown-item" data-emoji="<?= htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($emoji) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                        <p class="form-help">可选填，为标题增加一个小图标。下拉时一行10个显示。</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">自定义 Emoji</label>
                        <input type="text" name="emoji_custom" class="form-input"
                               value="<?= htmlspecialchars($emojiSelectValue === '' ? $formData['emoji'] : '', ENT_QUOTES, 'UTF-8') ?>" maxlength="16" placeholder="也可手动输入">
                        <p class="form-help">如果上方没有想要的，可以在此输入（不超过 16 字符）。</p>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section__title">描述与标签</div>
                <div class="form-field">
                    <label class="form-label">测验介绍（富文本）</label>

                    <div class="rte-toolbar" data-rte-for="description-editor-main">
                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="bold">B</button>
                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="italic"><em>I</em></button>
                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="underline"><u>U</u></button>
                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="strikeThrough"><s>S</s></button>

                        <span class="rte-toolbar__divider"></span>

                        <div class="rte-color-picker-wrapper" style="display: inline-block; position: relative;">
                            <button type="button" class="btn btn-xs btn-ghost rte-color-trigger" data-cmd="foreColor" title="文字颜色">
                                <span style="display: inline-block; width: 16px; height: 16px; background: #ef4444; border-radius: 2px; vertical-align: middle;"></span>
                            </button>
                            <div class="rte-color-picker" style="display: none; position: absolute; top: 100%; left: 0; z-index: 1000; background: #1f2937; border: 1px solid #374151; border-radius: 6px; padding: 8px; margin-top: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                                <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 4px; width: 200px;">
                                    <button type="button" class="rte-color-btn" data-color="#000000" style="width: 20px; height: 20px; background: #000000; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#374151" style="width: 20px; height: 20px; background: #374151; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#6b7280" style="width: 20px; height: 20px; background: #6b7280; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#9ca3af" style="width: 20px; height: 20px; background: #9ca3af; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#d1d5db" style="width: 20px; height: 20px; background: #d1d5db; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#e5e7eb" style="width: 20px; height: 20px; background: #e5e7eb; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#f3f4f6" style="width: 20px; height: 20px; background: #f3f4f6; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#ffffff" style="width: 20px; height: 20px; background: #ffffff; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#ef4444" style="width: 20px; height: 20px; background: #ef4444; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#f97316" style="width: 20px; height: 20px; background: #f97316; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#fbbf24" style="width: 20px; height: 20px; background: #fbbf24; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#22c55e" style="width: 20px; height: 20px; background: #22c55e; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#10b981" style="width: 20px; height: 20px; background: #10b981; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#06b6d4" style="width: 20px; height: 20px; background: #06b6d4; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#3b82f6" style="width: 20px; height: 20px; background: #3b82f6; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#6366f1" style="width: 20px; height: 20px; background: #6366f1; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#8b5cf6" style="width: 20px; height: 20px; background: #8b5cf6; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#a855f7" style="width: 20px; height: 20px; background: #a855f7; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#d946ef" style="width: 20px; height: 20px; background: #d946ef; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#ec4899" style="width: 20px; height: 20px; background: #ec4899; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#f43f5e" style="width: 20px; height: 20px; background: #f43f5e; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#fef9c3" style="width: 20px; height: 20px; background: #fef9c3; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#dbeafe" style="width: 20px; height: 20px; background: #dbeafe; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#e9d5ff" style="width: 20px; height: 20px; background: #e9d5ff; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#fce7f3" style="width: 20px; height: 20px; background: #fce7f3; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#fecdd3" style="width: 20px; height: 20px; background: #fecdd3; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#fed7aa" style="width: 20px; height: 20px; background: #fed7aa; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#fde68a" style="width: 20px; height: 20px; background: #fde68a; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#d1fae5" style="width: 20px; height: 20px; background: #d1fae5; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                    <button type="button" class="rte-color-btn" data-color="#cffafe" style="width: 20px; height: 20px; background: #cffafe; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                </div>
                            </div>
                        </div>

                        <span class="rte-toolbar__divider"></span>

                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="createLink">链接</button>
                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="insertImage">图片</button>

                        <span class="rte-toolbar__divider"></span>

                        <div class="rte-emoji-picker-wrapper emoji-select-wrapper">
                            <select class="rte-emoji-picker emoji-select">
                                <option value="">Emoji</option>
                                <?php 
                                $emojiList = array_filter($emojiOptions, function($key) { return $key !== ''; }, ARRAY_FILTER_USE_KEY);
                                foreach ($emojiList as $emoji): 
                                ?>
                                    <option value="<?= htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($emoji) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <div class="emoji-dropdown-grid" style="display: none;">
                                <?php 
                                $emojiList = array_filter($emojiOptions, function($key) { return $key !== ''; }, ARRAY_FILTER_USE_KEY);
                                foreach ($emojiList as $emoji): 
                                ?>
                                    <button type="button" class="emoji-dropdown-item" data-emoji="<?= htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($emoji) ?></button>
                                <?php endforeach; ?>
                            </div>
                        </div>
                    </div>

                    <div id="description-editor-main"
                         class="rte-editor"
                         contenteditable="true"><?= !empty($formData['description']) ? $formData['description'] : '' ?></div>

                    <textarea name="description"
                              class="rte-hidden-textarea"
                              style="display:none;"><?= htmlspecialchars($formData['description'] ?? '') ?></textarea>

                    <p class="form-help">
                        可输入段落、Emoji、链接和图片（通过 URL），内容保存为 HTML；前台渲染时请注意适度过滤标签。
                    </p>
                </div>

                <div class="form-field">
                    <label class="form-label">标签（逗号分隔）</label>
                    <input type="text" name="tags" class="form-input" value="<?= htmlspecialchars($formData['tags']) ?>" placeholder="例如：性格, MBTI, 自我探索">
                </div>
            </div>

            <div class="form-section">
                <div class="form-section__title">发布与排序</div>
                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label">状态 *</label>
                        <select name="status" class="form-select">
                            <?php foreach ($statuses as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>"<?= $formData['status'] === $value ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-field">
                        <label class="form-label">排序值</label>
                        <input type="number" name="sort_order" class="form-input" value="<?= (int)$formData['sort_order'] ?>" step="1">
                    </div>
                </div>

                <div class="form-grid">
                    <div class="form-field">
                        <label class="form-label">评分模式 *</label>
                        <select name="scoring_mode" class="form-select">
                            <?php foreach ($scoringModes as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>"<?= $formData['scoring_mode'] === $value ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="form-field">
                    <label class="form-label">评分配置（JSON，可选）</label>
                    <textarea name="scoring_config" class="form-textarea" rows="5" placeholder='例如：{"dimensions":["I","E","R","F"]}'><?= htmlspecialchars($formData['scoring_config']) ?></textarea>
                    <p class="form-help">仅在部分评分模式下有效，请输入合法的 JSON。</p>
                </div>
            </div>

            <div class="form-actions">
                <button type="submit" class="btn btn-primary">保存</button>
                <a class="btn btn-ghost btn-xs" href="/admin/tests.php">返回列表</a>
            </div>
        </form>
    </div>

<?php elseif ($section === 'questions'): ?>
    <div class="admin-card">
        <?php if (empty($questions)): ?>
            <p class="admin-table__muted">当前测验还没有题目（如需新增/删除题目，请通过数据库脚本或单独工具操作）。</p>
        <?php else: ?>
            <div class="question-card-list">
                <?php
                // 一次性查询所有题目的选项，避免 N+1 查询问题
                $questionIds = array_column($questions, 'id');
                $optionsByQuestionId = [];
                
                if (!empty($questionIds)) {
                    // 使用占位符构建 IN 查询
                    $placeholders = implode(',', array_fill(0, count($questionIds), '?'));
                    $stmtOpt = $pdo->prepare("
                        SELECT id, question_id, option_key, option_text
                        FROM question_options
                        WHERE question_id IN ($placeholders)
                        ORDER BY question_id ASC, option_key ASC, id ASC
                    ");
                    $stmtOpt->execute($questionIds);
                    $allOptions = $stmtOpt->fetchAll(PDO::FETCH_ASSOC);
                    
                    // 按 question_id 分组
                    foreach ($allOptions as $opt) {
                        $qid = (int)$opt['question_id'];
                        if (!isset($optionsByQuestionId[$qid])) {
                            $optionsByQuestionId[$qid] = [];
                        }
                        $optionsByQuestionId[$qid][] = $opt;
                    }
                }
                
                foreach ($questions as $q):
                    $qid = (int)$q['id'];
                    $opts = $optionsByQuestionId[$qid] ?? [];
                ?>
                    <div class="question-card">
                        <div class="question-card__header">
                            <div class="question-card__meta">
                                <span class="question-card__badge">#<?= (int)$q['sort_order'] ?></span>
                                <span class="question-card__id">QID: <?= $qid ?></span>
                            </div>
                            <div class="question-card__title">题目文案</div>
                        </div>

                        <form method="post" class="question-card__form">
                            <?php require_once __DIR__ . '/../../lib/csrf.php'; echo CSRF::getTokenField(); ?>
                            <input type="hidden" name="edit_type" value="question_copy">
                            <input type="hidden" name="question_id" value="<?= $qid ?>">

                            <textarea
                                name="question_text"
                                class="form-textarea question-card__textarea"
                            ><?= htmlspecialchars($q['question_text']) ?></textarea>

                            <?php if (!empty($opts)): ?>
                                <table class="admin-table question-card__options-table">
                                    <thead>
                                    <tr>
                                        <th style="width:70px;">选项</th>
                                        <th>文案</th>
                                    </tr>
                                    </thead>
                                    <tbody>
                                    <?php foreach ($opts as $opt): ?>
                                        <tr>
                                            <td class="admin-table__muted">
                                                <?= htmlspecialchars($opt['option_key']) ?>
                                            </td>
                                            <td>
                                                <input type="text"
                                                       name="option_text[<?= (int)$opt['id'] ?>]"
                                                       class="form-input"
                                                       value="<?= htmlspecialchars($opt['option_text']) ?>">
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                    </tbody>
                                </table>
                            <?php else: ?>
                                <p class="admin-table__muted">暂无选项（如需新增选项，请通过数据库操作）。</p>
                            <?php endif; ?>

                            <div class="question-card__actions">
                                <button type="submit" class="btn btn-primary btn-xs">
                                    保存文案
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

<?php elseif ($section === 'results'): ?>
    <div class="admin-card">
        <?php if (empty($results)): ?>
            <p class="admin-table__muted">当前测验还没有结果配置（如需新增/删除结果，请通过数据库操作）。</p>
        <?php else: ?>
            <div class="result-card-list">
                <?php foreach ($results as $r): ?>
                    <div class="result-card">
                        <div class="result-card__header">
                            <div class="result-card__meta">
                                <span class="result-card__badge">
                                    <?= htmlspecialchars($r['code']) ?>
                                </span>
                                <span class="result-card__id">RID: <?= (int)$r['id'] ?></span>
                                <?php
                                $min = $r['min_score'];
                                $max = $r['max_score'];
                                ?>
                                <span class="result-card__range">
                                    <?php if ($min === null && $max === null): ?>
                                        无区间（simple 模式）
                                    <?php else: ?>
                                        区间：<?= htmlspecialchars((string)$min) ?> - <?= htmlspecialchars((string)$max) ?>
                                    <?php endif; ?>
                                </span>
                            </div>
                        </div>

                        <form method="post" class="result-card__form">
                            <?php require_once __DIR__ . '/../../lib/csrf.php'; echo CSRF::getTokenField(); ?>
                            <input type="hidden" name="edit_type" value="result_copy">
                            <input type="hidden" name="result_id" value="<?= (int)$r['id'] ?>">

                            <div class="form-grid">
                                <div class="form-field">
                                    <label class="form-label">结果标题</label>
                                    <input type="text"
                                           name="title"
                                           class="form-input"
                                           value="<?= htmlspecialchars($r['title']) ?>">
                                </div>

                                <div class="form-field">
                                    <label class="form-label">配图 URL（可选）</label>
                                    <input type="text"
                                           name="image_url"
                                           class="form-input"
                                           value="<?= htmlspecialchars($r['image_url'] ?? '') ?>">
                                    <p class="form-help">如需在前台展示结果插图，可以填写一张图片的完整 URL。</p>
                                </div>
                            </div>

                            <div class="form-field" style="margin-top:10px;">
                                <label class="form-label">结果描述文案（富文本）</label>
                                <?php $editorId = 'result-desc-' . (int)$r['id']; ?>

                                <div class="rte-toolbar" data-rte-for="<?= $editorId ?>">
                                    <button type="button" class="btn btn-xs btn-ghost" data-cmd="bold">B</button>
                                    <button type="button" class="btn btn-xs btn-ghost" data-cmd="italic"><em>I</em></button>
                                    <button type="button" class="btn btn-xs btn-ghost" data-cmd="underline"><u>U</u></button>
                                    <button type="button" class="btn btn-xs btn-ghost" data-cmd="strikeThrough"><s>S</s></button>

                                    <span class="rte-toolbar__divider"></span>

                                    <div class="rte-color-picker-wrapper" style="display: inline-block; position: relative;">
                                        <button type="button" class="btn btn-xs btn-ghost rte-color-trigger" data-cmd="foreColor" title="文字颜色">
                                            <span style="display: inline-block; width: 16px; height: 16px; background: #ef4444; border-radius: 2px; vertical-align: middle;"></span>
                                        </button>
                                        <div class="rte-color-picker" style="display: none; position: absolute; top: 100%; left: 0; z-index: 1000; background: #1f2937; border: 1px solid #374151; border-radius: 6px; padding: 8px; margin-top: 4px; box-shadow: 0 4px 6px rgba(0,0,0,0.3);">
                                            <div style="display: grid; grid-template-columns: repeat(8, 1fr); gap: 4px; width: 200px;">
                                                <button type="button" class="rte-color-btn" data-color="#000000" style="width: 20px; height: 20px; background: #000000; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#374151" style="width: 20px; height: 20px; background: #374151; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#6b7280" style="width: 20px; height: 20px; background: #6b7280; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#9ca3af" style="width: 20px; height: 20px; background: #9ca3af; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#d1d5db" style="width: 20px; height: 20px; background: #d1d5db; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#e5e7eb" style="width: 20px; height: 20px; background: #e5e7eb; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#f3f4f6" style="width: 20px; height: 20px; background: #f3f4f6; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#ffffff" style="width: 20px; height: 20px; background: #ffffff; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#ef4444" style="width: 20px; height: 20px; background: #ef4444; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#f97316" style="width: 20px; height: 20px; background: #f97316; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#fbbf24" style="width: 20px; height: 20px; background: #fbbf24; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#22c55e" style="width: 20px; height: 20px; background: #22c55e; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#10b981" style="width: 20px; height: 20px; background: #10b981; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#06b6d4" style="width: 20px; height: 20px; background: #06b6d4; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#3b82f6" style="width: 20px; height: 20px; background: #3b82f6; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#6366f1" style="width: 20px; height: 20px; background: #6366f1; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#8b5cf6" style="width: 20px; height: 20px; background: #8b5cf6; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#a855f7" style="width: 20px; height: 20px; background: #a855f7; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#d946ef" style="width: 20px; height: 20px; background: #d946ef; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#ec4899" style="width: 20px; height: 20px; background: #ec4899; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#f43f5e" style="width: 20px; height: 20px; background: #f43f5e; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#fef9c3" style="width: 20px; height: 20px; background: #fef9c3; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#dbeafe" style="width: 20px; height: 20px; background: #dbeafe; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#e9d5ff" style="width: 20px; height: 20px; background: #e9d5ff; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#fce7f3" style="width: 20px; height: 20px; background: #fce7f3; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#fecdd3" style="width: 20px; height: 20px; background: #fecdd3; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#fed7aa" style="width: 20px; height: 20px; background: #fed7aa; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#fde68a" style="width: 20px; height: 20px; background: #fde68a; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#d1fae5" style="width: 20px; height: 20px; background: #d1fae5; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                                <button type="button" class="rte-color-btn" data-color="#cffafe" style="width: 20px; height: 20px; background: #cffafe; border: 1px solid #4b5563; border-radius: 3px; cursor: pointer;"></button>
                                            </div>
                                        </div>
                                    </div>

                                    <span class="rte-toolbar__divider"></span>

                                    <button type="button" class="btn btn-xs btn-ghost" data-cmd="createLink">链接</button>
                                    <button type="button" class="btn btn-xs btn-ghost" data-cmd="insertImage">图片</button>

                                    <span class="rte-toolbar__divider"></span>

                                    <div class="rte-emoji-picker-wrapper emoji-select-wrapper">
                                        <select class="rte-emoji-picker emoji-select">
                                            <option value="">Emoji</option>
                                            <?php 
                                            $emojiList = array_filter($emojiOptions, function($key) { return $key !== ''; }, ARRAY_FILTER_USE_KEY);
                                            foreach ($emojiList as $emoji): 
                                            ?>
                                                <option value="<?= htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($emoji) ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                        <div class="emoji-dropdown-grid" style="display: none;">
                                            <?php 
                                            $emojiList = array_filter($emojiOptions, function($key) { return $key !== ''; }, ARRAY_FILTER_USE_KEY);
                                            foreach ($emojiList as $emoji): 
                                            ?>
                                                <button type="button" class="emoji-dropdown-item" data-emoji="<?= htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') ?>" title="<?= htmlspecialchars($emoji, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($emoji) ?></button>
                                            <?php endforeach; ?>
                                        </div>
                                    </div>
                                </div>

                                <div id="<?= $editorId ?>"
                                     class="rte-editor"
                                     contenteditable="true"><?= !empty($r['description']) ? $r['description'] : '' ?></div>

                                <textarea
                                    name="description"
                                    class="rte-hidden-textarea"
                                    style="display:none;"
                                ><?= htmlspecialchars($r['description'] ?? '') ?></textarea>

                                <p class="form-help">
                                    支持基础富文本（粗体、颜色、Emoji、链接、图片URL），仅编辑文案，不修改规则与区间。
                                </p>
                            </div>

                            <div class="result-card__actions">
                                <button type="submit" class="btn btn-primary btn-xs">
                                    保存文案
                                </button>
                            </div>
                        </form>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>
