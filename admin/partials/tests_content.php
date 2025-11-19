<?php
$errors      = [];
$successMsg  = null;
$statusLabel = [
    'draft'     => ['label' => '�׸�', 'class' => 'badge-muted'],
    'published' => ['label' => '�ѷ���', 'class' => 'badge-success'],
    'archived'  => ['label' => '�ѹǼ�', 'class' => 'badge-warning'],
];
$statusFilterOptions = [
    ''           => 'ȫ��״̬',
    'draft'      => '�׸�',
    'published'  => '�ѷ���',
    'archived'   => '�ѹǼ�',
];
$orderOptions = [
    'updated_desc' => '������ʱ������',
    'created_desc' => '��������ʱ������',
    'order_asc'    => '����ֵ����С��',
];

$msgKey = $_GET['msg'] ?? '';
if ($msgKey === 'deleted') {
    $successMsg = '����ɾ���ɹ���';
} elseif ($msgKey === 'saved') {
    $successMsg = '�����ѱ��浽���顣';
}

if (($_GET['action'] ?? '') === 'delete') {
    $deleteId = (int)($_GET['id'] ?? 0);
    if ($deleteId <= 0) {
        $errors[] = '����ȷ��Ĳ��� ID��';
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

$baseQuery = [
    'q'      => $keyword,
    'status' => $statusFilter,
    'order'  => $orderKey,
];
$filterQuery = array_filter($baseQuery, function ($value) {
    return $value !== '' && $value !== null;
});
$pageQuery = $filterQuery;
$pageQuery['page'] = $page;
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
            <label>����</label>
            <input type="text" name="q" value="<?= htmlspecialchars($keyword) ?>" placeholder="������ title �� slug">
        </div>
        <div class="filter-item">
            <label>״̬</label>
            <select name="status">
                <?php foreach ($statusFilterOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"<?= $statusFilter === $value ? ' selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-item">
            <label>����</label>
            <select name="order">
                <?php foreach ($orderOptions as $value => $label): ?>
                    <option value="<?= htmlspecialchars($value) ?>"<?= $orderKey === $value ? ' selected' : '' ?>>
                        <?= htmlspecialchars($label) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-actions">
            <button type="submit" class="btn btn-primary">��������</button>
            <a href="/admin/tests.php" class="btn btn-ghost btn-xs">�������</a>
        </div>
        <div class="filter-actions">
            <a href="/admin/test_edit.php" class="btn btn-success">+ �½�����</a>
        </div>
    </form>
</div>

<?php if (!$tests): ?>
    <p class="hint">��ǰû�пɹ����Ĳ��ԣ�����Ԥ�����½�����ʹ��Ӱ����</p>
<?php else: ?>
    <table class="table-admin" style="margin-top:16px;">
        <thead>
        <tr>
            <th style="width:60px;">ID</th>
            <th>����</th>
            <th style="width:160px;">Slug</th>
            <th style="width:120px;">״̬</th>
            <th style="width:200px;">��ǩ</th>
            <th style="width:150px;">ʱ��</th>
            <th style="width:120px;">����</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tests as $test): ?>
            <?php
            $statusValue = (string)$test['status'];
            if (!isset($statusLabel[$statusValue]) && in_array($statusValue, ['0', '1', '2'], true)) {
                $map = ['0' => 'draft', '1' => 'published', '2' => 'archived'];
                $statusValue = $map[$statusValue];
            }
            $badgeInfo = $statusLabel[$statusValue] ?? ['label' => 'δ֪', 'class' => 'badge-muted'];
            $tags = array_filter(array_map('trim', explode(',', (string)$test['tags'])));
            $tags = array_slice($tags, 0, 3);
            $updatedAt = $test['updated_at'] ?? null;
            $createdAt = $test['created_at'] ?? null;
            $timeToShow = $updatedAt && $updatedAt !== '0000-00-00 00:00:00' ? $updatedAt : $createdAt;
            $timeDisplay = null;
            if ($timeToShow && $timeToShow !== '0000-00-00 00:00:00') {
                $timestamp = strtotime($timeToShow);
                if ($timestamp) {
                    $timeDisplay = date('Y-m-d H:i', $timestamp);
                }
            }
            ?>
            <tr>
                <td><?= (int)$test['id'] ?></td>
                <td>
                    <div class="test-title-text"><?= htmlspecialchars($test['title']) ?></div>
                    <div class="muted">����ֵ: <?= (int)$test['sort_order'] ?></div>
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
                        <span class="muted">--</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($timeDisplay): ?>
                        <?= htmlspecialchars($timeDisplay) ?>
                    <?php else: ?>
                        <span class="muted">--</span>
                    <?php endif; ?>
                </td>
                <td class="actions">
                    <a class="btn-mini" href="/admin/test_edit.php?id=<?= (int)$test['id'] ?>">�༭</a>
                    <a class="btn-mini danger-btn" href="/admin/tests.php?action=delete&id=<?= (int)$test['id'] ?>"
                       onclick="return confirm('ȷ��ɾ�������Բ��ԣ���غ����޷��ָ���');">ɾ��</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>

    <?php if ($totalRows > $perPage): ?>
        <div class="pagination">
            <span>�� <?= $page ?> / <?= $totalPages ?> ҳ��</span>
            <?php if ($page > 1): ?>
                <?php
                $prevQuery = $filterQuery;
                $prevQuery['page'] = $page - 1;
                ?>
                <a class="btn btn-ghost btn-xs" href="/admin/tests.php?<?= http_build_query($prevQuery) ?>">��һҳ</a>
            <?php endif; ?>
            <?php if ($page < $totalPages): ?>
                <?php
                $nextQuery = $filterQuery;
                $nextQuery['page'] = $page + 1;
                ?>
                <a class="btn btn-ghost btn-xs" href="/admin/tests.php?<?= http_build_query($nextQuery) ?>">��һҳ</a>
            <?php endif; ?>
        </div>
    <?php endif; ?>
<?php endif; ?>
