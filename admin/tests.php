<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';

$pageTitle = '测验管理';
$pageSubtitle = '管理所有在线测验：标题、标签、排序、状态与答题数';
$activeMenu = 'tests';

$stmt = $pdo->query("
    SELECT t.*,
           (SELECT COUNT(*) FROM test_runs r WHERE r.test_id = t.id) AS run_count
    FROM tests t
    ORDER BY sort_order DESC, id DESC
");
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <input type="text" class="admin-input admin-input--search"
               placeholder="搜索标题或 slug..."
               oninput="filterTests(this.value)">
    </div>
    <div class="admin-toolbar__right">
        <a href="new_test.php" class="btn btn-primary btn-lg">+ 新建测验</a>
    </div>
</div>

<div class="admin-card">
    <table class="admin-table" id="tests-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>标题</th>
            <th>slug</th>
            <th>状态</th>
            <th>标签</th>
            <th>颜色</th>
            <th>答题数</th>
            <th>排序</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tests as $test): ?>
            <tr data-test-row>
                <td><?= (int)$test['id'] ?></td>
                <td>
                    <div class="admin-table__title admin-table__title--lg">
                        <?= htmlspecialchars($test['title']) ?>
                    </div>
                    <?php if (!empty($test['subtitle'])): ?>
                        <div class="admin-table__subtitle">
                            <?= htmlspecialchars($test['subtitle']) ?>
                        </div>
                    <?php endif; ?>
                </td>
                <td>
                    <code class="code-badge"
                          onclick="copyToClipboard('<?= htmlspecialchars($test['slug']) ?>')">
                        <?= htmlspecialchars($test['slug']) ?>
                    </code>
                </td>
                <td>
                    <?php
                    require_once __DIR__ . '/../lib/Constants.php';
                    $status = Constants::normalizeTestStatus($test['status']);
                    $statusLabels = Constants::getTestStatusLabels();
                    $statusLabel = $statusLabels[$status] ?? $status;
                    ?>
                    <span class="badge badge--<?= htmlspecialchars($status) ?>">
                        <?= htmlspecialchars($statusLabel) ?>
                    </span>
                </td>
                <td>
                    <?php if (!empty($test['tags'])): ?>
                        <?php foreach (explode(',', $test['tags']) as $tag): ?>
                            <span class="tag-chip"><?= htmlspecialchars(trim($tag)) ?></span>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if (!empty($test['title_color'])): ?>
                        <span class="color-dot"
                              style="background: <?= htmlspecialchars($test['title_color']) ?>"></span>
                        <code class="code-badge code-badge--muted">
                            <?= htmlspecialchars($test['title_color']) ?>
                        </code>
                    <?php endif; ?>
                </td>
                <td>
                    <a class="admin-kpi-link admin-kpi-link--small" href="stats.php?test_id=<?= (int)$test['id'] ?>">
                        <?= (int)$test['run_count'] ?> 次
                    </a>
                </td>
                <td>
                    <span class="admin-table__muted"><?= (int)$test['sort_order'] ?></span>
                </td>
                <td class="admin-table__actions">
                    <a href="test_edit.php?id=<?= (int)$test['id'] ?>" class="btn btn-xs btn-primary">管理测验</a>
                    <a href="../test.php?slug=<?= urlencode($test['slug']) ?>" class="btn btn-xs btn-ghost" target="_blank">预览</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<script>
    function filterTests(keyword) {
        keyword = keyword.toLowerCase();
        document.querySelectorAll('[data-test-row]').forEach(function (tr) {
            const text = tr.innerText.toLowerCase();
            tr.style.display = text.indexOf(keyword) > -1 ? '' : 'none';
        });
    }

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
