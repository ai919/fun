<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';

$testId = null;
if (isset($_GET['test_id'])) {
    $testId = (int)$_GET['test_id'];
} elseif (isset($_GET['slug'])) {
    $slug = trim($_GET['slug']);
    $stmt = $pdo->prepare("SELECT id FROM tests WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $testId = (int)$row['id'];
    }
}

if (!$testId) {
    die('缺少 test_id 或 slug 参数，例如：/admin/questions.php?test_id=1');
}

$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
$testStmt->execute([$testId]);
$test = $testStmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    die('测验不存在');
}

$pageTitle    = '题目管理 · ' . ($test['title'] ?? '');
$pageHeading  = '题目 & 选项';
$pageSubtitle = '管理此测验的题目、选项、分数与维度，支持卡片化编辑。';
$activeMenu   = 'tests';

$errors  = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_question') {
        $content = trim($_POST['content'] ?? '');
        $order   = (int)($_POST['order_number'] ?? 0);

        if ($content === '') {
            $errors[] = '题目内容不能为空。';
        }

        if (!$order) {
            $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(order_number), 0) FROM questions WHERE test_id = ?");
            $maxStmt->execute([$testId]);
            $order = (int)$maxStmt->fetchColumn() + 1;
        }

        if (!$errors) {
            $insQ = $pdo->prepare(
                "INSERT INTO questions (test_id, order_number, content)
                 VALUES (?, ?, ?)"
            );
            $insQ->execute([$testId, $order, $content]);
            $success = '题目已添加。';
        }
    }

    if ($action === 'add_option') {
        $questionId   = (int)($_POST['question_id'] ?? 0);
        $optContent   = trim($_POST['content'] ?? '');
        $dimensionKey = trim($_POST['dimension_key'] ?? '');
        $score        = (int)($_POST['score'] ?? 0);

        if (!$questionId) {
            $errors[] = '缺少题目 ID。';
        }
        if ($optContent === '') {
            $errors[] = '选项内容不能为空。';
        }
        if ($dimensionKey === '') {
            $dimensionKey = null;
        }

        if (!$errors) {
            $insO = $pdo->prepare(
                "INSERT INTO options (question_id, content, dimension_key, score)
                 VALUES (?, ?, ?, ?)"
            );
            $insO->execute([$questionId, $optContent, $dimensionKey, $score]);
            $success = '选项已添加。';
        }
    }

    if ($action === 'edit_question') {
        $qid     = (int)($_POST['question_id'] ?? 0);
        $content = trim($_POST['content'] ?? '');
        $order   = (int)($_POST['order_number'] ?? 0);

        if (!$qid) {
            $errors[] = '缺少题目 ID。';
        }
        if ($content === '') {
            $errors[] = '题目内容不能为空。';
        }

        if (!$errors) {
            if (!$order) {
                $oStmt = $pdo->prepare("SELECT order_number FROM questions WHERE id = ? AND test_id = ?");
                $oStmt->execute([$qid, $testId]);
                $order = (int)$oStmt->fetchColumn();
                if (!$order) {
                    $order = 1;
                }
            }

            $upd = $pdo->prepare(
                "UPDATE questions
                 SET content = ?, order_number = ?
                 WHERE id = ? AND test_id = ?"
            );
            $upd->execute([$content, $order, $qid, $testId]);
            $success = '题目已更新。';
        }
    }

    if ($action === 'delete_question') {
        $qid = (int)($_POST['question_id'] ?? 0);
        if (!$qid) {
            $errors[] = '缺少题目 ID。';
        }

        if (!$errors) {
            $del = $pdo->prepare("DELETE FROM questions WHERE id = ? AND test_id = ?");
            $del->execute([$qid, $testId]);
            $success = '题目已删除（相关选项也一起删除）。';
        }
    }

    if ($action === 'edit_option') {
        $oid          = (int)($_POST['option_id'] ?? 0);
        $optContent   = trim($_POST['content'] ?? '');
        $dimensionKey = trim($_POST['dimension_key'] ?? '');
        $score        = (int)($_POST['score'] ?? 0);

        if (!$oid) {
            $errors[] = '缺少选项 ID。';
        }
        if ($optContent === '') {
            $errors[] = '选项内容不能为空。';
        }
        if ($dimensionKey === '') {
            $dimensionKey = null;
        }

        if (!$errors) {
            $upd = $pdo->prepare(
                "UPDATE options
                 SET content = ?, dimension_key = ?, score = ?
                 WHERE id = ?
                   AND question_id IN (SELECT id FROM questions WHERE test_id = ?)"
            );
            $upd->execute([$optContent, $dimensionKey, $score, $oid, $testId]);
            $success = '选项已更新。';
        }
    }

    if ($action === 'delete_option') {
        $oid = (int)($_POST['option_id'] ?? 0);
        if (!$oid) {
            $errors[] = '缺少选项 ID。';
        }

        if (!$errors) {
            $del = $pdo->prepare(
                "DELETE FROM options
                 WHERE id = ?
                   AND question_id IN (SELECT id FROM questions WHERE test_id = ?)"
            );
            $del->execute([$oid, $testId]);
            $success = '选项已删除。';
        }
    }
}

