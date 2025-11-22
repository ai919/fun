<?php
require_once __DIR__ . '/../../lib/Constants.php';
$errors      = [];
$successMsg  = null;

/**
 * Check if a column exists in current DB schema.
 */
function admin_column_exists(PDO $pdo, string $table, string $column): bool
{
    static $cache = [];
    $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $key = "{$dbName}.{$table}.{$column}";
    if (array_key_exists($key, $cache)) {
        return $cache[$key];
    }
    $stmt = $pdo->prepare("
        SELECT 1
        FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
        LIMIT 1
    ");
    $stmt->execute([$dbName, $table, $column]);
    $cache[$key] = (bool)$stmt->fetchColumn();
    return $cache[$key];
}

$statusLabels = Constants::getTestStatusLabels();
$statusLabel = [
    Constants::TEST_STATUS_DRAFT     => ['label' => $statusLabels[Constants::TEST_STATUS_DRAFT], 'class' => 'badge-muted'],
    Constants::TEST_STATUS_PUBLISHED  => ['label' => $statusLabels[Constants::TEST_STATUS_PUBLISHED], 'class' => 'badge-success'],
    Constants::TEST_STATUS_ARCHIVED   => ['label' => $statusLabels[Constants::TEST_STATUS_ARCHIVED], 'class' => 'badge-warning'],
];

$statusFilterOptions = [
    ''                                => '全部状态',
    Constants::TEST_STATUS_DRAFT      => $statusLabels[Constants::TEST_STATUS_DRAFT],
    Constants::TEST_STATUS_PUBLISHED  => $statusLabels[Constants::TEST_STATUS_PUBLISHED],
    Constants::TEST_STATUS_ARCHIVED   => $statusLabels[Constants::TEST_STATUS_ARCHIVED],
];

$orderOptions = [
    'updated_desc' => '按更新时间倒序',
    'created_desc' => '按创建时间倒序',
    'order_asc'    => '按排序值升序',
];

$msgKey = $_GET['msg'] ?? '';
if ($msgKey === 'deleted') {
    $successMsg = '测验已删除。';
} elseif ($msgKey === 'saved') {
    $successMsg = '测验保存成功。';
}

if (($_GET['action'] ?? '') === 'delete') {
    $deleteId = (int)($_GET['id'] ?? 0);
    if ($deleteId <= 0) {
        $errors[] = '缺少测验 ID。';
    } else {
        $delStmt = $pdo->prepare('DELETE FROM tests WHERE id = :id LIMIT 1');
        $delStmt->execute([':id' => $deleteId]);
        header('Location: /admin/tests.php?msg=deleted');
        exit;
    }
}

$keyword = trim($_GET['q'] ?? '');
$statusFilter = $_GET['status'] ?? '';
$orderKey = $_GET['order'] ?? 'updated_desc';
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

$validOrders = [
    'updated_desc' => 't.updated_at DESC',
    'created_desc' => 't.created_at DESC',
    'order_asc'    => 't.sort_order ASC, t.id DESC',
];
if (!isset($validOrders[$orderKey])) {
    $orderKey = 'updated_desc';
}
$orderSql = ' ORDER BY ' . $validOrders[$orderKey];

$conditions = [];
$params = [];
if ($keyword !== '') {
    $conditions[] = '(t.title LIKE :keyword OR t.slug LIKE :keyword)';
    $params[':keyword'] = '%' . $keyword . '%';
}
if ($statusFilter !== '' && isset($statusFilterOptions[$statusFilter])) {
    $conditions[] = 't.status = :status';
    $params[':status'] = $statusFilter;
}
$whereSql = $conditions ? ' WHERE ' . implode(' AND ', $conditions) : '';

$countStmt = $pdo->prepare("SELECT COUNT(*) FROM tests t{$whereSql}");
foreach ($params as $name => $value) {
    $countStmt->bindValue($name, $value);
}
$countStmt->execute();
$totalRows = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($totalRows / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
}
$offset = ($page - 1) * $perPage;

$hasEmojiCol = admin_column_exists($pdo, 'tests', 'emoji');
$hasTitleColorCol = admin_column_exists($pdo, 'tests', 'title_color');
$selectCols = "t.id, t.title, t.slug, t.status, t.tags, t.sort_order, t.created_at, t.updated_at, t.scoring_mode";
$selectCols .= $hasEmojiCol ? ", t.emoji" : ", NULL AS emoji";
$selectCols .= $hasTitleColorCol ? ", t.title_color" : ", NULL AS title_color";

$listSql = "SELECT {$selectCols}
            FROM tests t{$whereSql}{$orderSql} LIMIT :limit OFFSET :offset";
$listStmt = $pdo->prepare($listSql);
foreach ($params as $name => $value) {
    $listStmt->bindValue($name, $value);
}
$listStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$listStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$listStmt->execute();
$tests = $listStmt->fetchAll(PDO::FETCH_ASSOC);

$filterQuery = array_filter([
    'q'      => $keyword,
    'status' => $statusFilter,
    'order'  => $orderKey,
], static fn($value) => $value !== '' && $value !== null);

?>

<?php if ($errors): ?>
    <div class="alert alert-danger">
        <?php foreach ($errors as $error): ?>
            <div><?= htmlspecialchars($error) ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php if ($successMsg): ?>
    <div class="alert alert-success"><?= htmlspecialchars($successMsg) ?></div>
<?php endif; ?>

<div class="card">
    <form method="get" class="filter-row">
        <div class="filter-item">
            <label>关键字</label>
            <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="搜索 title 或 slug">
        </div>
        <div class="filter-item">
            <label>状态</label>
            <select name="status">
                <?php foreach ($statusFilterOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"<?= $statusFilter === $value ? ' selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>排序</label>
            <select name="order">
                <?php foreach ($orderOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"<?= $orderKey === $value ? ' selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">筛选</button>
            <a href="/admin/tests.php" class="btn btn-ghost btn-xs">重置</a>
            <a href="/admin/test_edit.php" class="btn btn-success">+ 新建测验</a>
        </div>
    </form>
</div>

<?php if (!$tests): ?>
    <p class="hint">当前没有可展示的测验，点击“新建测验”开始创建吧。</p>
<?php else: ?>
    <table class="table-admin" style="margin-top:16px;">
        <thead>
        <tr>
            <th style="width:60px;">ID</th>
            <th>标题</th>
            <th style="width:80px;">Emoji</th>
            <th style="width:140px;">标题颜色</th>
            <th style="width:160px;">Slug</th>
            <th style="width:110px;">状态</th>
            <th style="width:120px;">评分模式</th>
            <th style="width:200px;">标签</th>
            <th style="width:150px;">更新时间</th>
            <th style="width:140px;">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tests as $test): ?>
            <?php
            $statusValue = Constants::normalizeTestStatus($test['status']);
            $badgeInfo = $statusLabel[$statusValue] ?? ['label' => '未知', 'class' => 'badge-muted'];
            $tags = array_filter(array_map('trim', explode(',', (string)$test['tags'])));
            $tags = array_slice($tags, 0, 3);
            $timeDisplay = $test['updated_at'] && $test['updated_at'] !== '0000-00-00 00:00:00'
                ? $test['updated_at']
                : ($test['created_at'] ?? '');
            ?>
            <tr>
                <td><?= (int)$test['id'] ?></td>
                <td>
                    <div><?= htmlspecialchars($test['title']) ?></div>
                    <div class="muted">排序值：<?= (int)$test['sort_order'] ?></div>
                </td>
                <td>
                    <?= ($test['emoji'] ?? '') !== '' ? htmlspecialchars($test['emoji']) : '—' ?>
                </td>
                <td>
                    <?php if (!empty($test['title_color'])): ?>
                        <span class="color-swatch" style="background: <?= htmlspecialchars($test['title_color']) ?>;"></span>
                        <span class="color-text"><?= htmlspecialchars($test['title_color']) ?></span>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td><code><?= htmlspecialchars($test['slug']) ?></code></td>
                <td>
                    <span class="badge <?= $badgeInfo['class'] ?>">
                        <?= htmlspecialchars($badgeInfo['label']) ?>
                    </span>
                </td>
                <td><?= htmlspecialchars($test['scoring_mode'] ?? Constants::SCORING_MODE_SIMPLE) ?></td>
                <td>
                    <?php if ($tags): ?>
                        <div class="tag-list">
                            <?php foreach ($tags as $tag): ?>
                                <span class="badge badge-muted"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <span class="muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?= $timeDisplay ? htmlspecialchars(date('Y-m-d H:i', strtotime($timeDisplay))) : '—' ?></td>
                <td class="actions">
                    <a class="btn-mini" href="/admin/test_edit.php?id=<?= (int)$test['id'] ?>">编辑</a>
                    <a class="btn-mini" href="/<?= urlencode($test['slug']) ?>" target="_blank">前台预览</a>
                    <a class="btn-mini danger-btn"
                       href="/admin/tests.php?action=delete&id=<?= (int)$test['id'] ?>"
                       onclick="return confirm('确认删除这个测验？其下题目也会被移除。');">删除</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalRows > $perPage): ?>
        <div class="pagination">
            <span>第 <?= $page ?> / <?= $totalPages ?> 页</span>
            <?php if ($page > 1): ?>
                <?php $prevQuery = $filterQuery; $prevQuery['page'] = $page - 1; ?>
                <a class="btn btn-ghost btn-xs" href="/admin/tests.php?<?= http_build_query($prevQuery) ?>">上一页</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <?php $nextQuery = $filterQuery; $nextQuery['page'] = $page + 1; ?>
                <a class="btn btn-ghost btn-xs" href="/admin/tests.php?<?= http_build_query($nextQuery) ?>">下一页</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
