<?php
$testId   = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : null;
$section  = isset($section) ? $section : (isset($_GET['section']) ? trim((string)$_GET['section']) : 'basic');
if (!in_array($section, ['basic', 'questions', 'results'], true)) {
    $section = 'basic';
}
$errors   = [];
$statuses = [
    'draft'     => '草稿',
    'published' => '已发布',
    'archived'  => '已归档',
];

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
$emojiOptions = [
    ''   => '（不选择）',
    '🧠' => '🧠 大脑',
    '💘' => '💘 爱心',
    '🔥' => '🔥 火焰',
    '🌙' => '🌙 月亮',
    '🎲' => '🎲 骰子',
    '📚' => '📚 书本',
    '😈' => '😈 小恶魔',
    '🌈' => '🌈 彩虹',
    '⭐' => '⭐ 星星',
    '🎯' => '🎯 靶心',
    '🎧' => '🎧 耳机',
    '🪐' => '🪐 行星',
];
$scoringModes = [
    'simple'     => 'Simple（单结果）',
    'dimensions' => 'Dimensions（维度组合）',
    'range'      => 'Range（区间）',
    'custom'     => 'Custom（自定义）',
];

$formData = [
    'title'          => '',
    'slug'           => '',
    'subtitle'       => '',
    'description'    => '',
    'emoji'          => '',
    'title_color'    => '#6366F1',
    'tags'           => '',
    'status'         => 'draft',
    'sort_order'     => 0,
    'scoring_mode'   => 'simple',
    'scoring_config' => '',
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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!$testId || ($testId && $existingTest))) {
    $formData['title']          = trim($_POST['title'] ?? '');
    $formData['slug']           = strtolower(trim($_POST['slug'] ?? ''));
    $formData['subtitle']       = trim($_POST['subtitle'] ?? '');
    $formData['description']    = trim($_POST['description'] ?? '');
    $titleColorClear            = ($_POST['title_color_clear'] ?? '0') === '1';
    $formData['title_color']    = $titleColorClear ? '' : trim($_POST['title_color'] ?? '');
    $selectedEmoji              = trim($_POST['emoji'] ?? '');
    $customEmoji                = trim($_POST['emoji_custom'] ?? '');
    $formData['emoji']          = $customEmoji !== '' ? $customEmoji : $selectedEmoji;
    $formData['tags']           = trim($_POST['tags'] ?? '');
    $formData['status']         = $_POST['status'] ?? 'draft';
    $formData['sort_order']     = (int)($_POST['sort_order'] ?? 0);
    $formData['scoring_mode']   = $_POST['scoring_mode'] ?? 'simple';
    $formData['scoring_config'] = trim($_POST['scoring_config'] ?? '');

    if ($formData['title'] === '') {
        $errors[] = '测验标题不能为空。';
    }
    if ($formData['slug'] === '') {
        $errors[] = 'Slug 不能为空。';
    } elseif (!preg_match('/^[a-z0-9_-]+$/', $formData['slug'])) {
        $errors[] = 'Slug 只能包含小写字母、数字、短横线和下划线。';
    }
    if (!isset($statuses[$formData['status']])) {
        $errors[] = '请选择有效的状态。';
    }
    if (!array_key_exists($formData['scoring_mode'], $scoringModes)) {
        $errors[] = '请选择有效的评分模式。';
    }
    if ($hasTitleColorCol && $formData['title_color'] !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $formData['title_color'])) {
        $errors[] = '请输入合法的颜色值，例如 #6366F1。';
    }
    if (mb_strlen($formData['emoji']) > 16) {
        $errors[] = 'Emoji 最长支持 16 个字符。';
    }

    $tagsNormalized = '';
    if ($formData['tags'] !== '') {
        $tagPieces = array_unique(array_filter(array_map('trim', explode(',', $formData['tags']))));
        $tagsNormalized = implode(', ', $tagPieces);
    }
    $formData['tags'] = $tagsNormalized;

    if ($formData['scoring_config'] !== '') {
        json_decode($formData['scoring_config'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = '评分配置不是合法的 JSON。';
        }
    }

    if ($formData['slug'] !== '') {
        if ($testId) {
            $slugStmt = $pdo->prepare('SELECT COUNT(*) FROM tests WHERE slug = :slug AND id != :id');
            $slugStmt->execute([':slug' => $formData['slug'], ':id' => $testId]);
        } else {
            $slugStmt = $pdo->prepare('SELECT COUNT(*) FROM tests WHERE slug = :slug');
            $slugStmt->execute([':slug' => $formData['slug']]);
        }
        if ((int)$slugStmt->fetchColumn() > 0) {
            $errors[] = '该 slug 已存在，请换一个。';
        }
    }

    if (!$errors) {
        $payload = [
            ':title'          => $formData['title'],
            ':slug'           => $formData['slug'],
            ':subtitle'       => $formData['subtitle'] !== '' ? $formData['subtitle'] : null,
            ':description'    => $formData['description'] !== '' ? $formData['description'] : null,
            ':tags'           => $formData['tags'] !== '' ? $formData['tags'] : null,
            ':status'         => $formData['status'],
            ':sort_order'     => $formData['sort_order'],
            ':scoring_mode'   => $formData['scoring_mode'],
            ':scoring_config' => $formData['scoring_config'] !== '' ? $formData['scoring_config'] : null,
        ];
        if ($hasEmojiCol) {
            $payload[':emoji'] = $formData['emoji'] !== '' ? $formData['emoji'] : null;
        }
        if ($hasTitleColorCol) {
            $payload[':title_color'] = $formData['title_color'] !== '' ? $formData['title_color'] : null;
        }

        if ($testId) {
            $payload[':id'] = $testId;
            $setParts = [
                'title = :title',
                'slug = :slug',
                'subtitle = :subtitle',
                'description = :description',
                'tags = :tags',
                'status = :status',
                'sort_order = :sort_order',
                'scoring_mode = :scoring_mode',
                'scoring_config = :scoring_config',
                'updated_at = NOW()',
            ];
            if ($hasEmojiCol) {
                $setParts[] = 'emoji = :emoji';
            }
            if ($hasTitleColorCol) {
                $setParts[] = 'title_color = :title_color';
            }
            $updateSql = "UPDATE tests SET " . implode(",\n                ", $setParts) . " WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($payload);
        } else {
            $columns = ['title', 'slug', 'subtitle', 'description', 'tags', 'status', 'sort_order', 'scoring_mode', 'scoring_config'];
            $placeholders = [':title', ':slug', ':subtitle', ':description', ':tags', ':status', ':sort_order', ':scoring_mode', ':scoring_config'];
            if ($hasEmojiCol) {
                $columns[] = 'emoji';
                $placeholders[] = ':emoji';
            }
            if ($hasTitleColorCol) {
                $columns[] = 'title_color';
                $placeholders[] = ':title_color';
            }
            $insertSql = "INSERT INTO tests (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute($payload);
        }

        header('Location: /admin/tests.php?msg=saved');
        exit;
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
        SELECT q.id,
               q.question_text AS question_text,
               q.sort_order,
               COUNT(o.id) AS option_count
        FROM questions q
        LEFT JOIN question_options o ON o.question_id = q.id
        WHERE q.test_id = :test_id
        GROUP BY q.id
        ORDER BY q.sort_order ASC, q.id ASC
    ");
    $stmtQ->execute([':test_id' => $testId]);
    $questions = $stmtQ->fetchAll(PDO::FETCH_ASSOC);

    $stmtR = $pdo->prepare("
        SELECT id, code, title, min_score, max_score
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
                        <select name="emoji" class="form-select">
                            <?php foreach ($emojiOptions as $value => $label): ?>
                                <option value="<?= htmlspecialchars($value) ?>"<?= $emojiSelectValue === $value ? ' selected' : '' ?>>
                                    <?= htmlspecialchars($label) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-help">可选填，为标题增加一个小图标。</p>
                    </div>
                    <div class="form-field">
                        <label class="form-label">自定义 Emoji</label>
                        <input type="text" name="emoji_custom" class="form-input"
                               value="<?= htmlspecialchars($emojiSelectValue === '' ? $formData['emoji'] : '') ?>" maxlength="16" placeholder="也可手动输入">
                        <p class="form-help">如果下拉没有想要的，可以在此输入（不超过 16 字符）。</p>
                    </div>
                </div>
            </div>

            <div class="form-section">
                <div class="form-section__title">描述与标签</div>
                <div class="form-field">
                    <label class="form-label">测验介绍（富文本）</label>

                    <div class="rte-toolbar" data-rte-for="description-editor">
                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="bold">B</button>
                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="italic"><em>I</em></button>
                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="underline"><u>U</u></button>
                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="strikeThrough"><s>S</s></button>

                        <span class="rte-toolbar__divider"></span>

                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="foreColor" data-value="#ef4444">文字红</button>
                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="foreColor" data-value="#22c55e">文字绿</button>
                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="backColor" data-value="#fef9c3">背景黄</button>

                        <span class="rte-toolbar__divider"></span>

                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="createLink">链接</button>
                        <button type="button" class="btn btn-xs btn-ghost" data-cmd="insertImage">图片</button>

                        <span class="rte-toolbar__divider"></span>

                        <select class="rte-emoji-picker">
                            <option value="">Emoji</option>
                            <option>😀</option>
                            <option>😍</option>
                            <option>🤔</option>
                            <option>🥲</option>
                            <option>👍</option>
                            <option>🔥</option>
                            <option>✨</option>
                            <option>💤</option>
                        </select>
                    </div>

                    <div id="description-editor"
                         class="rte-editor"
                         contenteditable="true"><?= !empty($formData['description']) ? $formData['description'] : '' ?></div>

                    <textarea name="description"
                              id="description-hidden"
                              class="rte-hidden-textarea"
                              style="display:none;"><?= $formData['description'] ?? '' ?></textarea>

                    <p class="form-help">
                        可输入段落、Emoji、链接和图片（通过 URL），颜色仅用于前台展示。内容将保存为 HTML。
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

    <script>
        (function() {
            const textarea = document.getElementById('description-editor');
            const preview = document.getElementById('description-preview');
            if (!textarea || !preview) return;

            function updatePreview() {
                let text = textarea.value || '';
                let html = text
                    .replace(/^> (.+)$/gm, '<blockquote>$1</blockquote>')
                    .replace(/\\*\\*(.+?)\\*\\*/g, '<strong>$1</strong>')
                    .replace(/_(.+?)_/g, '<em>$1</em>')
                    .replace(/\\n/g, '<br>');
                preview.innerHTML = html || '<span class=\"admin-table__muted\">预览区</span>';
            }

            document.querySelectorAll('.richtext-toolbar [data-md]').forEach(function(btn) {
                btn.addEventListener('click', function () {
                    const md = this.getAttribute('data-md');
                    const start = textarea.selectionStart;
                    const end = textarea.selectionEnd;
                    const value = textarea.value;
                    if (md === '> ') {
                        const before = value.substring(0, start);
                        const selected = value.substring(start, end) || '';
                        const after = value.substring(end);
                        textarea.value = before + '> ' + selected + after;
                        textarea.selectionStart = start + 2;
                        textarea.selectionEnd = start + 2 + selected.length;
                    } else {
                        const before = value.substring(0, start);
                        const selected = value.substring(start, end) || '文本';
                        const after = value.substring(end);
                        textarea.value = before + md + selected + md + after;
                        textarea.selectionStart = start + md.length;
                        textarea.selectionEnd = start + md.length + selected.length;
                    }
                    textarea.focus();
                    updatePreview();
                });
            });

            textarea.addEventListener('input', updatePreview);
            updatePreview();
        })();
    </script>

<?php elseif ($section === 'questions'): ?>
    <div class="admin-card">
        <?php if (empty($questions)): ?>
            <p class="admin-table__muted">当前测验还没有题目。</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>排序</th>
                    <th>题目内容</th>
                    <th>选项数</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($questions as $q): ?>
                    <tr>
                        <td><?= (int)$q['id'] ?></td>
                        <td><span class="admin-table__muted"><?= (int)$q['sort_order'] ?></span></td>
                        <td><?= htmlspecialchars($q['question_text']) ?></td>
                        <td><span class="admin-table__muted"><?= (int)$q['option_count'] ?></span></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p class="admin-table__muted" style="margin-top:8px;">* 目前仅为只读概览，题目与选项的维护后续在统一编辑入口完成。</p>
    </div>

<?php elseif ($section === 'results'): ?>
    <div class="admin-card">
        <?php if (empty($results)): ?>
            <p class="admin-table__muted">当前测验还没有结果配置。</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                <tr>
                    <th>ID</th>
                    <th>代码</th>
                    <th>标题</th>
                    <th>分数区间</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($results as $r): ?>
                    <tr>
                        <td><?= (int)$r['id'] ?></td>
                        <td><code class="code-badge"><?= htmlspecialchars($r['code']) ?></code></td>
                        <td><?= htmlspecialchars($r['title']) ?></td>
                        <td>
                            <?php
                            $min = $r['min_score'];
                            $max = $r['max_score'];
                            if ($min === null && $max === null) {
                                echo '<span class="admin-table__muted">无区间（simple 模式）</span>';
                            } else {
                                echo '<span class="admin-table__muted">' . htmlspecialchars((string)$min) . ' - ' . htmlspecialchars((string)$max) . '</span>';
                            }
                            ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
        <p class="admin-table__muted" style="margin-top:8px;">* 目前仅为只读概览，结果文案与区间的维护后续在统一编辑入口完成。</p>
    </div>
<?php endif; ?>
