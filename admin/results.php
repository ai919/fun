<?php
// admin/results.php
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
    die('缺少 test_id 或 slug 参数，例如：/admin/results.php?test_id=1');
}

// 获取测试信息
$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
$testStmt->execute([$testId]);
$test = $testStmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    die('测试不存在');
}

// 获取该测试的维度（方便填写 dimension_key）
$dimStmt = $pdo->prepare("SELECT * FROM dimensions WHERE test_id = ? ORDER BY id ASC");
$dimStmt->execute([$testId]);
$dimensions = $dimStmt->fetchAll(PDO::FETCH_ASSOC);

// 处理表单提交（add / edit / delete）
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_result') {
        $dimensionKey = trim($_POST['dimension_key'] ?? '');
        $rangeMin     = (int)($_POST['range_min'] ?? 0);
        $rangeMax     = (int)($_POST['range_max'] ?? 0);
        $title        = trim($_POST['title'] ?? '');
        $description  = trim($_POST['description'] ?? '');

        if ($dimensionKey === '') {
            $errors[] = '维度键（dimension_key）不能为空。';
        }
        if ($title === '') {
            $errors[] = '结果标题不能为空。';
        }
        if ($rangeMin > $rangeMax) {
            $errors[] = '最小分不得大于最大分。';
        }

        if (!$errors) {
            $ins = $pdo->prepare(
                "INSERT INTO results (test_id, dimension_key, range_min, range_max, title, description)
                 VALUES (?, ?, ?, ?, ?, ?)"
            );
            $ins->execute([$testId, $dimensionKey, $rangeMin, $rangeMax, $title, $description]);
            $success = '结果区间已添加。';
        }
    }

    if ($action === 'edit_result') {
        $rid          = (int)($_POST['result_id'] ?? 0);
        $dimensionKey = trim($_POST['dimension_key'] ?? '');
        $rangeMin     = (int)($_POST['range_min'] ?? 0);
        $rangeMax     = (int)($_POST['range_max'] ?? 0);
        $title        = trim($_POST['title'] ?? '');
        $description  = trim($_POST['description'] ?? '');

        if (!$rid) {
            $errors[] = '缺少结果 ID。';
        }
        if ($dimensionKey === '') {
            $errors[] = '维度键不能为空。';
        }
        if ($title === '') {
            $errors[] = '结果标题不能为空。';
        }
        if ($rangeMin > $rangeMax) {
            $errors[] = '最小分不得大于最大分。';
        }

        if (!$errors && $rid) {
            $upd = $pdo->prepare(
                "UPDATE results
                 SET dimension_key = ?, range_min = ?, range_max = ?, title = ?, description = ?
                 WHERE id = ? AND test_id = ?"
            );
            $upd->execute([$dimensionKey, $rangeMin, $rangeMax, $title, $description, $rid, $testId]);
            $success = '结果区间已更新。';
        }
    }

    if ($action === 'delete_result') {
        $rid = (int)($_POST['result_id'] ?? 0);
        if (!$rid) {
            $errors[] = '缺少结果 ID。';
        } else {
            $del = $pdo->prepare("DELETE FROM results WHERE id = ? AND test_id = ?");
            $del->execute([$rid, $testId]);
            $success = '结果区间已删除。';
        }
    }
}

// 取出该测试的所有结果
$rStmt = $pdo->prepare(
    "SELECT * FROM results
     WHERE test_id = ?
     ORDER BY dimension_key ASC, range_min ASC, range_max ASC, id ASC"
);
$rStmt->execute([$testId]);
$results = $rStmt->fetchAll(PDO::FETCH_ASSOC);

// 取出最近 20 次测试记录 + 维度得分
$runStmt = $pdo->prepare(
    "SELECT * FROM test_runs
     WHERE test_id = ?
     ORDER BY id DESC
     LIMIT 20"
);
$runStmt->execute([$testId]);
$runs = $runStmt->fetchAll(PDO::FETCH_ASSOC);

$runScores = [];
if ($runs) {
    $runIds = array_column($runs, 'id');
    $place  = implode(',', array_fill(0, count($runIds), '?'));
    $rsStmt = $pdo->prepare(
        "SELECT s.*, r.title AS result_title
         FROM test_run_scores s
         LEFT JOIN results r ON r.id = s.result_id
         WHERE s.run_id IN ($place)
         ORDER BY s.run_id DESC, s.dimension_key ASC"
    );
    $rsStmt->execute($runIds);
    while ($row = $rsStmt->fetch(PDO::FETCH_ASSOC)) {
        $rid = $row['run_id'];
        if (!isset($runScores[$rid])) {
            $runScores[$rid] = [];
        }
        $runScores[$rid][] = $row;
    }
}

admin_header('结果管理 · ' . ($test['title'] ?? ''));
?>
<h1>结果管理：<?= htmlspecialchars($test['title'] ?? '') ?></h1>
<p class="hint">
    当前测试 slug：<code><?= htmlspecialchars($test['slug'] ?? '') ?></code>，
    前台访问：<code>/<?= htmlspecialchars($test['slug'] ?? '') ?></code>
</p>

<?php if ($dimensions): ?>
    <p class="hint">
        该测试的维度列表：
        <?php foreach ($dimensions as $d): ?>
            <code><?= htmlspecialchars($d['key_name']) ?></code>
        <?php endforeach; ?>
    </p>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="errors">
        <?php foreach ($errors as $e): ?>
            <div><?= htmlspecialchars($e) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<h2>结果区间列表</h2>

