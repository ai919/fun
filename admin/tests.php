<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';
require __DIR__ . '/layout.php';

$errors  = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if ($action === 'delete_test') {
        $testId = (int)($_POST['test_id'] ?? 0);
        if (!$testId) {
            $errors[] = '缺少测试 ID。';
        } else {
            try {
                $del = $pdo->prepare('DELETE FROM tests WHERE id = ?');
                $del->execute([$testId]);
                $success = '测试已删除（包含题目 / 选项 / 结果）。';
            } catch (Exception $e) {
                $errors[] = '删除失败：' . $e->getMessage();
            }
        }
    }
}

$keyword = trim($_GET['q'] ?? '');
if ($keyword !== '') {
    $like = '%' . $keyword . '%';
    $stmt = $pdo->prepare(
        "SELECT t.*,
                (SELECT COUNT(*) FROM questions q WHERE q.test_id = t.id) AS question_count,
                (SELECT COUNT(*) FROM results r WHERE r.test_id = t.id)   AS result_count
         FROM tests t
         WHERE t.slug LIKE ? OR t.title LIKE ?
         ORDER BY t.id DESC"
    );
    $stmt->execute([$like, $like]);
} else {
    $stmt = $pdo->query(
        "SELECT t.*,
                (SELECT COUNT(*) FROM questions q WHERE q.test_id = t.id) AS question_count,
                (SELECT COUNT(*) FROM results r WHERE r.test_id = t.id)   AS result_count
         FROM tests t
         ORDER BY t.id DESC"
    );
}
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

admin_header('测试列表 · DoFun');
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
    table {
        width: 100%;
        border-collapse: collapse;
        font-size: 14px;
        background: #fff;
        border-radius: 8px;
        overflow: hidden;
    }
    th, td {
        border-bottom: 1px solid #eee;
        padding: 8px 10px;
        vertical-align: top;
    }
    th {
        text-align: left;
        background: #f9fafb;
        font-weight: 600;
    }
    tr:last-child td {
        border-bottom: none;
    }
    tr:hover {
        background: #f5f5f5;
    }
    .actions {
        display: flex;
        flex-wrap: wrap;
        gap: 6px;
        align-items: flex-start;
    }
    .btn-mini {
        display: inline-flex;
        align-items: center;
        padding: 2px 8px;
        font-size: 12px;
        border-radius: 4px;
        border: 1px solid #d1d5db;
        background: #fff;
        text-decoration: none;
        cursor: pointer;
    }
    .btn-mini:hover {
        border-color: #2563eb;
        color: #2563eb;
    }
    .danger-btn {
        background: #ef4444;
        color: #fff;
        border: none;
        border-radius: 4px;
        padding: 2px 8px;
        font-size: 12px;
        cursor: pointer;
    }
    .actions-menu details {
        display: inline-block;
    }
    .actions-menu summary {
        list-style: none;
        cursor: pointer;
        padding: 2px 8px;
        border: 1px solid #d1d5db;
        border-radius: 4px;
        font-size: 12px;
        background: #fff;
    }
    .actions-menu summary::-webkit-details-marker { display: none; }
    .actions-menu details[open] summary {
        border-color: #2563eb;
        color: #2563eb;
    }
    .actions-menu-body {
        position: absolute;
        right: 0;
        top: 28px;
        min-width: 180px;
        background: #fff;
        border: 1px solid #e5e7eb;
        border-radius: 6px;
        padding: 10px;
        box-shadow: 0 8px 24px rgba(15,23,42,0.15);
        display: flex;
        flex-direction: column;
        gap: 6px;
    }
    details:not([open]) .actions-menu-body {
        display: none;
    }
    .actions-menu {
        position: relative;
    }
</style>

<h1>测试列表</h1>
<p class="hint">
    在这里集中管理所有测试：查看、编辑题目、查看统计、克隆或删除等。
</p>

<form method="get" style="margin-bottom:12px;">
    <input type="text" name="q" placeholder="搜索 slug 或标题"
           value="<?= htmlspecialchars($keyword) ?>">
    <button type="submit">搜索</button>
    <?php if ($keyword !== ''): ?>
        <a href="/admin/tests.php" class="btn-mini">清除搜索</a>
    <?php endif; ?>
</form>

<?php if ($errors): ?>
    <div class="errors">
        <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="success">
        <?= htmlspecialchars($success) ?>
    </div>
<?php endif; ?>

<?php if (!$tests): ?>
    <p class="hint">当前还没有测试，可以先去“新增测试”创建一个。</p>
<?php else: ?>
    <table>
        <thead>
        <tr>
            <th style="width:50px;">ID</th>
            <th style="width:150px;">Slug</th>
            <th style="width:120px;">封面</th>
            <th>标题</th>
            <th style="width:80px;">题目数</th>
            <th style="width:80px;">结果数</th>
            <th style="width:360px;">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tests as $t): ?>
            <?php $coverSrc = !empty($t['cover_image']) ? $t['cover_image'] : '/assets/images/default.png'; ?>
            <tr>
                <td><?= (int)$t['id'] ?></td>
                <td><code><?= htmlspecialchars($t['slug']) ?></code></td>
                <td style="width:120px;">
                    <a href="<?= htmlspecialchars($coverSrc) ?>" target="_blank">
                        <img src="<?= htmlspecialchars($coverSrc) ?>" alt="封面" style="width:120px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;">
                    </a>
                </td>
                <td><?= htmlspecialchars($t['title']) ?></td>
                <td><?= (int)($t['question_count'] ?? 0) ?></td>
                <td><?= (int)($t['result_count'] ?? 0) ?></td>
                <td class="actions">
                    <a class="btn-mini" href="/admin/edit_test.php?id=<?= (int)$t['id'] ?>">编辑</a>
                    <a class="btn-mini"
                       href="/admin/questions.php?test_id=<?= (int)$t['id'] ?>">
                        题目 & 选项
                    </a>
                    <div class="actions-menu">
                        <details>
                            <summary>更多操作</summary>
                            <div class="actions-menu-body">
                                <a class="btn-mini" href="/<?= htmlspecialchars($t['slug']) ?>" target="_blank">前台查看</a>
                                <a class="btn-mini" href="/admin/results.php?test_id=<?= (int)$t['id'] ?>">结果区间</a>
                                <a class="btn-mini" href="/admin/clone_test.php">克隆</a>
                                <a class="btn-mini" href="/admin/stats.php?test_id=<?= (int)$t['id'] ?>">统计</a>
                                <form method="post"
                                      onsubmit="return confirm('确定要删除这个测试及其所有题目、选项和结果吗？该操作不可恢复。');">
                                    <input type="hidden" name="action" value="delete_test">
                                    <input type="hidden" name="test_id" value="<?= (int)$t['id'] ?>">
                                    <button type="submit" class="danger-btn">删除</button>
                                </form>
                            </div>
                        </details>
                    </div>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
admin_footer();