$qStmt = $pdo->prepare(
    "SELECT * FROM questions
     WHERE test_id = ?
     ORDER BY order_number ASC, id ASC"
);
$qStmt->execute([$testId]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

$optionsByQuestion = [];
if ($questions) {
    $qIds = array_column($questions, 'id');
    $place = implode(',', array_fill(0, count($qIds), '?'));
    $oStmt = $pdo->prepare(
        "SELECT * FROM options
         WHERE question_id IN ($place)
         ORDER BY question_id ASC, id ASC"
    );
    $oStmt->execute($qIds);
    while ($o = $oStmt->fetch(PDO::FETCH_ASSOC)) {
        $qid = $o['question_id'];
        if (!isset($optionsByQuestion[$qid])) {
            $optionsByQuestion[$qid] = [];
        }
        $optionsByQuestion[$qid][] = $o;
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

<?php if (!$questions): ?>
    <p class="hint">这个测验目前还没有题目，可以在下面添加第一道题。</p>
<?php else: ?>
    <?php foreach ($questions as $q): ?>
        <?php $qid = (int)$q['id']; $options = $optionsByQuestion[$qid] ?? []; ?>
        <article class="question-card">
            <div class="question-card-header">
                <div>
                    <div class="question-card-title">
                        Q<?= (int)$q['order_number'] ?>.
                        <span class="question-card-meta">ID: <?= $qid ?></span>
                    </div>
                    <div><?= nl2br(htmlspecialchars($q['content'])) ?></div>
                </div>
                <div class="question-card-actions">
                    <form method="post" class="inline-form">
                        <input type="hidden" name="action" value="delete_question">
                        <input type="hidden" name="question_id" value="<?= $qid ?>">
                        <button type="submit" class="btn btn-danger btn-xs"
                                onclick="return confirm('确定删除这道题及其所有选项吗？');">
                            删除
                        </button>
                    </form>
                </div>
            </div>

            <div class="question-card-body">
                <?php if ($options): ?>
                    <table class="option-table">
                        <thead>
                        <tr>
                            <th>选项内容</th>
                            <th style="width:70px;">分数</th>
                            <th style="width:120px;">维度键</th>
                            <th style="width:220px;">操作</th>
                        </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($options as $op): ?>
                            <tr>
                                <td><?= htmlspecialchars($op['content']) ?></td>
                                <td><?= (int)$op['score'] ?></td>
                                <td><?= htmlspecialchars($op['dimension_key'] ?? '-') ?></td>
                                <td class="option-actions">
                                    <details>
                                        <summary>编辑</summary>
                                        <form method="post" class="option-edit-form">
                                            <input type="hidden" name="action" value="edit_option">
                                            <input type="hidden" name="option_id" value="<?= (int)$op['id'] ?>">
                                            <input type="text" name="content" value="<?= htmlspecialchars($op['content']) ?>" required>
                                            <input type="text" name="dimension_key" value="<?= htmlspecialchars($op['dimension_key'] ?? '') ?>" placeholder="维度键">
                                            <input type="number" name="score" value="<?= (int)$op['score'] ?>" required>
                                            <button type="submit" class="btn btn-primary btn-xs">保存</button>
                                        </form>
                                    </details>
                                    <form method="post" onsubmit="return confirm('确定删除这个选项吗？');">
                                        <input type="hidden" name="action" value="delete_option">
                                        <input type="hidden" name="option_id" value="<?= (int)$op['id'] ?>">
                                        <button type="submit" class="btn btn-danger btn-xs">删除</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php else: ?>
                    <p class="hint">这个题暂时还没有选项。</p>
                <?php endif; ?>
            </div>

            <div class="question-edit-form" id="edit-question-<?= $qid ?>">
                <form method="post" class="inline-form">
                    <input type="hidden" name="action" value="edit_question">
                    <input type="hidden" name="question_id" value="<?= $qid ?>">
                    <label>
                        排序号：
                        <input type="number" name="order_number" value="<?= (int)$q['order_number'] ?>" style="width:80px;">
                    </label>
                    <label>
                        题目内容：
                        <textarea name="content" rows="2"><?= htmlspecialchars($q['content']) ?></textarea>
                    </label>
                    <button type="submit" class="btn btn-primary btn-xs">保存题目</button>
                </form>
            </div>

            <div class="add-option-area">
                <form method="post" class="inline-form">
                    <input type="hidden" name="action" value="add_option">
                    <input type="hidden" name="question_id" value="<?= $qid ?>">
                    <input type="text" name="content" placeholder="选项内容" required>
                    <input type="text" name="dimension_key" placeholder="维度键">
                    <input type="number" name="score" value="1" style="width:70px;">
                    <button type="submit" class="btn btn-success btn-xs">+ 新增选项</button>
                </form>
            </div>
        </article>
    <?php endforeach; ?>
<?php endif; ?>

<h2>新增题目</h2>
<form method="post" class="new-q-form">
    <input type="hidden" name="action" value="add_question">
    <label>
        排序号（可选）：
        <input type="number" name="order_number" placeholder="留空则自动排到最后">
    </label>
    <label>
        题目内容（必填）：
        <textarea name="content" rows="3" required></textarea>
    </label>
    <button type="submit" class="btn btn-primary">添加题目</button>
</form>

<?php require __DIR__ . '/layout_footer.php'; ?>
