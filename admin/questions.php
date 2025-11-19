<?php
// admin/questions.php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';
require __DIR__ . '/layout.php';

$errors  = [];
$success = null;

// 获取 test_id（优先用 ?test_id=，也支持 ?slug=）
$testId = null;
if (isset($_GET['test_id'])) {
    $testId = (int)$_GET['test_id'];
} elseif (isset($_GET['slug'])) {
    $slug = trim($_GET['slug']);
    $stmt = $pdo->prepare("SELECT id FROM tests WHERE slug = ? LIMIT 1");
    $stmt->execute([$slug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $testId = (int)$row['id'];
    }
}

if (!$testId) {
    die('缺少 test_id 或 slug 参数，例如：/admin/questions.php?test_id=1');
}

// 获取测试信息
$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
$testStmt->execute([$testId]);
$test = $testStmt->fetch(PDO::FETCH_ASSOC);

if (!$test) {
    die('测试不存在');
}

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // 新增题目
    if ($action === 'add_question') {
        $content = trim($_POST['content'] ?? '');
        $order   = (int)($_POST['order_number'] ?? 0);

        if ($content === '') {
            $errors[] = '题目内容不能为空。';
        }

        if (!$order) {
            // 自动给一个最大的 order_number + 1
            $maxStmt = $pdo->prepare("SELECT COALESCE(MAX(order_number), 0) FROM questions WHERE test_id = ?");
            $maxStmt->execute([$testId]);
            $maxOrder = (int)$maxStmt->fetchColumn();
            $order    = $maxOrder + 1;
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

    // 新增选项
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

    // 编辑题目
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
                // 不填排序号则沿用原来的排序
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

    // 删除题目（连带删除选项，由外键 CASCADE 处理）
    if ($action === 'delete_question') {
        $qid = (int)($_POST['question_id'] ?? 0);
        if (!$qid) {
            $errors[] = '缺少题目 ID。';
        }

        if (!$errors) {
            $del = $pdo->prepare("DELETE FROM questions WHERE id = ? AND test_id = ?");
            $del->execute([$qid, $testId]);
            $success = '题目已删除（相关选项也一并删除）。';
        }
    }

    // 编辑选项
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

        if (!$errors) {
            if ($dimensionKey === '') {
                $dimensionKey = null;
            }

            // 确保选项属于当前测试（通过 question_id -> test_id）
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

    // 删除选项
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

// 取出该测试的所有题目
$qStmt = $pdo->prepare(
    "SELECT * FROM questions 
     WHERE test_id = ?
     ORDER BY order_number ASC, id ASC"
);
$qStmt->execute([$testId]);
$questions = $qStmt->fetchAll(PDO::FETCH_ASSOC);

// 一次性取出这些题目的所有选项，按 question_id 分组
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

admin_header('管理题目 & 选项 · ' . ($test['title'] ?? ''));
?>
<style>
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
    .hint { font-size: 13px; color: #666; }
    .question-card {
        background:#fff;
        border-radius:12px;
        border:1px solid #e5e7eb;
        padding:16px 18px 14px;
        margin-bottom:14px;
        box-shadow:0 6px 20px rgba(15,23,42,0.08);
    }
    .question-card-header {
        display:flex;
        justify-content:space-between;
        align-items:flex-start;
        gap:12px;
        margin-bottom:8px;
    }
    .question-card-title {
        font-weight:600;
        font-size:15px;
        color:#111827;
    }
    .question-card-actions {
        display:flex;
        gap:6px;
        flex-wrap:wrap;
    }
    .btn-mini {
        display:inline-flex;
        align-items:center;
        gap:4px;
        padding:4px 10px;
        font-size:12px;
        border-radius:6px;
        border:1px solid #d1d5db;
        background:#fff;
        color:#374151;
        text-decoration:none;
        cursor:pointer;
    }
    .btn-mini:hover {
        border-color:#2563eb;
        color:#2563eb;
    }
    .danger-btn {
        background:#ef4444;
        color:#fff;
        border:none;
        border-radius:6px;
        padding:4px 10px;
        cursor:pointer;
    }
    .option-table {
        width:100%;
        border-collapse:collapse;
        font-size:13px;
        margin-bottom:10px;
    }
    .option-table th,
    .option-table td {
        padding:6px 8px;
        border-bottom:1px solid #f3f4f6;
        vertical-align:top;
    }
    .option-actions {
        width:200px;
    }
    .option-actions form {
        margin:2px 0;
    }
    .option-edit-form input[type="text"],
    .option-edit-form input[type="number"] {
        width:100%;
        margin-bottom:4px;
        padding:3px 6px;
        font-size:12px;
    }
    .question-edit-form textarea {
        width:100%;
        min-height:60px;
        padding:6px 8px;
        box-sizing:border-box;
    }
    .question-card-footer {
        margin-top:6px;
        display:flex;
        justify-content:flex-end;
        gap:8px;
    }
    .add-option-btn {
        background:#10b981;
        color:#fff;
        border:none;
        border-radius:6px;
        padding:6px 12px;
        cursor:pointer;
    }
    .inline-form {
        font-size:13px;
        margin-top:6px;
    }
    .inline-form input[type="text"],
    .inline-form input[type="number"] {
        padding:3px 6px;
        font-size:13px;
    }
    .inline-form button {
        font-size:12px;
        padding:3px 8px;
    }
    .new-q-form textarea {
        width: 100%;
        padding: 6px 8px;
        box-sizing: border-box;
    }
    .new-q-form input[type="number"] {
        width: 80px;
    }
</style>

<h1>管理题目 &amp; 选项：<?= htmlspecialchars($test['title'] ?? '') ?></h1>
<p class="hint">
    当前测试 slug：<code><?= htmlspecialchars($test['slug'] ?? '') ?></code>，
    访问路径：<code>/<?= htmlspecialchars($test['slug'] ?? '') ?></code>
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
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<h2>已有题目</h2>

<?php if (!$questions): ?>
    <p class="hint">这个测试目前还没有题目，可以在下面添加第一道题。</p>
<?php else: ?>
    <?php foreach ($questions as $idx => $q): ?>
        <?php $qid = (int)$q['id']; $options = $optionsByQuestion[$qid] ?? []; ?>
        <div class="question-card" id="question-<?= $qid ?>">
            <div class="question-card-header">
                <div>
                    <div class="question-card-title">Q<?= (int)$q['order_number'] ?>.</div>
                    <div><?= nl2br(htmlspecialchars($q['content'])) ?></div>
                </div>
                <div class="question-card-actions">
                    <a class="btn-mini" href="#edit-question-<?= $qid ?>">编辑问题</a>
                    <form method="post">
                        <input type="hidden" name="action" value="delete_question">
                        <input type="hidden" name="question_id" value="<?= $qid ?>">
                        <button type="submit" class="danger-btn" onclick="return confirm('确定删除这道题及其所有选项吗？');">删除问题</button>
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
                            <th style="width:100px;">维度</th>
                            <th style="width:200px;">操作</th>
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
                                            <button type="submit" class="btn-mini">保存</button>
                                        </form>
                                    </details>
                                    <form method="post" onsubmit="return confirm('确定删除这个选项吗？');">
                                        <input type="hidden" name="action" value="delete_option">
                                        <input type="hidden" name="option_id" value="<?= (int)$op['id'] ?>">
                                        <button type="submit" class="danger-btn">删除</button>
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

            <div id="edit-question-<?= $qid ?>" class="question-edit-form" style="margin-top:8px;">
                <form method="post" class="inline-form">
                    <input type="hidden" name="action" value="edit_question">
                    <input type="hidden" name="question_id" value="<?= $qid ?>">
                    <label>排序号：<input type="number" name="order_number" value="<?= (int)$q['order_number'] ?>" style="width:80px;"></label>
                    <label>题目内容：<textarea name="content" rows="2" style="width:100%;"><?= htmlspecialchars($q['content']) ?></textarea></label>
                    <button type="submit" class="btn-mini">保存题目</button>
                </form>
            </div>

            <div class="question-card-footer">
                <form method="post" class="inline-form">
                    <input type="hidden" name="action" value="add_option">
                    <input type="hidden" name="question_id" value="<?= $qid ?>">
                    <input type="text" name="content" placeholder="选项内容" required>
                    <input type="text" name="dimension_key" placeholder="维度键">
                    <input type="number" name="score" value="1" style="width:70px;">
                    <button type="submit" class="add-option-btn">+ 新增选项</button>
                </form>
            </div>
        </div>
    <?php endforeach; ?>
<?php endif; ?>

<h2>新增题目</h2>
<form method="post" class="new-q-form">
    <input type="hidden" name="action" value="add_question">
    <p>
        <label>
            排序号（可选）：
            <input type="number" name="order_number" placeholder="留空则自动排到最后">
        </label>
    </p>
    <p>
        <label>
            题目内容（必填）：<br>
            <textarea name="content" rows="3" required></textarea>
        </label>
    </p>
    <button type="submit">添加题目</button>
</form>

<?php
admin_footer();
