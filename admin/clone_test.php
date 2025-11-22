<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/Constants.php';

$pageTitle    = '克隆测验 - DoFun心理实验空间';
($pageHeading = '克隆一个测验') || true;
$pageSubtitle = '复制题目、选项、结果配置，生成一个新的测验草稿。';
$activeMenu   = 'clone';

$errors  = [];
$success = null;
$newSlug = '';

$testsStmt = $pdo->query("SELECT id, slug, title FROM tests ORDER BY id ASC");
$tests     = $testsStmt->fetchAll(PDO::FETCH_ASSOC);

$sourceId          = 0;
$slugInput         = '';
$titleInput        = '';
$descriptionInput  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        $errors[] = 'CSRF token 验证失败，请刷新页面后重试';
    } else {
    $sourceId         = (int)($_POST['source_test_id'] ?? 0);
    $slugInput        = trim($_POST['slug'] ?? '');
    $titleInput       = trim($_POST['title'] ?? '');
    $descriptionInput = trim($_POST['description'] ?? '');

    if (!$sourceId) {
        $errors[] = '请选择要克隆的测验。';
    }
    if ($slugInput === '' || !preg_match('/^[a-z0-9_-]+$/', $slugInput)) {
        $errors[] = '新测验的 slug 只能包含小写字母、数字、下划线、短横线。';
    }
    if ($titleInput === '') {
        $errors[] = '新测验的标题不能为空。';
    }

    $srcTest = null;
    if (!$errors) {
        $srcStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
        $srcStmt->execute([$sourceId]);
        $srcTest = $srcStmt->fetch(PDO::FETCH_ASSOC);
        if (!$srcTest) {
            $errors[] = '要克隆的测验不存在。';
        }
    }

    if (!$errors) {
        $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM tests WHERE slug = ?");
        $checkStmt->execute([$slugInput]);
        if ($checkStmt->fetchColumn() > 0) {
            $errors[] = '该 slug 已被占用，请换一个。';
        }
    }

    if (!$errors && $srcTest) {
        try {
            $pdo->beginTransaction();

            $insertTest = $pdo->prepare(
                "INSERT INTO tests (slug, title, subtitle, description, title_color, tags, status, sort_order, scoring_mode, scoring_config)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $insertTest->execute([
                $slugInput,
                $titleInput,
                $srcTest['subtitle'] ?? '',
                $descriptionInput !== '' ? $descriptionInput : ($srcTest['description'] ?? ''),
                $srcTest['title_color'] ?? '#4f46e5',
                $srcTest['tags'] ?? null,
                Constants::TEST_STATUS_DRAFT,
                (int)$srcTest['sort_order'],
                $srcTest['scoring_mode'] ?? Constants::SCORING_MODE_SIMPLE,
                $srcTest['scoring_config'] ?? null,
            ]);
            $newTestId = (int)$pdo->lastInsertId();

            $qStmt = $pdo->prepare("SELECT * FROM questions WHERE test_id = ?");
            $qStmt->execute([$sourceId]);
            $questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);
            $questionIdMap = [];
            if ($questions) {
                $insQ = $pdo->prepare(
                    "INSERT INTO questions (test_id, sort_order, question_text)
                     VALUES (?, ?, ?)"
                );
                foreach ($questions as $question) {
                    $sortOrder = $question['sort_order'] ?? ($question['order_number'] ?? null);
                    $insQ->execute([$newTestId, $sortOrder, $question['question_text'] ?? '']);
                    $questionIdMap[$question['id']] = (int)$pdo->lastInsertId();
                }
            }

            if ($questionIdMap) {
                // 使用占位符防止SQL注入
                $sourceQuestionIds = array_keys($questionIdMap);
                $placeholders = implode(',', array_fill(0, count($sourceQuestionIds), '?'));
                $optStmt = $pdo->prepare("SELECT * FROM question_options WHERE question_id IN ($placeholders)");
                $optStmt->execute($sourceQuestionIds);
                $options = $optStmt->fetchAll(PDO::FETCH_ASSOC);
                if ($options) {
                    $insOpt = $pdo->prepare(
                        "INSERT INTO question_options (question_id, option_text, map_result_code, score_value)
                         VALUES (:qid, :text, :result_code, :score)"
                    );
                    foreach ($options as $option) {
                        $insOpt->execute([
                            ':qid'         => $questionIdMap[$option['question_id']],
                            ':text'        => $option['option_text'] ?? '',
                            ':result_code' => $option['map_result_code'] ?? ($option['dimension_key'] ?? null),
                            ':score'       => $option['score_value'] ?? ($option['score'] ?? 0),
                        ]);
                    }
                }
            }

            $resStmt = $pdo->prepare("SELECT * FROM results WHERE test_id = ?");
            $resStmt->execute([$sourceId]);
            $results = $resStmt->fetchAll(PDO::FETCH_ASSOC);
            if ($results) {
                $insRes = $pdo->prepare(
                    "INSERT INTO results (test_id, code, title, description, image_url, min_score, max_score)
                     VALUES (?, ?, ?, ?, ?, ?, ?)"
                );
                foreach ($results as $res) {
                    $insRes->execute([
                        $newTestId,
                        $res['code'],
                        $res['title'],
                        $res['description'],
                        $res['image_url'],
                        $res['min_score'],
                        $res['max_score'],
                    ]);
                }
            }

            $pdo->commit();
            $success = '测验克隆完成，可以进入编辑页面继续完善。';
            $newSlug = $slugInput;
        } catch (Throwable $e) {
            $pdo->rollBack();
            $errors[] = '克隆过程中出现错误：' . $e->getMessage();
        }
    }
    }
}

require __DIR__ . '/layout.php';
?>

<div class="section-card">
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $err): ?>
                <div><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success">
            <?= htmlspecialchars($success) ?>
            <?php if ($newSlug): ?>
                <a href="/admin/test_edit.php?slug=<?= urlencode($newSlug) ?>" class="btn btn-mini" style="margin-left:8px;">立即编辑</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>

    <form method="post" class="form-grid">
        <?php echo CSRF::getTokenField(); ?>
        <label>
            <span>选择要克隆的测验</span>
            <select name="source_test_id" required>
                <option value="">请选择</option>
                <?php foreach ($tests as $row): ?>
                    <option value="<?= (int)$row['id'] ?>" <?= $sourceId === (int)$row['id'] ? 'selected' : '' ?>>
                        #<?= (int)$row['id'] ?> - <?= htmlspecialchars($row['title']) ?> (<?= htmlspecialchars($row['slug']) ?>)
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>
            <span>新测验 slug</span>
            <input type="text" name="slug" value="<?= htmlspecialchars($slugInput) ?>" required placeholder="例如 love-2024">
        </label>
        <label>
            <span>新测验标题</span>
            <input type="text" name="title" value="<?= htmlspecialchars($titleInput) ?>" required>
        </label>
        <label>
            <span>测验描述（可选，优先使用输入）</span>
            <textarea name="description" rows="3"><?= htmlspecialchars($descriptionInput) ?></textarea>
        </label>
        <button type="submit" class="btn btn-primary">开始克隆</button>
    </form>
</div>

<?php require __DIR__ . '/layout_footer.php'; ?>
