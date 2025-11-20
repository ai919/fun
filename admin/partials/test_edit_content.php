<?php
$testId   = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : null;
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
?>

<div class="card">
    <form method="post">
        <div class="form-grid">
            <label>
                <span>标题 *</span>
                <input type="text" name="title" value="<?= htmlspecialchars($formData['title']) ?>" required>
            </label>
            <label>
                <span>Slug *</span>
                <input type="text" name="slug" value="<?= htmlspecialchars($formData['slug']) ?>" required>
                <small class="muted">用于 URL，只能包含小写英文字母、数字、短横线、下划线。</small>
            </label>
        </div>

        <div class="form-grid">
            <label>
                <span>副标题</span>
                <input type="text" name="subtitle" value="<?= htmlspecialchars($formData['subtitle']) ?>">
            </label>
            <label>
                <span>标题颜色</span>
                <input type="hidden" name="title_color_clear" value="<?= $colorClearDefault ?>">
                <div class="color-input-row">
                    <input type="color"
                           name="title_color"
                           value="<?= htmlspecialchars($formData['title_color'] !== '' ? $formData['title_color'] : '#6366F1') ?>"
                           oninput="this.form.title_color_clear.value='0';">
                    <button type="button" class="btn btn-ghost btn-xs"
                            onclick="this.closest('form').title_color_clear.value='1'; this.closest('form').title_color.value='#6366F1';">
                        清空
                    </button>
                </div>
                <small class="muted">清空后前台使用默认颜色。</small>
            </label>
        </div>

        <div class="form-grid">
            <label>
                <span>Emoji</span>
                <select name="emoji">
                    <?php foreach ($emojiOptions as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>"<?= $emojiSelectValue === $value ? ' selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="muted">可选填，为标题增加一个小图标。</small>
            </label>
            <label>
                <span>自定义 Emoji</span>
                <input type="text" name="emoji_custom" value="<?= htmlspecialchars($emojiSelectValue === '' ? $formData['emoji'] : '') ?>" maxlength="16" placeholder="也可手动输入">
                <small class="muted">如果下拉没有想要的，可以在此输入（不超过 16 字符）。</small>
            </label>
        </div>

        <label>
            <span>测验介绍</span>
            <textarea name="description" rows="5"><?= htmlspecialchars($formData['description']) ?></textarea>
        </label>

        <label>
            <span>标签（逗号分隔）</span>
            <input type="text" name="tags" value="<?= htmlspecialchars($formData['tags']) ?>" placeholder="例如：性格, MBTI, 自我探索">
        </label>

        <div class="form-grid">
            <label>
                <span>状态 *</span>
                <select name="status">
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>"<?= $formData['status'] === $value ? ' selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>排序值</span>
                <input type="number" name="sort_order" value="<?= (int)$formData['sort_order'] ?>" step="1">
            </label>
        </div>

        <div class="form-grid">
            <label>
                <span>评分模式 *</span>
                <select name="scoring_mode">
                    <?php foreach ($scoringModes as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>"<?= $formData['scoring_mode'] === $value ? ' selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
        </div>

        <label>
            <span>评分配置（JSON，可选）</span>
            <textarea name="scoring_config" rows="5" placeholder='例如：{"dimensions":["I","E","R","F"]}'><?= htmlspecialchars($formData['scoring_config']) ?></textarea>
            <small class="muted">仅在部分评分模式下有效，请输入合法的 JSON。</small>
        </label>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
            <a class="btn btn-ghost btn-xs" href="/admin/tests.php">返回列表</a>
        </div>
    </form>
</div>
