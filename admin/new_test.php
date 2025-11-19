<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';

$pageTitle    = '新增测试 · DoFun';
$pageHeading  = '新增测试';
$pageSubtitle = '填写基础信息、封面与标签即可创建新测试。';
$activeMenu   = 'new';

$errors  = [];
$success = null;
$newSlug = '';

$slug        = '';
$title       = '';
$description = '';
$cover       = '/assets/images/default.png';
$tags        = '';
$titleEmoji  = '';
$titleColor  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug        = trim($_POST['slug'] ?? '');
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cover       = trim($_POST['cover_image'] ?? '');
    $tags        = trim($_POST['tags'] ?? '');
    $titleEmoji  = trim($_POST['title_emoji'] ?? '');
    $titleColor  = trim($_POST['title_color'] ?? '');

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
        $check = $pdo->prepare('SELECT COUNT(*) FROM tests WHERE slug = ?');
        $check->execute([$slug]);
        if ($check->fetchColumn() > 0) {
            $errors[] = '这个 slug 已被占用，请换一个（例如后面加数字）。';
        }
    }

    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO tests (slug, title, description, cover_image, tags, title_emoji, title_color)
             VALUES (?, ?, ?, ?, ?, ?, ?)'
        );
        $stmt->execute([$slug, $title, $description, $cover, $tags, $titleEmoji, $titleColor]);

        $success = '测试已创建成功！现在可以访问 /' . htmlspecialchars($slug) . '。';
        $newSlug = $slug;

        $slug        = '';
        $title       = '';
        $description = '';
        $cover       = '/assets/images/default.png';
        $tags        = '';
        $titleEmoji  = '';
        $titleColor  = '';
    }
}

require __DIR__ . '/layout.php';
?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<form method="post" class="admin-form">
    <div class="field">
        <label for="slug">测试路径 slug（必填）</label>
        <input type="text" id="slug" name="slug"
               placeholder="例如：love / animal / work / money_anxiety"
               value="<?= htmlspecialchars($slug) ?>">
        <div class="field-hint">
            只允许小写字母、数字、下划线、短横线；用户访问路径将是 <code>/slug</code>。
        </div>
    </div>

    <div class="field">
        <label for="title">测试标题（必填）</label>
        <input type="text" id="title" name="title"
               placeholder="例如：你的存钱焦虑等级是多少？"
               value="<?= htmlspecialchars($title) ?>">
    </div>

    <div class="field">
        <label for="description">测试简介（可选）</label>
        <textarea id="description" name="description" rows="3"
                  placeholder="一句话介绍这个测试的用途、风格、适合谁做"><?= htmlspecialchars($description) ?></textarea>
    </div>

    <div class="field">
        <label for="cover_image">封面图 URL</label>
        <input type="text" id="cover_image" name="cover_image"
               placeholder="/assets/images/default.png 或完整图片 URL"
               value="<?= htmlspecialchars($cover) ?>">
        <div class="field-hint">
            留空则使用默认封面 <code>/assets/images/default.png</code>。
        </div>
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
        <label for="title_color">标题颜色（可选）</label>
        <input type="text" id="title_color" name="title_color" class="input-text"
               placeholder="例如：#111827 或 #ef4444"
               value="<?= htmlspecialchars($titleColor) ?>">
        <div class="field-hint">留空则使用默认颜色。</div>
    </div>

    <button type="submit" class="btn btn-primary">创建测试</button>
</form>

<?php require __DIR__ . '/layout_footer.php'; ?>
