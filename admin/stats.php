<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/ShareStats.php';

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
    $statsTests = $pdo->query("SELECT id, title, slug FROM tests ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle    = '测验统计 - DoFun心理实验空间';
    $pageHeading  = '测验统计';
    $pageSubtitle = '选择一个测验查看运行数据。';
    $activeMenu   = 'stats';

    ob_start();
    ?>
    <div class="admin-card">
        <?php if (!$statsTests): ?>
            <p class="admin-table__muted">目前还没有可统计的测验，先去创建一个吧。</p>
        <?php else: ?>
            <table class="admin-table">
                <thead>
                <tr>
                    <th style="width:70px;">ID</th>
                    <th>标题</th>
                    <th style="width:140px;">slug</th>
                    <th style="width:120px;">操作</th>
                </tr>
                </thead>
                <tbody>
                <?php foreach ($statsTests as $row): ?>
                    <tr>
                        <td><?= (int)$row['id'] ?></td>
                        <td><?= htmlspecialchars($row['title']) ?></td>
                        <td><code class="code-badge code-badge--muted"><?= htmlspecialchars($row['slug']) ?></code></td>
                        <td class="admin-table__actions">
                            <a class="btn btn-xs" href="/admin/stats.php?test_id=<?= (int)$row['id'] ?>">查看统计</a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
    $content = ob_get_clean();
    include __DIR__ . '/layout.php';
    exit;
}

$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
$testStmt->execute([$testId]);
$test = $testStmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    die('测验不存在');
}

$totalRunsStmt = $pdo->prepare("SELECT COUNT(*) FROM test_runs WHERE test_id = ?");
$totalRunsStmt->execute([$testId]);
$totalRuns = (int)$totalRunsStmt->fetchColumn();

$totalDimsStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM test_run_scores s
     JOIN test_runs r ON r.id = s.test_run_id
     WHERE r.test_id = ?"
);
$totalDimsStmt->execute([$testId]);
$totalDims = (int)$totalDimsStmt->fetchColumn();

$totalHitStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM test_runs
     WHERE test_id = ?
       AND result_id IS NOT NULL"
);
$totalHitStmt->execute([$testId]);
$matchedRuns = (int)$totalHitStmt->fetchColumn();
$unmatchedRuns = max(0, $totalRuns - $matchedRuns);

$distStmt = $pdo->prepare(
    "SELECT 
         res.id,
         res.code,
         res.min_score,
         res.max_score,
         res.title,
         COUNT(tr.id) AS hit_count
     FROM results res
     LEFT JOIN test_runs tr ON tr.result_id = res.id
     WHERE res.test_id = ?
     GROUP BY res.id, res.code, res.min_score, res.max_score, res.title
     ORDER BY res.min_score ASC, res.id ASC"
);
$distStmt->execute([$testId]);
$resultStats = $distStmt->fetchAll(PDO::FETCH_ASSOC);

// 获取分享统计
$shareStats = ShareStats::getStats($testId);
$totalShares = ShareStats::getTestShareCount($testId);

$pageTitle    = '统计 - ' . ($test['title'] ?? '');
$pageHeading  = '测验统计：' . ($test['title'] ?? '');
$pageSubtitle = 'slug: ' . ($test['slug'] ?? '');
$activeMenu   = 'stats';

ob_start();
?>

<div class="admin-card" style="margin-bottom:16px;">
    <?php if ($totalRuns === 0): ?>
        <p class="admin-table__muted">还没有任何完成记录，先在前台跑一次吧。</p>
    <?php else: ?>
        <div class="admin-toolbar" style="justify-content:flex-start; gap:12px; flex-wrap:wrap;">
            <div class="admin-card" style="padding:10px 14px; min-width:160px; box-shadow:none; border:1px solid #e5e7eb;">
                <div class="admin-table__subtitle">完成次数</div>
                <div class="admin-table__title" style="font-size:18px;"><?= number_format($totalRuns) ?></div>
            </div>
            <div class="admin-card" style="padding:10px 14px; min-width:160px; box-shadow:none; border:1px solid #e5e7eb;">
                <div class="admin-table__subtitle">维度得分记录</div>
                <div class="admin-table__title" style="font-size:18px;"><?= number_format($totalDims) ?></div>
            </div>
            <div class="admin-card" style="padding:10px 14px; min-width:160px; box-shadow:none; border:1px solid #e5e7eb;">
                <div class="admin-table__subtitle">匹配到结果的 run</div>
                <div class="admin-table__title" style="font-size:18px;"><?= number_format($matchedRuns) ?></div>
            </div>
            <div class="admin-card" style="padding:10px 14px; min-width:160px; box-shadow:none; border:1px solid #e5e7eb;">
                <div class="admin-table__subtitle">未匹配结果的 run</div>
                <div class="admin-table__title" style="font-size:18px;"><?= number_format($unmatchedRuns) ?></div>
            </div>
            <div class="admin-card" style="padding:10px 14px; min-width:160px; box-shadow:none; border:1px solid #e5e7eb;">
                <div class="admin-table__subtitle">总分享次数</div>
                <div class="admin-table__title" style="font-size:18px;"><?= number_format($totalShares) ?></div>
            </div>
        </div>
        <p class="hint">
            每一次测验会产生 1 条 run 记录，并记录该 run 的维度得分。
        </p>
    <?php endif; ?>
</div>

<div class="admin-card">
    <h2 class="admin-page-title" style="font-size:15px;margin:0 0 10px;">结果命中分布</h2>
    <?php if (!$resultStats): ?>
        <p class="admin-table__muted">暂无结果数据，先在上方添加结果区间吧。</p>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th style="width:140px;">代码</th>
                <th style="width:140px;">分数区间</th>
                <th>标题</th>
                <th style="width:120px;">命中 run</th>
                <th style="width:140px;">覆盖全部 run</th>
                <th style="width:140px;">覆盖已匹配 run</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($resultStats as $row): ?>
                <?php
                $hitCount = (int)$row['hit_count'];
                $pctRuns = $totalRuns > 0 ? round($hitCount * 100.0 / $totalRuns, 1) : 0.0;
                $pctHits = $matchedRuns > 0 ? round($hitCount * 100.0 / $matchedRuns, 1) : 0.0;
                ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><code><?= htmlspecialchars($row['code']) ?></code></td>
                    <td><?= (int)$row['min_score'] ?> - <?= (int)$row['max_score'] ?></td>
                    <td><?= htmlspecialchars($row['title']) ?></td>
                    <td><?= $hitCount ?></td>
                    <td><?= $pctRuns ?>%</td>
                    <td><?= $pctHits ?>%</td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php if (!empty($shareStats)): ?>
<div class="admin-card">
    <h2 class="admin-page-title" style="font-size:15px;margin:0 0 10px;">分享统计</h2>
    <table class="admin-table">
        <thead>
        <tr>
            <th>分享平台</th>
            <th style="width:120px;">分享次数</th>
            <th style="width:140px;">唯一分享</th>
            <th style="width:140px;">唯一IP</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($shareStats as $stat): ?>
            <tr>
                <td><?= htmlspecialchars($stat['platform'] ?: '未知') ?></td>
                <td><?= number_format((int)$stat['share_count']) ?></td>
                <td><?= number_format((int)$stat['unique_shares']) ?></td>
                <td><?= number_format((int)$stat['unique_ips']) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<p class="admin-table__muted">
    更多维度（如渠道、来源等）可以在 <code>test_runs</code> 或 <code>test_run_scores</code> 表中自行扩展字段，并在此处增加相应的统计查询。
</p>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
