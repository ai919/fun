<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/db_connect.php';

$pageTitle = '结果管理';
$pageSubtitle = '为指定测验管理结果文案与区间';
$activeMenu = 'results';

// 参数解析：test_id 或 slug
$testId = isset($_GET['test_id']) ? (int)$_GET['test_id'] : null;
$slug   = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';

if (!$testId && $slug !== '') {
    $stmt = $pdo->prepare("SELECT id FROM tests WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $testId = (int)$row['id'];
    }
}

// 无参数时展示测验列表，供选择要管理的测验
if (!$testId) {
    $stmt = $pdo->query("
        SELECT t.id, t.title, t.subtitle, t.slug, t.status
        FROM tests t
        ORDER BY sort_order DESC, id DESC
    ");
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

    ob_start();
    ?>
    <div class="admin-toolbar">
        <div class="admin-toolbar__left">
            <span class="admin-table__muted">请先选择一个测验，再进行结果管理：</span>
        </div>
        <div class="admin-toolbar__right">
            <a href="new_test.php" class="btn btn-primary">+ 新建测验</a>
        </div>
    </div>

    <div class="admin-card">
        <table class="admin-table">
            <thead>
            <tr>
                <th>ID</th>
                <th>标题</th>
                <th>slug</th>
                <th>状态</th>
                <th>操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tests as $test): ?>
                <tr>
                    <td><?= (int)$test['id'] ?></td>
                    <td>
                        <div class="admin-table__title"><?= htmlspecialchars($test['title']) ?></div>
                        <?php if (!empty($test['subtitle'])): ?>
                            <div class="admin-table__subtitle"><?= htmlspecialchars($test['subtitle']) ?></div>
                        <?php endif; ?>
                    </td>
                    <td><code class="code-badge"><?= htmlspecialchars($test['slug']) ?></code></td>
                    <td>
                        <?php
                        $status = $test['status'];
                        $statusLabel = [
                            'draft'     => '草稿',
                            'published' => '已发布',
                            'archived'  => '已归档',
                        ][$status] ?? $status;
                        ?>
                        <span class="badge badge--<?= htmlspecialchars($status) ?>"><?= $statusLabel ?></span>
                    </td>
                    <td class="admin-table__actions">
                        <a href="questions.php?test_id=<?= (int)$test['id'] ?>" class="btn btn-xs btn-ghost">管理题目</a>
                        <a href="results.php?test_id=<?= (int)$test['id'] ?>" class="btn btn-xs">管理结果</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
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

$errors  = [];
$success = null;

ob_start();

function normalize_result_payload(array $data): array
{
    $code        = strtolower(trim($data['code'] ?? ''));
    $title       = trim($data['title'] ?? '');
    $description = trim($data['description'] ?? '');
    $imageUrl    = trim($data['image_url'] ?? '');
    $minScore    = (int)($data['min_score'] ?? 0);
    $maxScore    = (int)($data['max_score'] ?? 0);

    return [
        'code'        => $code,
        'title'       => $title,
        'description' => $description,
        'image_url'   => $imageUrl !== '' ? $imageUrl : null,
        'min_score'   => $minScore,
        'max_score'   => $maxScore,
    ];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_result') {
        $payload = normalize_result_payload($_POST);
        if ($payload['code'] === '' || !preg_match('/^[a-z0-9_-]+$/', $payload['code'])) {
            $errors[] = '结果 code 不能为空，只能使用小写字母、数字、短横线或下划线。';
        }
        if ($payload['title'] === '') {
            $errors[] = '结果标题不能为空。';
        }
        if ($payload['min_score'] > $payload['max_score']) {
            $errors[] = '最小分不能大于最大分。';
        }
        if (!$errors) {
            $dup = $pdo->prepare("SELECT COUNT(*) FROM results WHERE test_id = ? AND code = ?");
            $dup->execute([$testId, $payload['code']]);
            if ($dup->fetchColumn() > 0) {
                $errors[] = '该 code 已存在，请换一个标识。';
            }
        }
        if (!$errors) {
            $ins = $pdo->prepare(
                "INSERT INTO results (test_id, code, title, description, image_url, min_score, max_score)
                 VALUES (:test_id, :code, :title, :description, :image_url, :min_score, :max_score)"
            );
            $ins->execute([
                ':test_id'     => $testId,
                ':code'        => $payload['code'],
                ':title'       => $payload['title'],
                ':description' => $payload['description'],
                ':image_url'   => $payload['image_url'],
                ':min_score'   => $payload['min_score'],
                ':max_score'   => $payload['max_score'],
            ]);
            $success = '结果区间已创建。';
        }
    }

    if ($action === 'edit_result') {
        $resultId = (int)($_POST['result_id'] ?? 0);
        $payload  = normalize_result_payload($_POST);
        if (!$resultId) {
            $errors[] = '缺少结果 ID。';
        }
        if ($payload['code'] === '' || !preg_match('/^[a-z0-9_-]+$/', $payload['code'])) {
            $errors[] = '结果 code 不能为空，只能使用小写字母、数字、短横线或下划线。';
        }
        if ($payload['title'] === '') {
            $errors[] = '结果标题不能为空。';
        }
        if ($payload['min_score'] > $payload['max_score']) {
            $errors[] = '最小分不能大于最大分。';
        }
        if (!$errors && $resultId) {
            $dup = $pdo->prepare("SELECT COUNT(*) FROM results WHERE test_id = ? AND code = ? AND id != ?");
            $dup->execute([$testId, $payload['code'], $resultId]);
            if ($dup->fetchColumn() > 0) {
                $errors[] = '该 code 已存在，请换一个标识。';
            }
        }
        if (!$errors && $resultId) {
            $upd = $pdo->prepare(
                "UPDATE results
                 SET code = :code,
                     title = :title,
                     description = :description,
                     image_url = :image_url,
                     min_score = :min_score,
                     max_score = :max_score
                 WHERE id = :id AND test_id = :test_id"
            );
            $upd->execute([
                ':code'        => $payload['code'],
                ':title'       => $payload['title'],
                ':description' => $payload['description'],
                ':image_url'   => $payload['image_url'],
                ':min_score'   => $payload['min_score'],
                ':max_score'   => $payload['max_score'],
                ':id'          => $resultId,
                ':test_id'     => $testId,
            ]);
            $success = '结果已更新。';
        }
    }

    if ($action === 'delete_result') {
        $resultId = (int)($_POST['result_id'] ?? 0);
        if (!$resultId) {
            $errors[] = '缺少结果 ID。';
        } else {
            $del = $pdo->prepare("DELETE FROM results WHERE id = ? AND test_id = ?");
            $del->execute([$resultId, $testId]);
            $success = '结果已删除。';
        }
    }
}

