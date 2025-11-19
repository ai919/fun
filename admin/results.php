<?php
require __DIR__ . '/auth.php';
require_admin_login();

require __DIR__ . '/../lib/db_connect.php';

$errors  = [];
$success = null;

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

$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
$testStmt->execute([$testId]);
$test = $testStmt->fetch(PDO::FETCH_ASSOC);
if (!$test) {
    die('测试不存在');
}

$dimStmt = $pdo->prepare("SELECT * FROM dimensions WHERE test_id = ? ORDER BY id ASC");
$dimStmt->execute([$testId]);
$dimensions = $dimStmt->fetchAll(PDO::FETCH_ASSOC);

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

$rStmt = $pdo->prepare(
    "SELECT * FROM results
     WHERE test_id = ?
     ORDER BY dimension_key ASC, range_min ASC, range_max ASC, id ASC"
);
$rStmt->execute([$testId]);
$results = $rStmt->fetchAll(PDO::FETCH_ASSOC);

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

$pageTitle    = '结果管理 · ' . ($test['title'] ?? '');
$pageHeading  = '结果区间管理';
$pageSubtitle = '当前测试：' . ($test['title'] ?? '') . ' · slug：' . ($test['slug'] ?? '');
$activeMenu   = 'tests';

require __DIR__ . '/layout.php';
?>

<?php if ($dimensions): ?>
    <div class="section-card">
        <h2>维度参考</h2>
        <p class="hint">配置结果时可以参考下列 dimension_key：</p>
        <div class="tag-list">
            <?php foreach ($dimensions as $dimension): ?>
                <code><?= htmlspecialchars($dimension['key_name']) ?></code>
            <?php endforeach; ?>
        </div>
    </div>
<?php endif; ?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($success): ?>
    <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
<?php endif; ?>

<div class="section-card">
    <h2>结果区间列表</h2>
    <?php if (!$results): ?>
        <p class="hint">当前还没有任何结果区间，可以在下方添加。</p>
    <?php else: ?>
        <table class="table-admin">
            <thead>
            <tr>
                <th style="width:60px;">ID</th>
                <th style="width:120px;">维度</th>
                <th style="width:120px;">分数范围</th>
                <th style="width:160px;">标题</th>
                <th>说明</th>
                <th style="width:320px;">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($results as $res): ?>
                <tr>
                    <td><?= (int)$res['id'] ?></td>
                    <td><code><?= htmlspecialchars($res['dimension_key']) ?></code></td>
                    <td><?= (int)$res['range_min'] ?> - <?= (int)$res['range_max'] ?></td>
                    <td><?= htmlspecialchars($res['title']) ?></td>
                    <td><?= nl2br(htmlspecialchars($res['description'])) ?></td>
                    <td>
                        <div class="result-actions">
                            <form method="post" class="inline-form">
                                <input type="hidden" name="action" value="edit_result">
                                <input type="hidden" name="result_id" value="<?= (int)$res['id'] ?>">
                                <div class="inline-form-row">
                                    <div class="field-inline">
                                        <label>维度</label>
                                        <input type="text" name="dimension_key" value="<?= htmlspecialchars($res['dimension_key']) ?>">
                                    </div>
                                    <div class="field-inline">
                                        <label>最小分</label>
                                        <input type="number" name="range_min" value="<?= (int)$res['range_min'] ?>">
                                    </div>
                                    <div class="field-inline">
                                        <label>最大分</label>
                                        <input type="number" name="range_max" value="<?= (int)$res['range_max'] ?>">
                                    </div>
                                </div>
                                <div class="inline-form-row">
                                    <div class="field-inline">
                                        <label>标题</label>
                                        <input type="text" name="title" value="<?= htmlspecialchars($res['title']) ?>">
                                    </div>
                                </div>
                                <div class="inline-form-row">
                                    <div class="field-inline" style="flex:1;">
                                        <label>说明</label>
                                        <textarea name="description" rows="2"><?= htmlspecialchars($res['description']) ?></textarea>
                                    </div>
                                </div>
                                <button type="submit" class="btn btn-primary btn-sm">保存</button>
                            </form>
                            <form method="post"
                                  onsubmit="return confirm('确定删除这个结果区间吗？已记录的数据不会被删除，但 result_id 会变为空。');">
                                <input type="hidden" name="action" value="delete_result">
                                <input type="hidden" name="result_id" value="<?= (int)$res['id'] ?>">
                                <button type="submit" class="btn btn-danger btn-sm">删除</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<form method="post" class="admin-form">
    <input type="hidden" name="action" value="add_result">
    <div class="field">
        <label for="dimension_key">维度键（dimension_key）</label>
        <input type="text" id="dimension_key" name="dimension_key" required placeholder="例如：love / animal / work">
    </div>
    <div class="field">
        <label>分数范围</label>
        <div class="inline-form-row">
            <div class="field-inline">
                <label>最小分</label>
                <input type="number" name="range_min" required>
            </div>
            <div class="field-inline">
                <label>最大分</label>
                <input type="number" name="range_max" required>
            </div>
        </div>
    </div>
    <div class="field">
        <label for="title">标题</label>
        <input type="text" id="title" name="title" required>
    </div>
    <div class="field">
        <label for="description">说明</label>
        <textarea id="description" name="description" rows="3"></textarea>
    </div>
    <div class="form-actions">
        <button type="submit" class="btn btn-primary">添加结果区间</button>
    </div>
</form>

<div class="section-card">
    <h2>最近测试记录（仅本测试）</h2>
    <p class="hint">以下为最近 20 次匿名提交的维度得分和命中结果，用于快速排查配置问题。</p>
    <?php if (!$runs): ?>
        <p class="hint">目前还没有测试记录。</p>
    <?php else: ?>
        <table class="table-admin table-compact">
            <thead>
            <tr>
                <th style="width:80px;">Run ID</th>
                <th style="width:160px;">时间</th>
                <th>维度得分 &amp; 结果</th>
                <th style="width:140px;">IP</th>
                <th style="width:220px;">User-Agent</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($runs as $run): ?>
                <?php $rid = $run['id']; ?>
                <tr>
                    <td><?= (int)$rid ?></td>
                    <td><?= htmlspecialchars($run['created_at']) ?></td>
                    <td>
                        <?php $scores = $runScores[$rid] ?? []; ?>
                        <?php if (!$scores): ?>
                            <span class="hint">（无记录）</span>
                        <?php else: ?>
                            <?php foreach ($scores as $score): ?>
                                <div>
                                    <code><?= htmlspecialchars($score['dimension_key']) ?></code>
                                    ：<?= (int)$score['score'] ?>
                                    <?php if (!empty($score['result_title'])): ?>
                                        <span class="hint">（<?= htmlspecialchars($score['result_title']) ?>）</span>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </td>
                    <td><?= $run['client_ip'] ? htmlspecialchars($run['client_ip']) : '-' ?></td>
                    <td>
                        <?php
                        $ua = $run['user_agent'] ?? '';
                        if (strlen($ua) > 80) {
                            $ua = substr($ua, 0, 77) . '...';
                        }
                        echo $ua ? htmlspecialchars($ua) : '-';
                        ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php require __DIR__ . '/layout_footer.php'; ?>
