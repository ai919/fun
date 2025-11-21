<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';

$pageTitle = '概览';
$pageSubtitle = '快速查看测验与答题情况';
$activeMenu = 'dashboard';

$totalTests = (int)$pdo->query("SELECT COUNT(*) FROM tests")->fetchColumn();
$totalRuns  = (int)$pdo->query("SELECT COUNT(*) FROM test_runs")->fetchColumn();

$recentRunsStmt = $pdo->prepare("SELECT COUNT(*) FROM test_runs WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)");
$recentRunsStmt->execute();
$recentRuns = (int)$recentRunsStmt->fetchColumn();

$topTestsStmt = $pdo->query("
    SELECT t.id, t.title, t.slug, COUNT(r.id) AS run_count
    FROM tests t
    LEFT JOIN test_runs r ON r.test_id = t.id
    GROUP BY t.id
    ORDER BY run_count DESC
    LIMIT 10
");
$topTests = $topTestsStmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <span class="admin-table__muted">欢迎回来，这里是 DoFun 测验后台概览。</span>
    </div>
    <div class="admin-toolbar__right">
        <a href="new_test.php" class="btn btn-primary btn-lg">+ 新建测验</a>
    </div>
</div>

<div class="admin-card" style="margin-bottom: 16px;">
    <table class="admin-table admin-table--kpi">
        <tbody>
        <tr>
            <td>
                <div class="admin-kpi-number"><?= $totalTests ?></div>
                <div class="admin-kpi-label">测验总数</div>
            </td>
            <td>
                <div class="admin-kpi-number"><?= $totalRuns ?></div>
                <div class="admin-kpi-label">累计答题次数</div>
            </td>
            <td>
                <div class="admin-kpi-number"><?= $recentRuns ?></div>
                <div class="admin-kpi-label">最近 7 天答题次数</div>
            </td>
        </tr>
        </tbody>
    </table>
</div>

<div class="admin-card">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 8px;">最受欢迎的测验</h2>
    <?php if (empty($topTests)): ?>
        <p class="admin-table__muted">暂无数据。</p>
    <?php else: ?>
        <table class="admin-table admin-table--compact">
            <thead>
            <tr>
                <th>ID</th>
                <th>标题</th>
                <th>slug</th>
                <th>答题数</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($topTests as $test): ?>
                <tr>
                    <td><?= (int)$test['id'] ?></td>
                    <td><?= htmlspecialchars($test['title']) ?></td>
                    <td>
                        <code class="code-badge"
                              onclick="copyToClipboard('<?= htmlspecialchars($test['slug']) ?>')">
                            <?= htmlspecialchars($test['slug']) ?>
                        </code>
                    </td>
                    <td>
                        <a href="stats.php?test_id=<?= (int)$test['id'] ?>" class="admin-kpi-link">
                            <?= (int)$test['run_count'] ?> 次
                        </a>
                    </td>
                    <td class="admin-table__actions">
                        <a href="test_edit.php?id=<?= (int)$test['id'] ?>" class="btn btn-xs btn-primary">管理测验</a>
                        <a href="../test.php?slug=<?= urlencode($test['slug']) ?>"
                           class="btn btn-xs btn-ghost" target="_blank">预览</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<script>
    function copyToClipboard(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                alert('已复制：' + text);
            });
        }
    }
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
