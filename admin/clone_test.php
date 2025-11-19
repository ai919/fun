<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';

$pageTitle    = '克隆测试 · DoFun';
$pageHeading  = '克隆一个现有测试';
$pageSubtitle = '复制模板测试的维度、题目、选项和结果，生成一个新的可编辑测试。';
$activeMenu   = 'clone';

$errors   = [];
$success  = null;
$newSlug  = '';
$newId    = null;

$testsStmt = $pdo->query("SELECT id, slug, title FROM tests ORDER BY id ASC");
$tests     = $testsStmt->fetchAll(PDO::FETCH_ASSOC);

$sourceId        = 0;
$slugInput       = '';
$titleInput      = '';
$descriptionInput = '';
$coverInput      = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $sourceId        = (int)($_POST['source_test_id'] ?? 0);
    $slugInput       = trim($_POST['slug'] ?? '');
    $titleInput      = trim($_POST['title'] ?? '');
    $descriptionInput = trim($_POST['description'] ?? '');
    $coverInput      = trim($_POST['cover_image'] ?? '');

    if (!$sourceId) {
        $errors[] = '请选择一个要克隆的模板测试。';
    }
    if ($slugInput === '' || !preg_match('/^[a-z0-9_-]+$/', $slugInput)) {
        $errors[] = '新测试的 slug 不能为空，只能使用小写字母、数字、下划线和短横线。';
    }
    if ($titleInput === '') {
        $errors[] = '新测试的标题不能为空。';
    }

    $srcTest = null;
    if (!$errors) {
        $srcStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
        $srcStmt->execute([$sourceId]);
        $srcTest = $srcStmt->fetch(PDO::FETCH_ASSOC);
        if (!$srcTest) {
            $errors[] = '要克隆的模板测试不存在。';
        }
    }

    if (!$errors) {
        $check = $pdo->prepare('SELECT COUNT(*) FROM tests WHERE slug = ?');
        $check->execute([$slugInput]);
        if ($check->fetchColumn() > 0) {
            $errors[] = '这个 slug 已存在，请换一个（可在后面加数字）。';
        }
    }

    if (!$errors && $srcTest) {
        try {
            $pdo->beginTransaction();

            $descriptionToSave = $descriptionInput !== ''
                ? $descriptionInput
                : ($srcTest['description'] ?? '');
            $coverToSave = $coverInput !== ''
                ? $coverInput
                : ($srcTest['cover_image'] ?? '/assets/images/default.png');

            $insertTest = $pdo->prepare(
                "INSERT INTO tests (slug, title, description, cover_image)
                 VALUES (?, ?, ?, ?)"
            );
            $insertTest->execute([
                $slugInput,
                $titleInput,
                $descriptionToSave,
                $coverToSave,
            ]);
            $newTestId = (int)$pdo->lastInsertId();

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

            $qStmt = $pdo->prepare(
                "SELECT * FROM questions
                 WHERE test_id = ?
                 ORDER BY order_number, id"
            );
            $qStmt->execute([$sourceId]);
            $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

            $questionMap = [];
            if ($questions) {
                $insQ = $pdo->prepare(
                    "INSERT INTO questions (test_id, order_number, content)
                     VALUES (?, ?, ?)"
                );
                foreach ($questions as $question) {
                    $insQ->execute([
                        $newTestId,
                        $question['order_number'],
                        $question['content'],
                    ]);
                    $questionMap[$question['id']] = (int)$pdo->lastInsertId();
                }
            }

            if ($questionMap) {
                $oldIds = array_keys($questionMap);
                $placeholder = implode(',', array_fill(0, count($oldIds), '?'));
                $oStmt = $pdo->prepare(
                    "SELECT * FROM options
                     WHERE question_id IN ($placeholder)
                     ORDER BY question_id, id"
                );
                $oStmt->execute($oldIds);
                $options = $oStmt->fetchAll(PDO::FETCH_ASSOC);

                if ($options) {
                    $insOpt = $pdo->prepare(
                        "INSERT INTO options (question_id, content, dimension_key, score)
                         VALUES (?, ?, ?, ?)"
                    );
                    foreach ($options as $option) {
                        $oldQId = $option['question_id'];
                        if (!isset($questionMap[$oldQId])) {
                            continue;
                        }
                        $insOpt->execute([
                            $questionMap[$oldQId],
                            $option['content'],
                            $option['dimension_key'],
                            $option['score'],
                        ]);
                    }
                }
            }

            $rStmt = $pdo->prepare("SELECT * FROM results WHERE test_id = ?");
            $rStmt->execute([$sourceId]);
            $results = $rStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($results) {
                $insRes = $pdo->prepare(
                    "INSERT INTO results (test_id, dimension_key, range_min, range_max, title, description)
                     VALUES (?, ?, ?, ?, ?, ?)"
                );
                foreach ($results as $result) {
                    $insRes->execute([
                        $newTestId,
                        $result['dimension_key'],
                        $result['range_min'],
                        $result['range_max'],
                        $result['title'],
                        $result['description'],
                    ]);
                }
            }

            $pdo->commit();

            $success = '克隆成功，新测试已经创建。';
            $newSlug = $slugInput;
            $newId   = $newTestId;

            $sourceId = 0;
            $slugInput = '';
            $titleInput = '';
            $descriptionInput = '';
            $coverInput = '';
        } catch (Exception $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            $errors[] = '克隆过程中出错：' . $e->getMessage();
        }
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
    <div class="alert alert-success">
        <div><?= htmlspecialchars($success) ?></div>
        <?php if ($newSlug): ?>
            <div>前台访问：<a href="/<?= htmlspecialchars($newSlug) ?>" target="_blank">/<?= htmlspecialchars($newSlug) ?></a></div>
        <?php endif; ?>
        <?php if ($newId): ?>
            <div>管理题目：<a href="/admin/questions.php?test_id=<?= (int)$newId ?>" target="_blank">/admin/questions.php?test_id=<?= (int)$newId ?></a></div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<p class="hint">
    选择一个已有测试作为模板，它的维度、题目、选项、结果都会被完整复制。<br>
    复制完成后可以前往“题目 &amp; 选项”页面继续调整内容。
</p>

<form method="post" class="admin-form">
    <div class="field">
        <label for="source_test_id">模板测试</label>
        <select id="source_test_id" name="source_test_id" required>
            <option value="">请选择要克隆的测试</option>
            <?php foreach ($tests as $testItem): ?>
                <option value="<?= (int)$testItem['id'] ?>" <?= $sourceId === (int)$testItem['id'] ? 'selected' : '' ?>>
                    [<?= (int)$testItem['id'] ?>] <?= htmlspecialchars($testItem['slug']) ?> · <?= htmlspecialchars($testItem['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
        <div class="field-hint">会复制全部题目、选项、维度与结果。</div>
    </div>

    <div class="field">
        <label for="slug">新测试 slug（必填）</label>
        <input type="text" id="slug" name="slug" value="<?= htmlspecialchars($slugInput) ?>"
               placeholder="例如：love_language / finance_personality">
        <div class="field-hint">仅限小写字母、数字、下划线、短横线。访问路径为 <code>/slug</code>。</div>
    </div>

    <div class="field">
        <label for="title">新测试标题（必填）</label>
        <input type="text" id="title" name="title" value="<?= htmlspecialchars($titleInput) ?>"
               placeholder="例如：你的金钱焦虑等级是多少？">
    </div>

    <div class="field">
        <label for="description">测试简介（可选）</label>
        <textarea id="description" name="description" rows="3"
                  placeholder="不填写则沿用模板测试的简介"><?= htmlspecialchars($descriptionInput) ?></textarea>
    </div>

    <div class="field">
        <label for="cover_image">封面图 URL（可选）</label>
        <input type="text" id="cover_image" name="cover_image" value="<?= htmlspecialchars($coverInput) ?>"
               placeholder="留空则继续使用模板的封面">
        <div class="field-hint">建议使用 4:3 或 16:9 的图片链接。</div>
    </div>

    <div class="form-actions">
        <button type="submit" class="btn btn-primary">克隆测试</button>
    </div>
</form>

<?php require __DIR__ . '/layout_footer.php'; ?>
