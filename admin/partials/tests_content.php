<?php
$errors      = [];
$successMsg  = null;

$statusLabel = [
    'draft'     => ['label' => '草稿', 'class' => 'badge-muted'],
    'published' => ['label' => '已发布', 'class' => 'badge-success'],
    'archived'  => ['label' => '已归档', 'class' => 'badge-warning'],
];

$statusFilterOptions = [
    ''           => '全部状态',
    'draft'      => '草稿',
    'published'  => '已发布',
    'archived'   => '已归档',
];

$orderOptions = [
    'updated_desc' => '按更新时间倒序',
    'created_desc' => '按创建时间倒序',
    'order_asc'    => '按排序值升序',
];

$msgKey = $_GET['msg'] ?? '';
if ($msgKey === 'deleted') {
    $successMsg = '测试已删除。';
} elseif ($msgKey === 'saved') {
    $successMsg = '测试保存成功。';
}

if (($_GET['action'] ?? '') === 'delete') {
    $deleteId = (int)($_GET['id'] ?? 0);
    if ($deleteId <= 0) {
        $errors[] = '缺少测试 ID。';
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

$listSql = "SELECT t.id, t.title, t.slug, t.status, t.tags, t.sort_order, t.created_at, t.updated_at
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
            <a href="/admin/test_edit.php" class="btn btn-success">+ 新建测试</a>
        </div>
    </form>
</div>

<?php if (!$tests): ?>
    <p class="hint">当前没有可展示的测试，点击“新建测试”开始创建吧。</p>
<?php else: ?>
    <table class="table-admin" style="margin-top:16px;">
        <thead>
        <tr>
            <th style="width:60px;">ID</th>
            <th>标题</th>
            <th style="width:160px;">Slug</th>
            <th style="width:120px;">状态</th>
            <th style="width:200px;">标签</th>
            <th style="width:150px;">更新时间</th>
            <th style="width:140px;">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tests as $test): ?>
            <?php
            $statusValue = (string)$test['status'];
            if (!isset($statusLabel[$statusValue]) && in_array($statusValue, ['0', '1', '2'], true)) {
                $map = ['0' => 'draft', '1' => 'published', '2' => 'archived'];
                $statusValue = $map[$statusValue] ?? 'draft';
            }
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
                <td><code><?= htmlspecialchars($test['slug']) ?></code></td>
                <td>
                    <span class="badge <?= $badgeInfo['class'] ?>">
                        <?= htmlspecialchars($badgeInfo['label']) ?>
                    </span>
                </td>
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
                       onclick="return confirm('确认删除这个测试？其下题目也会被移除。');">删除</a>
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
