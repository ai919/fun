<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';

$pageTitle    = '测试列表 · DoFun';
$pageHeading  = '测试列表';
$pageSubtitle = '像 WordPress 一样集中管理所有测试：查看、编辑题目和结果、查看统计、克隆、删除等。';
$activeMenu   = 'tests';

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

<form method="get" class="form-inline" style="margin-bottom:16px;">
    <input type="text" name="q" placeholder="搜索 slug 或标题"
           value="<?= htmlspecialchars($keyword) ?>">
    <button type="submit" class="btn btn-primary">搜索</button>
    <?php if ($keyword !== ''): ?>
        <a href="/admin/tests.php" class="btn btn-ghost btn-xs">清除搜索</a>
    <?php endif; ?>
</form>

<?php if (!$tests): ?>
    <p class="hint">当前还没有测试，可以先去“新增测试”创建一个。</p>
<?php else: ?>
    <table class="table-admin">
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
            <?php
            $coverSrc   = !empty($t['cover_image']) ? $t['cover_image'] : '/assets/images/default.png';
            $titleEmoji = trim($t['title_emoji'] ?? '');
            $titleColor = trim($t['title_color'] ?? '');
            $tagItems   = array_filter(array_map('trim', explode(',', $t['tags'] ?? '')));
            ?>
            <tr>
                <td><?= (int)$t['id'] ?></td>
                <td><code><?= htmlspecialchars($t['slug']) ?></code></td>
                <td style="width:120px;">
                    <a href="<?= htmlspecialchars($coverSrc) ?>" target="_blank">
                        <img src="<?= htmlspecialchars($coverSrc) ?>" alt="封面"
                             style="width:120px;height:50px;object-fit:cover;border-radius:6px;border:1px solid #e5e7eb;">
                    </a>
                </td>
                <td>
                    <div class="test-title-row">
                        <?php if ($titleEmoji !== ''): ?>
                            <span class="test-title-emoji"><?= htmlspecialchars($titleEmoji) ?></span>
                        <?php endif; ?>
                        <span class="test-title-text"<?= $titleColor !== '' ? ' style="color:' . htmlspecialchars($titleColor) . ';"' : '' ?>>
                            <?= htmlspecialchars($t['title']) ?>
                        </span>
                    </div>
                    <?php if ($tagItems): ?>
                        <div class="tag-list">
                            <?php foreach ($tagItems as $tag): ?>
                                <span class="badge badge-muted"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td><?= (int)($t['question_count'] ?? 0) ?></td>
                <td><?= (int)($t['result_count'] ?? 0) ?></td>
                <td class="actions">
                    <a class="btn-mini" href="/admin/edit_test.php?id=<?= (int)$t['id'] ?>">编辑</a>
                    <a class="btn-mini" href="/admin/questions.php?test_id=<?= (int)$t['id'] ?>">题目 & 选项</a>
                    <div class="actions-menu">
                        <details>
                            <summary>更多操作</summary>
                            <div class="actions-menu-body">
                                <a class="btn-mini" href="/<?= htmlspecialchars($t['slug']) ?>" target="_blank">前台查看</a>
                                <a class="btn-mini" href="/admin/results.php?test_id=<?= (int)$t['id'] ?>">结果区间</a>
                                <a class="btn-mini" href="/admin/clone_test.php">克隆</a>
                                <a class="btn-mini" href="/admin/stats.php?test_id=<?= (int)$t['id'] ?>">统计</a>
                                <form method="post" onsubmit="return confirm('确定要删除这个测试及其所有题目、选项和结果吗？该操作不可恢复。');">
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

<?php require __DIR__ . '/layout_footer.php'; ?>
