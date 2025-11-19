<?php
$testId   = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : null;
$errors   = [];
$statuses = [
    'draft'     => '�׸�',
    'published' => '�ѷ���',
    'archived'  => '�ѹǼ�',
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
        $errors[] = 'δ�ҵ������Բ��ԣ�';
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
        $errors[] = '�������Ʊ�����д��';
    }
    if ($formData['slug'] === '') {
        $errors[] = 'Slug ������д��';
    } elseif (!preg_match('/^[a-z0-9_-]+$/', $formData['slug'])) {
        $errors[] = 'Slug ֻ��������ĸ�����ֺ�ƽ�ַ��';
    }
    if (!isset($statuses[$formData['status']])) {
        $errors[] = '��ѡ����Ч��״ֵ̬��';
    }
    if ($formData['title_color'] === '') {
        $formData['title_color'] = '#4f46e5';
    } elseif (!preg_match('/^#[0-9a-fA-F]{6}$/', $formData['title_color'])) {
        $errors[] = '��ѡ��Ч�Ŀ��� RGB ����ֵ��';
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
            $errors[] = '�� slug �Ѵ��ڣ�������ѡһ���µľ���';
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
    <div class="alert alert-danger">δ�ҵ�����������뻻һ��������ٴβ鿴��</div>
    <a href="/admin/tests.php" class="btn btn-ghost btn-xs">���ذ����б�</a>
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
                <span>���� *</span>
                <input type="text" name="title" value="<?= htmlspecialchars($formData['title']) ?>" required>
            </label>
            <label>
                <span>Slug *</span>
                <input type="text" name="slug" value="<?= htmlspecialchars($formData['slug']) ?>" required>
                <small class="muted">�� URL ��ʹ�ã�ֻ��Ӣ����ĸ�����ֺ͵����ַ���</small>
            </label>
        </div>

        <div class="form-grid">
            <label>
                <span>������</span>
                <input type="text" name="subtitle" value="<?= htmlspecialchars($formData['subtitle']) ?>">
            </label>
            <label>
                <span>������ɫ</span>
                <input type="color" name="title_color" value="<?= htmlspecialchars($formData['title_color'] ?: '#4f46e5') ?>">
            </label>
        </div>

        <label>
            <span>������� (�ɿ�)</span>
            <textarea name="description" rows="5"><?= htmlspecialchars($formData['description']) ?></textarea>
        </label>

        <label>
            <span>��ǩ</span>
            <input type="text" name="tags" value="<?= htmlspecialchars($formData['tags']) ?>" placeholder="�ö��ŷָ���ǩ��">
            <small class="muted">ʾ��: ����,����,�˸�</small>
        </label>

        <div class="form-grid">
            <label>
                <span>״̬ *</span>
                <select name="status">
                    <?php foreach ($statuses as $value => $label): ?>
                        <option value="<?= htmlspecialchars($value) ?>"<?= $formData['status'] === $value ? ' selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                <span>���� sort_order</span>
                <input type="number" name="sort_order" value="<?= (int)$formData['sort_order'] ?>" step="1">
            </label>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">����</button>
            <a class="btn btn-ghost btn-xs" href="/admin/tests.php">���ذ����б�</a>
        </div>
    </form>
</div>
