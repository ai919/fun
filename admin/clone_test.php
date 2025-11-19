<?php
require __DIR__ . '/auth.php';
require_admin_login();

// admin/clone_test.php
require __DIR__ . '/../lib/db_connect.php';
require __DIR__ . '/layout.php';

$errors  = [];
$success = null;
$newSlug = '';
$newId   = null;

// 先把所有已有测试列出来，供选择模板
$testsStmt = $pdo->query("SELECT id, slug, title FROM tests ORDER BY id ASC");
$tests     = $testsStmt->fetchAll(PDO::FETCH_ASSOC);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sourceId    = (int)($_POST['source_test_id'] ?? 0);
    $slug        = trim($_POST['slug'] ?? '');
    $title       = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $cover       = trim($_POST['cover_image'] ?? '');

    // 校验
    if (!$sourceId) {
        $errors[] = '请选择一个要克隆的模板测试。';
    }
    if ($slug === '' || !preg_match('/^[a-z0-9_-]+$/', $slug)) {
        $errors[] = '新测试的 slug 不能为空，只能使用小写字母、数字、下划线和短横线。';
    }
    if ($title === '') {
        $errors[] = '新测试的标题不能为空。';
    }
    if ($cover === '') {
        $cover = '/assets/images/default.png';
    }

    // 检查源测试是否存在
    $srcTest = null;
    if (!$errors) {
        $srcStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
        $srcStmt->execute([$sourceId]);
        $srcTest = $srcStmt->fetch(PDO::FETCH_ASSOC);
        if (!$srcTest) {
            $errors[] = '要克隆的模板测试不存在。';
        }
    }

    // 检查 slug 唯一
    if (!$errors) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM tests WHERE slug = ?');
        $check->execute([$slug]);
        if ($check->fetchColumn() > 0) {
            $errors[] = '这个 slug 已存在，请换一个（比如后面加数字）。';
        }
    }

    if (!$errors && $srcTest) {
        try {
            $pdo->beginTransaction();

            // 1）插入新 tests
            $insertTest = $pdo->prepare(
                "INSERT INTO tests (slug, title, description, cover_image)
                 VALUES (?, ?, ?, ?)"
            );
            $insertTest->execute([
                $slug,
                $title,
                $description !== '' ? $description : ($srcTest['description'] ?? ''),
                $cover ?: ($srcTest['cover_image'] ?? '/assets/images/default.png'),
            ]);
            $newTestId = (int)$pdo->lastInsertId();

            // 2）克隆 dimensions
            $dimStmt = $pdo->prepare("SELECT * FROM dimensions WHERE test_id = ?");
            $dimStmt->execute([$sourceId]);
            $dims = $dimStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($dims) {
                $insDim = $pdo->prepare(
                    "INSERT INTO dimensions (test_id, key_name, title, description)
                     VALUES (?, ?, ?, ?)"
                );
                foreach ($dims as $d) {
                    $insDim->execute([
                        $newTestId,
                        $d['key_name'],
                        $d['title'],
                        $d['description'],
                    ]);
                }
            }

            // 3）克隆 questions
            $qStmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ? ORDER BY order_number, id");
            $qStmt->execute([$sourceId]);
            $qs = $qStmt->fetchAll(PDO::FETCH_ASSOC);

            $mapOldQToNewQ = [];
            if ($qs) {
                $insQ = $pdo->prepare(
                    "INSERT INTO questions (test_id, order_number, content)
                     VALUES (?, ?, ?)"
                );
                foreach ($qs as $q) {
                    $insQ->execute([
                        $newTestId,
                        $q['order_number'],
                        $q['content'],
                    ]);
                    $newQId = (int)$pdo->lastInsertId();
                    $mapOldQToNewQ[$q['id']] = $newQId;
                }
            }

            // 4）克隆 options
            if ($mapOldQToNewQ) {
                $oldQIds = array_keys($mapOldQToNewQ);
                $place   = implode(',', array_fill(0, count($oldQIds), '?'));

                $oStmt = $pdo->prepare(
                    "SELECT * FROM options WHERE question_id IN ($place) ORDER BY question_id, id"
                );
                $oStmt->execute($oldQIds);
                $ops = $oStmt->fetchAll(PDO::FETCH_ASSOC);

                if ($ops) {
                    $insO = $pdo->prepare(
                        "INSERT INTO options (question_id, content, dimension_key, score)
                         VALUES (?, ?, ?, ?)"
                    );
                    foreach ($ops as $o) {
                        $oldQId = $o['question_id'];
                        if (!isset($mapOldQToNewQ[$oldQId])) {
                            continue;
                        }
                        $insO->execute([
                            $mapOldQToNewQ[$oldQId],
                            $o['content'],
                            $o['dimension_key'],
                            $o['score'],
                        ]);
                    }
                }
            }

            // 5）克隆 results
            $rStmt = $pdo->prepare("SELECT * FROM results WHERE test_id = ?");
            $rStmt->execute([$sourceId]);
            $rs = $rStmt->fetchAll(PDO::FETCH_ASSOC);

            if ($rs) {
                $insR = $pdo->prepare(
                    "INSERT INTO results (test_id, dimension_key, range_min, range_max, title, description)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                foreach ($rs as $r) {
                    $insR->execute([
                        $newTestId,
                        $r['dimension_key'],
                        $r['range_min'],
                        $r['range_max'],
                        $r['title'],
                        $r['description'],
                    ]);
                }
            }

            $pdo->commit();

            $success = '克隆成功！新测试已创建。';
            $newSlug = $slug;
            $newId   = $newTestId;

        } catch (Exception $e) {
            $pdo->rollBack();
            $errors[] = '克隆过程中出错：' . $e->getMessage();
        }
    }
}

