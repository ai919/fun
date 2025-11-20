<?php
$testId   = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : null;
$errors   = [];
$statuses = [
    'draft'     => '草稿',
    'published' => '已发布',
    'archived'  => '已归档',
];

$formData = [
    'title'       => '',
    'slug'        => '',
    'subtitle'    => '',
    'description' => '',
    'title_color' => '#4f46e5',
    'tags'        => '',
    'status'      => 'draft',
    'sort_order'  => 0,
];

$existingTest = null;
if ($testId) {
    $stmt = $pdo->prepare('SELECT * FROM tests WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $testId]);
    $existingTest = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$existingTest) {
        $errors[] = '未找到对应的测试。';
    } else {
        foreach ($formData as $key => $defaultValue) {
            if (array_key_exists($key, $existingTest) && $existingTest[$key] !== null) {
                $formData[$key] = $existingTest[$key];
            }
        }
        if (!$formData['title_color']) {
            $formData['title_color'] = '#4f46e5';
        }
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && (!$testId || ($testId && $existingTest))) {
    $formData['title']       = trim($_POST['title'] ?? '');
    $formData['slug']        = strtolower(trim($_POST['slug'] ?? ''));
    $formData['subtitle']    = trim($_POST['subtitle'] ?? '');
    $formData['description'] = trim($_POST['description'] ?? '');
    $formData['title_color'] = trim($_POST['title_color'] ?? '#4f46e5');
    $formData['tags']        = trim($_POST['tags'] ?? '');
    $formData['status']      = $_POST['status'] ?? 'draft';
    $formData['sort_order']  = (int)($_POST['sort_order'] ?? 0);

    if ($formData['title'] === '') {
        $errors[] = '测试标题不能为空。';
    }
    if ($formData['slug'] === '') {
        $errors[] = 'Slug 不能为空。';
    } elseif (!preg_match('/^[a-z0-9_-]+$/', $formData['slug'])) {
        $errors[] = 'Slug 只能包含小写字母、数字、短横线和下划线。';
    }
    if (!isset($statuses[$formData['status']])) {
        $errors[] = '请选择有效的状态。';
    }
    if ($formData['title_color'] === '') {
        $formData['title_color'] = '#4f46e5';
    } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $formData['title_color'])) {
        $errors[] = '请输入合法的颜色值，例如 #4f46e5。';
    }

    $tagsNormalized = '';
    if ($formData['tags'] !== '') {
        $tagPieces = array_unique(array_filter(array_map('trim', explode(',', $formData['tags']))));
        $tagsNormalized = implode(', ', $tagPieces);
    }
    $formData['tags'] = $tagsNormalized;

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
            ':title'       => $formData['title'],
            ':slug'        => $formData['slug'],
            ':subtitle'    => $formData['subtitle'] !== '' ? $formData['subtitle'] : null,
            ':description' => $formData['description'] !== '' ? $formData['description'] : null,
            ':title_color' => $formData['title_color'] ?: '#4f46e5',
            ':tags'        => $formData['tags'] !== '' ? $formData['tags'] : null,
            ':status'      => $formData['status'],
            ':sort_order'  => $formData['sort_order'],
        ];

        if ($testId) {
            $payload[':id'] = $testId;
            $updateSql = "UPDATE tests SET
                title = :title,
                slug = :slug,
                subtitle = :subtitle,
                description = :description,
                title_color = :title_color,
                tags = :tags,
                status = :status,
                sort_order = :sort_order,
                updated_at = NOW()
            WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($payload);
        } else {
            $insertSql = "INSERT INTO tests
                (title, slug, subtitle, description, title_color, tags, status, sort_order)
                VALUES
                (:title, :slug, :subtitle, :description, :title_color, :tags, :status, :sort_order)";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute($payload);
        }

        header('Location: /admin/tests.php?msg=saved');
        exit;
    }
}
?>

<?php if ($testId && !$existingTest): ?>
    <div class="alert alert-danger">未找到需要编辑的测试。</div>
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
                <input type="color" name="title_color" value="<?= htmlspecialchars($formData['title_color'] ?: '#4f46e5') ?>">
            </label>
        </div>

        <label>
            <span>测试介绍</span>
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

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">保存</button>
            <a class="btn btn-ghost btn-xs" href="/admin/tests.php">返回列表</a>
        </div>
    </form>
</div>
