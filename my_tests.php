<?php
require_once __DIR__ . '/lib/user_auth.php';
require_once __DIR__ . '/lib/db_connect.php';

$user = UserAuth::requireLogin();

$stmt = $pdo->prepare("
    SELECT
        r.id,
        r.created_at,
        r.total_score,
        t.title AS test_title,
        t.slug   AS test_slug,
        res.title AS result_title
    FROM test_runs r
    INNER JOIN tests t ON r.test_id = t.id
    LEFT JOIN results res ON r.result_id = res.id
    WHERE r.user_id = :uid
    ORDER BY r.created_at DESC
    LIMIT 100
");
$stmt->execute([':uid' => $user['id']]);
$runs = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>我的测验记录 - DoFun</title>
    <link rel="stylesheet" href="/assets/css/style.css">
</head>
<body class="page-my-tests">
<div class="my-tests-container">
    <header class="my-tests-header">
        <h1>我的测验记录</h1>
        <div class="my-tests-user">
            <span><?php echo htmlspecialchars($user['nickname'] ?: $user['email']); ?></span>
            <a href="/logout.php" class="link-logout">退出</a>
        </div>
    </header>

    <?php if (empty($runs)): ?>
        <p>你还没有任何测验记录，去首页找一个喜欢的测验试试吧～</p>
        <p><a href="/">返回首页</a></p>
    <?php else: ?>
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
                        <td><?php echo htmlspecialchars($run['result_title'] ?: '-'); ?></td>
                        <td><?php echo $run['total_score'] !== null ? (int)$run['total_score'] : '-'; ?></td>
                        <td><?php echo htmlspecialchars($run['created_at']); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
</body>
</html>
