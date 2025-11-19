<?php
// admin/stats.php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';
require __DIR__ . '/layout.php';

$errors  = [];
$success = null;

// 获取 test_id（支持 ?test_id= 或 ?slug=）
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
    admin_header('数据统计 · 请选择测试');
    ?>
    <h1>数据统计</h1>
    <p class="hint">请选择一个测试查看详细统计数据。</p>
    <?php if (!$statsTests): ?>
        <p class="hint">目前还没有测试可以统计。</p>
    <?php else: ?>
        <ul>
            <?php foreach ($statsTests as $row): ?>
                <li>
                    <a href="/admin/stats.php?test_id=<?= (int)$row['id'] ?>">
                        [#<?= (int)$row['id'] ?>] <?= htmlspecialchars($row['title']) ?> (<?= htmlspecialchars($row['slug']) ?>)
                    </a>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
    <?php
    admin_footer();
    exit;
}

// 获取测试信息
$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
$testStmt->execute([$testId]);
$test = $testStmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    die('测试不存在');
}

// 总完成次数：test_runs 里这个 test_id 的记录数
$totalRunsStmt = $pdo->prepare(
    "SELECT COUNT(*) FROM test_runs WHERE test_id = ?"
);
$totalRunsStmt->execute([$testId]);
$totalRuns = (int)$totalRunsStmt->fetchColumn();

// 总维度计分数：这个测试所有 run 的 test_run_scores 行数
$totalDimsStmt = $pdo->prepare(
    "SELECT COUNT(*)
     FROM test_run_scores s
     JOIN test_runs r ON r.id = s.run_id
     WHERE r.test_id = ?"
);
$totalDimsStmt->execute([$testId]);
$totalDims = (int)$totalDimsStmt->fetchColumn();

// 有命中 result_id 的维度记录数
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

// 各结果的命中次数分布（按 result_id 统计）
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

admin_header('统计 · ' . ($test['title'] ?? ''));
?>
<h1>测试统计：<?= htmlspecialchars($test['title'] ?? '') ?></h1>
<p class="hint">
    当前测试 slug：<code><?= htmlspecialchars($test['slug'] ?? '') ?></code>，
    前台访问：<code>/<?= htmlspecialchars($test['slug'] ?? '') ?></code>
</p>

<h2>整体概况</h2>

<?php if ($totalRuns === 0): ?>
    <p class="hint">
        这个测试目前还没有任何完成记录。可以先在前台做几次测试，再回来看看统计。
    </p>
<?php else: ?>
    <ul>
        <li>总完成次数（run）：<strong><?= $totalRuns ?></strong></li>
        <li>总维度计分记录（test_run_scores 行）：<strong><?= $totalDims ?></strong></li>
        <li>命中某个结果区间的维度记录数：<strong><?= $totalHit ?></strong></li>
        <li>未命中任何结果区间的维度记录数：<strong><?= $unmatchedDims ?></strong></li>
    </ul>
    <p class="hint">
        说明：一位用户一次作答是 1 个 run；如果这个测试有多个维度（如 love / anxiety），
        那么一次作答会产生多条维度得分记录，因此「总维度计分记录」通常 ≥ 完成次数。
        下面的结果占比是以「完成次数」为分母来计算的，所以总和可能大于 100%。
    </p>
<?php endif; ?>

<h2>各结果区间出现比例</h2>

<?php if (!$resultStats): ?>
    <p class="hint">这个测试还没有配置任何结果区间（results），请先到「结果管理」里添加。</p>
<?php else: ?>
    <table style="width:100%; border-collapse:collapse; font-size:14px;">
        <thead>
        <tr style="background:#fafafa;">
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">ID</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">维度</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">分数范围</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">标题</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">命中次数</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">占所有完成次数</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">占所有命中记录</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($resultStats as $row): ?>
            <?php
            $hitCount = (int)$row['hit_count'];
            $pctRuns  = ($totalRuns > 0)
                ? round($hitCount * 100.0 / $totalRuns, 1)
                : 0.0;
            $pctHits  = ($totalHit > 0)
                ? round($hitCount * 100.0 / $totalHit, 1)
                : 0.0;
            ?>
            <tr>
                <td style="border-bottom:1px solid #eee; padding:6px 8px;"><?= (int)$row['id'] ?></td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px;">
                    <code><?= htmlspecialchars($row['dimension_key']) ?></code>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px;">
                    <?= (int)$row['range_min'] ?> - <?= (int)$row['range_max'] ?>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px;">
                    <?= htmlspecialchars($row['title']) ?>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px;">
                    <?= $hitCount ?>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px;">
                    <?= $pctRuns ?>%
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px;">
                    <?= $pctHits ?>%
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<p class="hint" style="margin-top:10px;">
    小提示：如果你以后想做更复杂的统计（按日期、按来源渠道等），
    可以在 <code>test_runs</code> 里增加更多字段（比如 source、utm 等），
    再扩展这里的查询。
</p>

<?php
admin_footer();
