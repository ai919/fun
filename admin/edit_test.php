<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';
require __DIR__ . '/layout.php';

$errors = [];
$success = null;
$testId = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if (!$testId) {
    die('缺少测试 ID');
}

$stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
$stmt->execute([$testId]);
$test = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    die('测试不存在');
}

$slug        = $test['slug'];
$title       = $test['title'];
$description = $test['description'];
$cover       = $test['cover_image'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug        = trim($_POST['slug'] ?? '');
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cover       = trim($_POST['cover_image'] ?? '');

    if ($slug === '' || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
        $errors[] = 'Slug 只能使用小写字母、数字、下划线、短横线，并且不能为空。';
    }

    if ($title === '') {
        $errors[] = '测试标题不能为空。';
    }

    if ($cover === '') {
        $cover = '/assets/images/default.png';
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM tests WHERE slug = ? AND id <> ?');
        $check->execute([$slug, $testId]);
        if ($check->fetchColumn() > 0) {
            $errors[] = '这个 slug 已经被占用了，请换一个。';
        }
    }

    if (!$errors) {
        $update = $pdo->prepare(
            "UPDATE tests
             SET slug = ?, title = ?, description = ?, cover_image = ?
             WHERE id = ?"
        );
        $update->execute([$slug, $title, $description, $cover, $testId]);
        $success = '测试信息已更新。';
    }
}

admin_header('编辑测试 · fun_quiz');
?>
<style>
    .field {
        margin-bottom: 12px;
    }
    .field label {
        display: block;
        margin-bottom: 4px;
    }
    .field input[type="text"],
    .field textarea {
        width: 100%;
        padding: 6px 8px;
    }
    .errors {
        background: #ffecec;
        border: 1px solid #ffb4b4;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 12px;
    }
    .success {
        background: #e7f9ec;
        border: 1px solid #9ad5aa;
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 12px;
    }
    .hint {
        font-size: 13px;
        color: #666;
    }
</style>

<h1>编辑测试：<?= htmlspecialchars($test['title'] ?? '') ?></h1>

<?php if ($errors): ?>
    <div class="errors">
        <strong>有一些问题：</strong>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="success">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<form method="post">
    <div class="field">
        <label for="slug">测试路径 slug（必填）</label>
        <input type="text" id="slug" name="slug"
               value="<?= htmlspecialchars($slug ?? '') ?>">
        <div class="hint">只允许小写字母、数字、下划线、短横线。</div>
    </div>

    <div class="field">
        <label for="title">测试标题（必填）</label>
        <input type="text" id="title" name="title"
               value="<?= htmlspecialchars($title ?? '') ?>">
    </div>

    <div class="field">
        <label for="description">测试简介</label>
        <textarea id="description" name="description" rows="3"><?= htmlspecialchars($description ?? '') ?></textarea>
    </div>

    <div class="field">
        <label for="cover_image">封面图 URL</label>
        <input type="text" id="cover_image" name="cover_image"
               placeholder="/assets/images/default.png 或完整图片 URL"
               value="<?= htmlspecialchars($cover ?? '') ?>">
        <div class="hint">留空则使用默认封面 <code>/assets/images/default.png</code>。</div>
    </div>

    <button type="submit">保存测试</button>
</form>

<?php
admin_footer();
