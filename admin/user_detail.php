<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/csrf.php';

$pageTitle = '用户详情';
$pageSubtitle = '查看用户信息和测验记录';
$activeMenu = 'users';

$userId = (int)($_GET['id'] ?? 0);
if ($userId <= 0) {
    header('Location: users.php');
    exit;
}

// 获取用户信息
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $userId]);
$user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$user) {
    header('Location: users.php');
    exit;
}

// 获取用户测验记录
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$offset = ($page - 1) * $perPage;

$stmt = $pdo->prepare("
    SELECT tr.*, t.title, t.slug, t.title_color
    FROM test_runs tr
    LEFT JOIN tests t ON tr.test_id = t.id
    WHERE tr.user_id = :id
    ORDER BY tr.created_at DESC
    LIMIT :limit OFFSET :offset
");
$stmt->bindValue(':id', $userId, PDO::PARAM_INT);
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$testRuns = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 获取测验记录总数
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM test_runs WHERE user_id = :id");
$countStmt->execute([':id' => $userId]);
$totalRuns = (int)$countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRuns / $perPage));

// 获取统计信息
$statsStmt = $pdo->prepare("
    SELECT 
        COUNT(DISTINCT tr.test_id) AS test_types,
        COUNT(*) AS total_runs,
        MIN(tr.created_at) AS first_run,
        MAX(tr.created_at) AS last_run
    FROM test_runs tr
    WHERE tr.user_id = :id
");
$statsStmt->execute([':id' => $userId]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);

ob_start();
?>

<div class="admin-card" style="margin-bottom: 20px;">
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
        <h2 style="margin: 0; color: var(--admin-text-primary);">用户信息</h2>
        <a href="users.php" class="btn btn-secondary">返回列表</a>
    </div>
    
    <table class="admin-table admin-table--compact">
        <tr>
            <th style="width: 120px;">用户ID</th>
            <td><?= (int)$user['id'] ?></td>
        </tr>
        <tr>
            <th>用户名</th>
            <td><?= htmlspecialchars($user['email']) ?></td>
        </tr>
        <tr>
            <th>昵称</th>
            <td><?= $user['nickname'] ? htmlspecialchars($user['nickname']) : '<span class="admin-table__muted">未设置</span>' ?></td>
        </tr>
        <tr>
            <th>注册时间</th>
            <td><?= $user['created_at'] ? date('Y-m-d H:i:s', strtotime($user['created_at'])) : '-' ?></td>
        </tr>
        <tr>
            <th>最后登录</th>
            <td><?= $user['last_login_at'] ? date('Y-m-d H:i:s', strtotime($user['last_login_at'])) : '<span class="admin-table__muted">从未登录</span>' ?></td>
        </tr>
        <tr>
            <th>性别</th>
            <td>
                <?php
                $genderLabels = ['male' => '男', 'female' => '女', 'other' => '其他'];
                echo $user['gender'] ? htmlspecialchars($genderLabels[$user['gender']] ?? $user['gender']) : '<span class="admin-table__muted">未设置</span>';
                ?>
            </td>
        </tr>
        <tr>
            <th>出生日期</th>
            <td><?= $user['birth_date'] ? htmlspecialchars($user['birth_date']) : '<span class="admin-table__muted">未设置</span>' ?></td>
        </tr>
        <?php if ($user['birth_date']): 
            $birthDate = new DateTime($user['birth_date']);
            $today = new DateTime();
            $age = $today->diff($birthDate)->y;
        ?>
        <tr>
            <th>年龄</th>
            <td><?= $age ?> 岁</td>
        </tr>
        <?php endif; ?>
        <tr>
            <th>星座</th>
            <td><?= $user['zodiac'] ? htmlspecialchars($user['zodiac']) : '<span class="admin-table__muted">未设置</span>' ?></td>
        </tr>
        <tr>
            <th>属相</th>
            <td><?= $user['chinese_zodiac'] ? htmlspecialchars($user['chinese_zodiac']) : '<span class="admin-table__muted">未设置</span>' ?></td>
        </tr>
        <tr>
            <th>人格</th>
            <td><?= $user['personality'] ? htmlspecialchars($user['personality']) : '<span class="admin-table__muted">未设置</span>' ?></td>
        </tr>
    </table>
</div>

<div class="admin-card" style="margin-bottom: 20px;">
    <h2 style="margin: 0 0 16px 0; color: var(--admin-text-primary);">统计信息</h2>
    <table class="admin-table admin-table--kpi">
        <tr>
            <td>
                <div class="admin-kpi-number"><?= (int)$stats['test_types'] ?></div>
                <div class="admin-kpi-label">测验类型</div>
            </td>
            <td>
                <div class="admin-kpi-number"><?= (int)$stats['total_runs'] ?></div>
                <div class="admin-kpi-label">总测验次数</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="font-size: 14px;">
                    <?= $stats['first_run'] ? date('Y-m-d', strtotime($stats['first_run'])) : '-' ?>
                </div>
                <div class="admin-kpi-label">首次测验</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="font-size: 14px;">
                    <?= $stats['last_run'] ? date('Y-m-d', strtotime($stats['last_run'])) : '-' ?>
                </div>
                <div class="admin-kpi-label">最近测验</div>
            </td>
        </tr>
    </table>
</div>

<div class="admin-card">
    <h2 style="margin: 0 0 16px 0; color: var(--admin-text-primary);">测验记录</h2>
    
    <?php if (empty($testRuns)): ?>
        <p class="admin-table__muted">该用户还没有测验记录</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th>测验名称</th>
                <th style="width:120px;">得分</th>
                <th style="width:180px;">测验时间</th>
                <th style="width:200px;">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($testRuns as $run): ?>
                <tr>
                    <td><?= (int)$run['id'] ?></td>
                    <td>
                        <div class="admin-table__title">
                            <?php if ($run['title']): ?>
                                <span style="color: <?= htmlspecialchars($run['title_color'] ?? '#4f46e5') ?>;">
                                    <?= htmlspecialchars($run['title']) ?>
                                </span>
                            <?php else: ?>
                                <span class="admin-table__muted">测验已删除</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($run['slug']): ?>
                            <div class="admin-table__subtitle">
                                <code class="code-badge"><?= htmlspecialchars($run['slug']) ?></code>
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($run['total_score'] !== null): ?>
                            <span class="admin-table__title"><?= number_format((float)$run['total_score'], 2) ?></span>
                        <?php else: ?>
                            <span class="admin-table__muted">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="admin-table__muted">
                            <?= $run['created_at'] ? date('Y-m-d H:i:s', strtotime($run['created_at'])) : '-' ?>
                        </span>
                    </td>
                    <td>
                        <div class="admin-table__actions">
                            <?php if ($run['slug']): ?>
                                <a href="../test.php?slug=<?= urlencode($run['slug']) ?>" class="btn btn-xs btn-ghost" target="_blank">查看测验</a>
                            <?php endif; ?>
                            <?php if ($run['share_token']): ?>
                                <a href="../result.php?token=<?= urlencode($run['share_token']) ?>" class="btn btn-xs btn-primary" target="_blank">查看结果</a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        
        <?php if ($totalPages > 1): ?>
            <div style="margin-top: 20px; display: flex; gap: 8px; justify-content: center; align-items: center;">
                <?php if ($page > 1): ?>
                    <a href="?id=<?= $userId ?>&page=<?= $page - 1 ?>" class="btn btn-secondary">上一页</a>
                <?php endif; ?>
                <span class="admin-table__muted">第 <?= $page ?> / <?= $totalPages ?> 页</span>
                <?php if ($page < $totalPages): ?>
                    <a href="?id=<?= $userId ?>&page=<?= $page + 1 ?>" class="btn btn-secondary">下一页</a>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

