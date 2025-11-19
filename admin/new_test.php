<?php
require __DIR__ . '/auth.php';
require_admin_login();

// admin/new_test.php
require __DIR__ . '/../lib/db_connect.php';
require __DIR__ . '/layout.php';

$errors  = [];
$success = null;
$newSlug = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $slug        = trim($_POST['slug'] ?? '');
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cover       = trim($_POST['cover_image'] ?? '');

    // 基础校验
    if ($slug === '' || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
        $errors[] = 'Slug 只能使用小写字母、数字、下划线、短横线，并且不能为空。';
    }

    if ($title === '') {
        $errors[] = '测试标题不能为空。';
    }

    if ($cover === '') {
        $cover = '/assets/images/default.png';
    }

    // 检查 slug 是否已存在
    if (!$errors) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM tests WHERE slug = ?');
        $check->execute([$slug]);
        if ($check->fetchColumn() > 0) {
            $errors[] = '这个 slug 已经被占用了，请换一个（比如在后面加数字）。';
        }
    }

    // 写入数据库
    if (!$errors) {
        $stmt = $pdo->prepare(
            'INSERT INTO tests (slug, title, description, cover_image)
             VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([$slug, $title, $description, $cover]);

        $success = '测试已创建成功！现在可以访问 /' . htmlspecialchars($slug) . '（注意：题目和结果需要你在数据库里继续添加）。';
        $newSlug = $slug;

        // 清空表单（保留一份 slug 用来显示链接）
        $slug        = '';
        $title       = '';
        $description = '';
        $cover       = '';
    }
}

admin_header('新增测试 · fun_quiz');
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

<h1>新增一个测试（只创建 tests 记录）</h1>
<p class="hint">
    这个页面只负责把测试的基本信息写入 <code>tests</code> 表。<br>
    创建后会自动支持 <code>/slug</code> 访问，但题目、选项、结果需要你后面在数据库里继续添加（可以照 <code>love / animal / work</code> 的 SQL 模板复制改）。
</p>

<?php if ($errors): ?>
    <div class="errors">
        <strong>提交有一些问题：</strong>
        <ul>
            <?php foreach ($errors as $e): ?>
                <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="success">
        <p><?= $success ?></p>
        <?php if ($newSlug): ?>
            <p>
                👉 现在可以先在浏览器里打开：
                <a href="/<?= htmlspecialchars($newSlug) ?>" target="_blank">/<?= htmlspecialchars($newSlug) ?></a>
            </p>
            <p class="hint">
                接下来，你可以在 phpMyAdmin 里：<br>
                1）在 <code>questions</code> 表里为它添加题目（test_id = 新测试的 id）<br>
                2）在 <code>options</code> 表里添加选项，并设置 <code>dimension_key</code> + <code>score</code><br>
                3）在 <code>dimensions</code> 和 <code>results</code> 里为它设计维度和结果区间
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="post">
    <div class="field">
        <label for="slug">测试路径 slug（必填）</label>
        <input type="text" id="slug" name="slug"
               placeholder="例如：love / animal / work / money_anxiety"
               value="<?= htmlspecialchars($slug ?? '') ?>">
        <div class="hint">只允许小写字母、数字、下划线、短横线；用户访问路径将是 <code>/slug</code>。</div>
    </div>

    <div class="field">
        <label for="title">测试标题（必填）</label>
        <input type="text" id="title" name="title"
               placeholder="例如：你的存钱焦虑等级是多少？"
               value="<?= htmlspecialchars($title ?? '') ?>">
    </div>

    <div class="field">
        <label for="description">测试简介（可选，但建议填写）</label>
        <textarea id="description" name="description" rows="3"
                  placeholder="一句话介绍这个测试的用途、风格、适合谁做。"><?= htmlspecialchars($description ?? '') ?></textarea>
    </div>

    <div class="field">
        <label for="cover_image">封面图片地址（可选）</label>
        <input type="text" id="cover_image" name="cover_image"
               placeholder="/assets/images/default.png 或完整图片 URL"
               value="<?= htmlspecialchars($cover ?? '') ?>">
        <div class="hint">留空则使用默认封面 <code>/assets/images/default.png</code>。</div>
    </div>

    <button type="submit">创建测试</button>
</form>

<?php
admin_footer();