admin_header('克隆测试 · fun_quiz');
?>
<style>
    .errors, .success {
        padding: 10px;
        border-radius: 6px;
        margin-bottom: 12px;
    }
    .errors {
        background: #ffecec;
        border: 1px solid #ffb4b4;
    }
    .success {
        background: #e7f9ec;
        border: 1px solid #9ad5aa;
    }
    .hint { font-size: 13px; color: #666; }
    .field { margin-bottom: 12px; }
    .field label { display: block; margin-bottom: 4px; }
    .field input[type="text"],
    .field textarea,
    .field select {
        width: 100%;
        padding: 6px 8px;
    }
</style>

<h1>克隆一个现有测试</h1>
<p class="hint">
    选择一个已有测试作为模板，它的维度、题目、选项、结果都会被完整复制。<br>
    你只需要改 slug / 标题 / 简介，再去 <code>questions & options</code> 页面调整内容即可。
</p>

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
        <p><?= htmlspecialchars($success) ?></p>
        <?php if ($newSlug): ?>
            <p>
                👉 前台访问路径：
                <a href="/<?= htmlspecialchars($newSlug) ?>" target="_blank">/<?= htmlspecialchars($newSlug) ?></a>
            </p>
        <?php endif; ?>
        <?php if ($newId): ?>
            <p>
                👉 后台管理题目：
                <a href="/admin/questions.php?test_id=<?= (int)$newId ?>" target="_blank">
                    /admin/questions.php?test_id=<?= (int)$newId ?>
                </a>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<form method="post">
    <div class="field">
        <label for="source_test_id">选择一个测试作为模板（来源）</label>
        <select name="source_test_id" id="source_test_id">
            <option value="">请选择...</option>
            <?php foreach ($tests as $t): ?>
                <option value="<?= (int)$t['id'] ?>" <?= isset($_POST['source_test_id']) && (int)$_POST['source_test_id'] === (int)$t['id'] ? 'selected' : '' ?>>
                    [<?= (int)$t['id'] ?>] <?= htmlspecialchars($t['slug']) ?> — <?= htmlspecialchars($t['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>

    <div class="field">
        <label for="slug">新测试 slug（必填）</label>
        <input type="text" id="slug" name="slug"
               placeholder="例如：money_anxiety / attachment_style"
               value="<?= htmlspecialchars($_POST['slug'] ?? '') ?>">
        <div class="hint">只能使用小写字母、数字、下划线、短横线。访问路径为 <code>/slug</code>。</div>
    </div>

    <div class="field">
        <label for="title">新测试标题（必填）</label>
        <input type="text" id="title" name="title"
               placeholder="例如：你的金钱焦虑体质有多严重？"
               value="<?= htmlspecialchars($_POST['title'] ?? '') ?>">
    </div>

    <div class="field">
        <label for="description">新测试简介（可选）</label>
        <textarea id="description" name="description" rows="3"
                  placeholder="不填则沿用模板的简介。"><?= htmlspecialchars($_POST['description'] ?? '') ?></textarea>
    </div>

    <div class="field">
        <label for="cover_image">封面图片地址（可选）</label>
        <input type="text" id="cover_image" name="cover_image"
               placeholder="留空则默认 /assets/images/default.png 或模板封面"
               value="<?= htmlspecialchars($_POST['cover_image'] ?? '') ?>">
    </div>

    <button type="submit">克隆测试</button>
</form>

<?php
admin_footer();
