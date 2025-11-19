<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';

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

    $pageTitle    = '数据统计 · DoFun';
    $pageHeading  = '数据统计';
    $pageSubtitle = '请选择一个测试查看完成人次和结果分布。';
    $activeMenu   = 'stats';

    require __DIR__ . '/layout.php';
    ?>
    <div class="section-card">
        <?php if (!$statsTests): ?>
            <p class="hint">当前还没有测试可以统计，先去创建一个测试吧。</p>
        <?php else: ?>
            <ul>
                <?php foreach ($statsTests as $row): ?>
                    <li style="margin-bottom:6px;">
                        <a href="/admin/stats.php?test_id=<?= (int)$row['id'] ?>">
                            [#<?= (int)$row['id'] ?>] <?= htmlspecialchars($row['title']) ?>
                            <span class="hint">(<?= htmlspecialchars($row['slug']) ?>)</span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </div>
    <?php
    require __DIR__ . '/layout_footer.php';
    exit;
}

$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
$testStmt->execute([$testId]);
$test = $testStmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    die('测试不存在');
}

$totalRunsStmt = $pdo->prepare("SELECT COUNT(*) FROM test_runs WHERE test_id = ?");
$totalRunsStmt->execute([$testId]);
$totalRuns = (int)$totalRunsStmt->fetchColumn();

$totalDimsStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM test_run_scores s
     JOIN test_runs r ON r.id = s.run_id
     WHERE r.test_id = ?"
);
$totalDimsStmt->execute([$testId]);
$totalDims = (int)$totalDimsStmt->fetchColumn();

$totalHitStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM test_run_scores s
     JOIN test_runs r ON r.id = s.run_id
     WHERE r.test_id = ?
       AND s.result_id IS NOT NULL"
);
$totalHitStmt->execute([$testId]);
$totalHit = (int)$totalHitStmt->fetchColumn();

$unmatchedDims = max(0, $totalDims - $totalHit);

$distStmt = $pdo->prepare(
    "SELECT 
         res.id,
         res.dimension_key,
         res.range_min,
         res.range_max,
         res.title,
         COUNT(s.id) AS hit_count
     FROM results res
     LEFT JOIN test_run_scores s
        ON s.result_id = res.id
     LEFT JOIN test_runs r
        ON r.id = s.run_id
     WHERE res.test_id = ?
     GROUP BY res.id, res.dimension_key, res.range_min, res.range_max, res.title
     ORDER BY res.dimension_key ASC, res.range_min ASC, res.range_max ASC, res.id ASC"
);
$distStmt->execute([$testId]);
$resultStats = $distStmt->fetchAll(PDO::FETCH_ASSOC);

$pageTitle    = '统计 · ' . ($test['title'] ?? '');
$pageHeading  = '测试统计：' . ($test['title'] ?? '');
$pageSubtitle = 'slug：' . ($test['slug'] ?? '') . ' · 前台路径 /' . ($test['slug'] ?? '');
$activeMenu   = 'stats';

require __DIR__ . '/layout.php';
?>

<div class="section-card">
    <?php if ($totalRuns === 0): ?>
        <p class="hint">
            这个测试目前还没有任何完成记录。可以先在前台做几次测试，再回来查看统计。
        </p>
    <?php else: ?>
        <div class="stat-cards">
            <div class="stat-card">
                <div class="stat-card-title">总完成次数（run）</div>
                <div class="stat-card-value"><?= number_format($totalRuns) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-title">维度计分记录</div>
                <div class="stat-card-value"><?= number_format($totalDims) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-title">命中结果的记录</div>
                <div class="stat-card-value"><?= number_format($totalHit) ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card-title">未命中任何结果</div>
                <div class="stat-card-value"><?= number_format($unmatchedDims) ?></div>
            </div>
        </div>
        <p class="hint">
            一位用户一次作答是 1 个 run；如果测试包含多个维度，一次作答会产生多条维度得分记录。
            下方“占所有完成次数”的百分比以 run 为分母，因此总和可能大于 100%。
        </p>
    <?php endif; ?>
</div>

<div class="section-card">
    <h2>各结果区间出现比例</h2>
    <?php if (!$resultStats): ?>
        <p class="hint">这个测试还没有配置任何结果区间，请先到「结果管理」里添加。</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th style="width:120px;">维度</th>
                <th style="width:140px;">分数范围</th>
                <th>标题</th>
                <th style="width:110px;">命中次数</th>
                <th style="width:150px;">占所有完成次数</th>
                <th style="width:150px;">占命中记录</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($resultStats as $row): ?>
                <?php
                $hitCount = (int)$row['hit_count'];
                $pctRuns = ($totalRuns > 0)
                    ? round($hitCount * 100.0 / max(1, $totalRuns), 1)
                    : 0.0;
                $pctHits = ($totalHit > 0)
                    ? round($hitCount * 100.0 / max(1, $totalHit), 1)
                    : 0.0;
                ?>
                <tr>
                    <td><?= (int)$row['id'] ?></td>
                    <td><code><?= htmlspecialchars($row['dimension_key']) ?></code></td>
                    <td><?= (int)$row['range_min'] ?> - <?= (int)$row['range_max'] ?></td>
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

<p class="hint">
    小提示：若需要按日期、渠道等维度做更复杂的统计，可以在 <code>test_runs</code> 表里扩展字段（如 source、utm 等），
    然后在这里追加对应的查询。
</p>

<?php require __DIR__ . '/layout_footer.php'; ?>
