<?php
require_once __DIR__ . '/lib/user_auth.php';
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/topbar.php';

$user = UserAuth::requireLogin();

// 分页参数
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20; // 每页显示数量

// 获取总记录数
$countStmt = $pdo->prepare("
    SELECT COUNT(*)
    FROM test_runs r
    INNER JOIN tests t ON r.test_id = t.id
    WHERE r.user_id = :uid
");
$countStmt->execute([':uid' => $user['id']]);
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));

// 确保页码不超过总页数
if ($page > $totalPages) {
    $page = $totalPages;
}

$offset = ($page - 1) * $perPage;

// 查询当前页数据
$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.created_at,
        r.total_score,
        r.share_token,
        t.title AS test_title,
        t.slug   AS test_slug,
        res.title AS result_title
    FROM test_runs r
    INNER JOIN tests t ON r.test_id = t.id
    LEFT JOIN results res ON r.result_id = res.id
    WHERE r.user_id = :uid
    ORDER BY r.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':uid', $user['id'], PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$runs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>我的测验记录 - DoFun</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/theme-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeBtn = document.getElementById('theme-toggle-btn');
            if (themeBtn) {
                themeBtn.addEventListener('click', function() {
                    window.ThemeToggle.toggle();
                });
            }
        });
    </script>
</head>
<body class="page-my-tests">
<?php render_topbar(); ?>
<div class="my-tests-container">
    <header class="my-tests-header">
        <h1>我的测验记录</h1>
    </header>

    <?php if (empty($runs)): ?>
        <p>你还没有任何测验记录，去首页找一个喜欢的测验试试吧～</p>
        <p><a href="/">返回首页</a></p>
    <?php else: ?>
        <?php if ($totalRows > $perPage): ?>
            <div style="margin-bottom: 16px; color: #666; font-size: 14px;">
                共 <?php echo $totalRows; ?> 条记录，第 <?php echo $page; ?> / <?php echo $totalPages; ?> 页
            </div>
        <?php endif; ?>
        <table class="my-tests-table">
            <thead>
                <tr>
                    <th>测验</th>
                    <th>结果</th>
                    <th>得分</th>
                    <th>时间</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($runs as $run): ?>
                    <tr>
                        <td>
                            <a href="/test.php?slug=<?php echo htmlspecialchars($run['test_slug']); ?>">
                                <?php echo htmlspecialchars($run['test_title']); ?>
                            </a>
                        </td>
                        <td>
                            <?php
                            $shareLink = !empty($run['share_token'])
                                ? '/result.php?token=' . urlencode($run['share_token'])
                                : '';
                            ?>
                            <?php if ($shareLink): ?>
                                <a href="<?php echo $shareLink; ?>">
                                    <?php echo htmlspecialchars($run['result_title'] ?: '查看结果'); ?>
                                </a>
                            <?php else: ?>
                                <?php echo htmlspecialchars($run['result_title'] ?: '-'); ?>
                            <?php endif; ?>
                        </td>
                        <td><?php echo $run['total_score'] !== null ? (int)$run['total_score'] : '-'; ?></td>
                        <td><?php echo htmlspecialchars($run['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if ($totalPages > 1): ?>
            <div style="margin-top: 20px; display: flex; align-items: center; gap: 12px; justify-content: center;">
                <?php if ($page > 1): ?>
                    <a href="?page=<?php echo $page - 1; ?>" class="btn-secondary" style="text-decoration: none; padding: 6px 12px;">← 上一页</a>
                <?php endif; ?>
                <span style="color: #666;">第 <?php echo $page; ?> / <?php echo $totalPages; ?> 页</span>
                <?php if ($page < $totalPages): ?>
                    <a href="?page=<?php echo $page + 1; ?>" class="btn-secondary" style="text-decoration: none; padding: 6px 12px;">下一页 →</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>
</body>
</html>