$resultsStmt = $pdo->prepare(
    "SELECT *
     FROM results
     WHERE test_id = ?
     ORDER BY min_score ASC, id ASC"
);
$resultsStmt->execute([$testId]);
$results = $resultsStmt->fetchAll(PDO::FETCH_ASSOC);

$runStmt = $pdo->prepare(
    "SELECT tr.*, res.title AS result_title
     FROM test_runs tr
     LEFT JOIN results res ON res.id = tr.result_id
     WHERE tr.test_id = ?
     ORDER BY tr.id DESC
     LIMIT 20"
);
$runStmt->execute([$testId]);
$runs = $runStmt->fetchAll(PDO::FETCH_ASSOC);

$runScores = [];
if ($runs) {
    $runIds = array_column($runs, 'id');
    $place  = implode(',', array_fill(0, count($runIds), '?'));
    $scoreStmt = $pdo->prepare(
        "SELECT s.*, tr.id AS run_id
         FROM test_run_scores s
         JOIN test_runs tr ON tr.id = s.test_run_id
         WHERE s.test_run_id IN ($place)
         ORDER BY s.test_run_id DESC, s.dimension_key ASC"
    );
    $scoreStmt->execute($runIds);
    while ($row = $scoreStmt->fetch(PDO::FETCH_ASSOC)) {
        $rid = (int)$row['run_id'];
        if (!isset($runScores[$rid])) {
            $runScores[$rid] = [];
        }
        $runScores[$rid][] = $row;
    }
}

