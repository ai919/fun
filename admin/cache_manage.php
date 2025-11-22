<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/CacheHelper.php';

$pageTitle = '缓存管理';
$pageSubtitle = '查看和管理系统缓存文件';
$activeMenu = 'system';

$cacheDir = __DIR__ . '/../cache';

// 确保缓存目录存在
if (!is_dir($cacheDir)) {
    @mkdir($cacheDir, 0755, true);
}

// 处理操作
$action = $_GET['action'] ?? '';
$message = '';
$messageType = '';

if ($action === 'clear') {
    $key = $_GET['key'] ?? '';
    if ($key) {
        // 清除单个缓存
        if (CacheHelper::delete($key)) {
            $message = '缓存已清除';
            $messageType = 'success';
        } else {
            $message = '清除失败';
            $messageType = 'error';
        }
    } else {
        // 清除所有缓存
        if (CacheHelper::clear()) {
            $message = '所有缓存已清除';
            $messageType = 'success';
        } else {
            $message = '清除失败';
            $messageType = 'error';
        }
    }
    header('Location: cache_manage.php?msg=' . urlencode($message) . '&type=' . $messageType);
    exit;
}

// 显示消息
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'info';
}

// 获取所有缓存文件
$cacheFiles = glob($cacheDir . '/*.cache');
$cacheList = [];

foreach ($cacheFiles as $file) {
    $key = basename($file, '.cache');
    $size = filesize($file);
    $modified = filemtime($file);
    $age = time() - $modified;
    
    // 尝试读取缓存内容以判断是否过期
    $content = @file_get_contents($file);
    $data = @unserialize($content);
    $isExpired = false;
    if ($data === false) {
        $isExpired = true;
    }
    
    $cacheList[] = [
        'key' => $key,
        'size' => $size,
        'modified' => $modified,
        'age' => $age,
        'isExpired' => $isExpired,
    ];
}

// 按修改时间排序（最新的在前）
usort($cacheList, function($a, $b) {
    return $b['modified'] - $a['modified'];
});

// 统计信息
$totalSize = array_sum(array_column($cacheList, 'size'));
$totalCount = count($cacheList);
$expiredCount = count(array_filter($cacheList, fn($c) => $c['isExpired']));

ob_start();
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType === 'success' ? 'success' : ($messageType === 'error' ? 'danger' : 'info') ?>" style="margin-bottom: 16px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <span class="admin-table__muted">管理文件缓存，提升系统性能。过期缓存会自动清理。</span>
    </div>
    <div class="admin-toolbar__right">
        <a href="?action=clear" 
           class="btn btn-primary"
           onclick="return confirm('确认清除所有缓存？');">
            清除所有缓存
        </a>
    </div>
</div>

<!-- 缓存统计 -->
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">缓存统计</h2>
    <table class="admin-table admin-table--kpi">
        <tbody>
        <tr>
            <td>
                <div class="admin-kpi-number"><?= $totalCount ?></div>
                <div class="admin-kpi-label">缓存文件数</div>
            </td>
            <td>
                <div class="admin-kpi-number">
                    <?php
                    if ($totalSize > 1024 * 1024) {
                        echo number_format($totalSize / (1024 * 1024), 2) . ' MB';
                    } elseif ($totalSize > 1024) {
                        echo number_format($totalSize / 1024, 2) . ' KB';
                    } else {
                        echo $totalSize . ' B';
                    }
                    ?>
                </div>
                <div class="admin-kpi-label">总大小</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: <?= $expiredCount > 0 ? '#f59e0b' : '#34d399' ?>;">
                    <?= $expiredCount ?>
                </div>
                <div class="admin-kpi-label">过期缓存</div>
            </td>
        </tr>
        </tbody>
    </table>
</div>

<!-- 缓存列表 -->
<div class="admin-card">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">缓存列表</h2>
    
    <?php if (empty($cacheList)): ?>
        <div style="padding: 40px; text-align: center; color: #9ca3af;">
            <div style="font-size: 48px; margin-bottom: 16px;">📦</div>
            <div>暂无缓存文件</div>
        </div>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th style="width: 40%;">缓存键</th>
                <th style="width: 120px;">大小</th>
                <th style="width: 150px;">修改时间</th>
                <th style="width: 120px;">年龄</th>
                <th style="width: 100px;">状态</th>
                <th style="width: 120px;">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($cacheList as $cache): ?>
                <tr>
                    <td>
                        <code class="code-badge" style="font-size: 11px;">
                            <?= htmlspecialchars($cache['key']) ?>
                        </code>
                    </td>
                    <td>
                        <?php
                        if ($cache['size'] > 1024 * 1024) {
                            echo number_format($cache['size'] / (1024 * 1024), 2) . ' MB';
                        } elseif ($cache['size'] > 1024) {
                            echo number_format($cache['size'] / 1024, 2) . ' KB';
                        } else {
                            echo $cache['size'] . ' B';
                        }
                        ?>
                    </td>
                    <td><?= date('Y-m-d H:i:s', $cache['modified']) ?></td>
                    <td>
                        <?php
                        if ($cache['age'] < 60) {
                            echo $cache['age'] . ' 秒前';
                        } elseif ($cache['age'] < 3600) {
                            echo floor($cache['age'] / 60) . ' 分钟前';
                        } elseif ($cache['age'] < 86400) {
                            echo floor($cache['age'] / 3600) . ' 小时前';
                        } else {
                            echo floor($cache['age'] / 86400) . ' 天前';
                        }
                        ?>
                    </td>
                    <td>
                        <?php if ($cache['isExpired']): ?>
                            <span style="color: #f59e0b; font-size: 12px;">已过期</span>
                        <?php else: ?>
                            <span style="color: #34d399; font-size: 12px;">有效</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <a href="?action=clear&key=<?= urlencode($cache['key']) ?>" 
                           class="btn btn-xs"
                           style="background:#b91c1c;color:#fff;border:none;"
                           onclick="return confirm('确认删除此缓存？');">
                            删除
                        </a>
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

