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

$contentFile = __DIR__ . '/partials/tests_content.php';

require __DIR__ . '/layout.php';
require __DIR__ . '/layout_footer.php';