$pageTitle    = '结果配置 - ' . ($test['title'] ?? '');
$pageHeading  = '结果区间配置';
$pageSubtitle = '设置总分区间与展示文案。slug: ' . ($test['slug'] ?? '');
$activeMenu   = 'tests';

require __DIR__ . '/layout.php';
?>

<div class="section-card">
    <?php if ($errors): ?>
        <div class="alert alert-danger">
            <?php foreach ($errors as $err): ?>
                <div><?= htmlspecialchars($err) ?></div>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?php if ($success): ?>
        <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if (!$results): ?>
        <p class="hint">还没有配置任何结果，请先新增一个分数区间。</p>
    <?php else: ?>
        <?php foreach ($results as $res): ?>
            <form method="post" class="card" style="margin-bottom:12px;">
                <input type="hidden" name="result_id" value="<?= (int)$res['id'] ?>">
                <div class="form-grid">
                    <label>
                        <span>结果代码 (code)</span>
                        <input type="text" name="code" value="<?= htmlspecialchars($res['code']) ?>" required>
                    </label>
                    <label>
                        <span>标题</span>
                        <input type="text" name="title" value="<?= htmlspecialchars($res['title']) ?>" required>
                    </label>
                </div>
                <div class="form-grid">
                    <label>
                        <span>最小分</span>
                        <input type="number" name="min_score" value="<?= (int)$res['min_score'] ?>" required>
                    </label>
                    <label>
                        <span>最大分</span>
                        <input type="number" name="max_score" value="<?= (int)$res['max_score'] ?>" required>
                    </label>
                </div>
                <label>
                    <span>描述</span>
                    <textarea name="description" rows="3"><?= htmlspecialchars($res['description']) ?></textarea>
                </label>
                <label>
                    <span>图片 URL（可选）</span>
                    <input type="text" name="image_url" value="<?= htmlspecialchars($res['image_url']) ?>" placeholder="https://">
                </label>
                <div class="form-actions">
                    <button type="submit" class="btn btn-primary" name="action" value="edit_result">保存</button>
                    <button type="submit"
                            class="btn btn-danger"
                            name="action"
                            value="delete_result"
                            onclick="return confirm('确定要删除这个结果吗？');"
                            formnovalidate>删除</button>
                </div>
            </form>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<div class="section-card">
    <h3>新增结果区间</h3>
    <form method="post" class="form-grid">
        <input type="hidden" name="action" value="add_result">
        <label>
            <span>结果代码 (code)</span>
            <input type="text" name="code" placeholder="例如 secure / wild" required>
        </label>
        <label>
            <span>标题</span>
            <input type="text" name="title" required>
        </label>
        <label>
            <span>最小分</span>
            <input type="number" name="min_score" required>
        </label>
        <label>
            <span>最大分</span>
            <input type="number" name="max_score" required>
        </label>
        <label>
            <span>描述</span>
            <textarea name="description" rows="3" placeholder="描述这个区间的状态、建议等"></textarea>
        </label>
        <label>
            <span>图片 URL（可选）</span>
            <input type="text" name="image_url" placeholder="https://">
        </label>
        <button type="submit" class="btn btn-primary">添加结果</button>
    </form>
</div>

<div class="section-card">
    <h3>最近 20 条测评</h3>
    <?php if (!$runs): ?>
        <p class="hint">暂无历史记录。</p>
    <?php else: ?>
        <?php foreach ($runs as $run): ?>
            <div class="run-log">
                <div class="run-log-head">
                    <div>
                        <strong>#<?= (int)$run['id'] ?></strong>
                        <span class="hint"><?= htmlspecialchars($run['created_at']) ?></span>
                    </div>
                    <div>
                        结果：<?= htmlspecialchars($run['result_title'] ?? '未匹配') ?>
                        （总分 <?= (int)$run['total_score'] ?>）
                    </div>
                </div>
                <?php if (!empty($runScores[$run['id']])): ?>
                    <ul class="run-score-list">
                        <?php foreach ($runScores[$run['id']] as $score): ?>
                            <li>
                                <code><?= htmlspecialchars($score['dimension_key'] ?? '-') ?></code>
                                <span><?= (float)$score['score_value'] ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
