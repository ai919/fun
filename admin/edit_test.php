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
$titleColor  = $test['title_color'] ?? '#4f46e5';
if ($titleColor === '') {
    $titleColor = '#4f46e5';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug        = trim($_POST['slug'] ?? '');
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cover       = trim($_POST['cover_image'] ?? '');
    $tags        = trim($_POST['tags'] ?? '');
    $titleEmoji  = trim($_POST['title_emoji'] ?? '');
    $titleColor  = trim($_POST['title_color'] ?? '');
    if ($titleColor === '') {
        $titleColor = '#4f46e5';
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
        <label>测验标签（可选）</label>
        <div class="tags-input" data-tags-target="tags_hidden">
            <div class="tags-chips" id="tags_chips"></div>
            <input type="text" id="tags_input" class="input-text tags-input-field"
                   placeholder="输入后回车添加，例如：情感、自我探索、亲密关系">
        </div>
        <input type="hidden"
               name="tags"
               id="tags_hidden"
               value="<?= htmlspecialchars($tags ?? '', ENT_QUOTES, 'UTF-8') ?>">
        <div class="field-hint">多个标签会显示在前台卡片上作为“测验类型标签”。</div>
    </div>

    <div class="field">
        <label for="title_emoji">标题 Emoji（可选）</label>
        <input type="text" id="title_emoji" name="title_emoji" class="input-text"
               placeholder="例如：💰 或 🐱"
               value="<?= htmlspecialchars($titleEmoji) ?>">
    </div>

    <div class="field">
        <label>标题颜色（可选）</label>
        <div class="color-input-row">
            <input
                type="color"
                name="title_color_picker"
                id="title_color_picker"
                value="<?= htmlspecialchars($titleColor ?? '#4f46e5', ENT_QUOTES, 'UTF-8') ?>">
            <input
                type="text"
                name="title_color"
                id="title_color_text"
                class="input-text"
                style="max-width: 140px;"
                placeholder="#4f46e5"
                value="<?= htmlspecialchars($titleColor ?? '#4f46e5', ENT_QUOTES, 'UTF-8') ?>">
        </div>
        <div class="field-hint">可选。留空则使用默认颜色。</div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">保存测试</button>
        <a class="btn btn-ghost" href="/admin/tests.php">返回列表</a>
    </div>
</form>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var picker = document.getElementById('title_color_picker');
    var text = document.getElementById('title_color_text');
    if (picker && text) {
        picker.addEventListener('input', function () {
            text.value = picker.value;
        });

        text.addEventListener('input', function () {
            var v = text.value.trim();
            if (/^#[0-9a-fA-F]{6}$/.test(v)) {
                picker.value = v;
            }
        });
    }

    var hidden = document.getElementById('tags_hidden');
    var input = document.getElementById('tags_input');
    var chips = document.getElementById('tags_chips');
    if (!hidden || !input || !chips) return;

    function parseTags(str) {
        if (!str) return [];
        return str.split(',').map(function (t) {
            return t.trim();
        }).filter(function (t) { return t.length > 0; });
    }

    var tags = parseTags(hidden.value);

    function renderChips() {
        chips.innerHTML = '';
        tags.forEach(function (tag, index) {
            var chip = document.createElement('span');
            chip.className = 'tag-chip';
            chip.innerHTML = '<span class="tag-label">' + tag + '</span><button type="button" class="tag-remove" data-index="' + index + '">×</button>';
            chips.appendChild(chip);
        });
        hidden.value = tags.join(',');
    }

    chips.addEventListener('click', function (e) {
        if (e.target.classList.contains('tag-remove')) {
            var idx = parseInt(e.target.getAttribute('data-index'), 10);
            if (!isNaN(idx)) {
                tags.splice(idx, 1);
                renderChips();
            }
        }
    });

    input.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' || e.keyCode === 13) {
            e.preventDefault();
            var v = input.value.trim();
            if (v.length > 0 && tags.indexOf(v) === -1) {
                tags.push(v);
                renderChips();
            }
            input.value = '';
        }
    });

    renderChips();
});
</script>

<?php require __DIR__ . '/layout_footer.php'; ?>
