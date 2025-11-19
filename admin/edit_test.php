<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';

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

$pageTitle    = '编辑测试 · DoFun';
$pageHeading  = '编辑测试：' . ($test['title'] ?? '');
$pageSubtitle = '当前 slug：' . ($test['slug'] ?? '') . ' · ID: ' . $testId;
$activeMenu   = 'tests';

$errors  = [];
$success = null;

$slug        = $test['slug'];
$title       = $test['title'];
$description = $test['description'];
$cover       = $test['cover_image'];
$tags        = $test['tags'] ?? '';
$titleEmoji  = $test['title_emoji'] ?? '';
$titleColor  = $test['title_color'] ?? '#111827';
if ($titleColor === '') {
    $titleColor = '#111827';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug        = trim($_POST['slug'] ?? '');
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cover       = trim($_POST['cover_image'] ?? '');
    $tags        = trim($_POST['tags'] ?? '');
    $titleEmoji  = trim($_POST['title_emoji'] ?? '');
    $colorPicker = trim($_POST['title_color'] ?? '');
    $colorText   = trim($_POST['title_color_text'] ?? '');
    $titleColor  = $colorText !== '' ? $colorText : $colorPicker;
    if ($titleColor === '') {
        $titleColor = '#111827';
    }

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
            $errors[] = '这个 slug 已经被占用，请换一个。';
        }
    }

    if (!$errors) {
        $update = $pdo->prepare(
            "UPDATE tests
             SET slug = ?, title = ?, description = ?, cover_image = ?, tags = ?, title_emoji = ?, title_color = ?
             WHERE id = ?"
        );
        $update->execute([$slug, $title, $description, $cover, $tags, $titleEmoji, $titleColor, $testId]);
        $success = '测试信息已更新。';
    }
}

require __DIR__ . '/layout.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="post" class="admin-form">
    <div class="field">
        <label for="slug">测试路径 slug（必填）</label>
        <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($slug) ?>">
        <div class="field-hint">用户访问路径为 <code>/<?= htmlspecialchars($slug) ?></code>，仅限小写字母、数字、下划线、短横线。</div>
    </div>

    <div class="field">
        <label for="title">测试标题（必填）</label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($title) ?>">
    </div>

    <div class="field">
        <label for="description">测试简介（可选）</label>
        <textarea id="description" name="description" rows="3"><?= htmlspecialchars($description) ?></textarea>
    </div>

    <div class="field">
        <label for="cover_image">封面图 URL</label>
        <input type="text" id="cover_image" name="cover_image" value="<?= htmlspecialchars($cover) ?>">
        <div class="field-hint">留空将自动使用 <code>/assets/images/default.png</code>。</div>
    </div>

    <div class="field">
        <label for="tags">测验标签（多标签）</label>
        <input type="text" id="tags" name="tags" class="input-text"
               placeholder="例如：情感,亲密关系,自我探索"
               value="<?= htmlspecialchars($tags) ?>">
        <div class="field-hint">多个标签用逗号分隔，将显示在卡片上的类型标签。</div>
    </div>

    <div class="field">
        <label for="title_emoji">标题 Emoji（可选）</label>
        <input type="text" id="title_emoji" name="title_emoji" class="input-text"
               placeholder="例如：💰 或 🐱"
               value="<?= htmlspecialchars($titleEmoji) ?>">
    </div>

    <div class="field">
        <label>标题颜色（可选）</label>
        <div style="display:flex; gap:8px; align-items:center;">
            <input type="color" name="title_color"
                   value="<?= htmlspecialchars($titleColor ?? '#111827') ?>">
            <input type="text" name="title_color_text" class="input-text"
                   style="max-width:130px;"
                   value="<?= htmlspecialchars($titleColor ?? '#111827') ?>">
        </div>
        <div class="field-hint">留空则使用默认颜色。</div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">保存测试</button>
        <a class="btn btn-ghost" href="/admin/tests.php">返回列表</a>
    </div>
</form>

<?php require __DIR__ . '/layout_footer.php'; ?>