<?php if (!$results): ?>
    <p class="hint">当前还没有结果区间，可以在下方添加。</p>
<?php else: ?>
    <table style="width:100%; border-collapse:collapse; font-size:14px; margin-bottom:16px;">
        <thead>
        <tr style="background:#fafafa;">
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">ID</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">维度键</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">分数范围</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">标题</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">说明</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($results as $res): ?>
            <tr>
                <td style="border-bottom:1px solid #eee; padding:6px 8px; vertical-align:top;">
                    <?= (int)$res['id'] ?>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px; vertical-align:top;">
                    <?= htmlspecialchars($res['dimension_key']) ?>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px; vertical-align:top;">
                    <?= (int)$res['range_min'] ?> - <?= (int)$res['range_max'] ?>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px; vertical-align:top;">
                    <?= htmlspecialchars($res['title']) ?>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px; vertical-align:top;">
                    <div style="max-height:80px; overflow:auto; font-size:13px;">
                        <?= nl2br(htmlspecialchars($res['description'])) ?>
                    </div>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px; vertical-align:top;">
                    <!-- 编辑结果 -->
                    <form method="post" style="font-size:12px; margin-bottom:4px;">
                        <input type="hidden" name="action" value="edit_result">
                        <input type="hidden" name="result_id" value="<?= (int)$res['id'] ?>">
                        维度：
                        <input type="text" name="dimension_key" size="8"
                               value="<?= htmlspecialchars($res['dimension_key']) ?>">
                        范围：
                        <input type="number" name="range_min" style="width:60px;"
                               value="<?= (int)$res['range_min'] ?>"> -
                        <input type="number" name="range_max" style="width:60px;"
                               value="<?= (int)$res['range_max'] ?>"><br>
                        标题：<br>
                        <input type="text" name="title" style="width:100%;"
                               value="<?= htmlspecialchars($res['title']) ?>"><br>
                        说明：<br>
                        <textarea name="description" rows="3" style="width:100%;"><?= htmlspecialchars($res['description']) ?></textarea><br>
                        <button type="submit">保存结果</button>
                    </form>

                    <!-- 删除结果 -->
                    <form method="post"
                          onsubmit="return confirm('确定删除这个结果区间吗？已记录的测试结果仍会保留，只是 result_id 可能变为空。');">
                        <input type="hidden" name="action" value="delete_result">
                        <input type="hidden" name="result_id" value="<?= (int)$res['id'] ?>">
                        <button type="submit" style="background:#f87171; color:#fff; border:none; border-radius:4px; padding:2px 8px; font-size:12px;">
                            删除
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<h3>新增结果区间</h3>
<form method="post" style="font-size:14px; max-width:640px;">
    <input type="hidden" name="action" value="add_result">
    <p>
        维度键（dimension_key）：
        <input type="text" name="dimension_key" required placeholder="例如：love / animal / work">
    </p>
    <p>
        分数范围：
        <input type="number" name="range_min" style="width:80px;" required> -
        <input type="number" name="range_max" style="width:80px;" required>
    </p>
    <p>
        标题：<br>
        <input type="text" name="title" style="width:100%;" required>
    </p>
    <p>
        说明：<br>
        <textarea name="description" rows="3" style="width:100%;"></textarea>
    </p>
    <button type="submit">添加结果区间</button>
</form>

<hr style="margin:24px 0;">

<h2>最近测试记录（仅本测试）</h2>
<p class="hint">
    以下是最近 20 次这个测试的匿名提交，每一行是一位用户（或一轮作答）的维度得分和命中结果。
</p>

<?php if (!$runs): ?>
    <p class="hint">目前还没有测试记录。</p>
<?php else: ?>
    <table style="width:100%; border-collapse:collapse; font-size:13px;">
        <thead>
        <tr style="background:#fafafa;">
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">Run ID</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">时间</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">维度得分 & 结果</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">IP（简略）</th>
            <th style="border-bottom:1px solid #eee; padding:6px 8px;">User-Agent（截断）</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($runs as $run): ?>
            <?php
            $rid   = $run['id'];
            $scores = $runScores[$rid] ?? [];
            ?>
            <tr>
                <td style="border-bottom:1px solid #eee; padding:6px 8px;">
                    <?= (int)$rid ?>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px;">
                    <?= htmlspecialchars($run['created_at']) ?>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px;">
                    <?php if (!$scores): ?>
                        <span class="hint">（无记录）</span>
                    <?php else: ?>
                        <?php foreach ($scores as $s): ?>
                            <div>
                                <code><?= htmlspecialchars($s['dimension_key']) ?></code>
                                ：<?= (int)$s['score'] ?>
                                <?php if (!empty($s['result_title'])): ?>
                                    <span class="hint">（<?= htmlspecialchars($s['result_title']) ?>）</span>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px;">
                    <?php
                    $ip = $run['client_ip'] ?? '';
                    echo $ip ? htmlspecialchars($ip) : '-';
                    ?>
                </td>
                <td style="border-bottom:1px solid #eee; padding:6px 8px;">
                    <?php
                    $ua = $run['user_agent'] ?? '';
                    if (strlen($ua) > 60) {
                        $ua = substr($ua, 0, 57) . '...';
                    }
                    echo $ua ? htmlspecialchars($ua) : '-';
                    ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
admin_footer();
