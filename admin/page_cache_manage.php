<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/PageCache.php';
require_once __DIR__ . '/../config/app.php';

$pageTitle = '页面缓存管理';
$pageSubtitle = '查看和管理页面级别缓存';
$activeMenu = 'system';

$config = require __DIR__ . '/../config/app.php';
$cacheDir = $config['cache']['page_dir'] ?? __DIR__ . '/../cache/pages';

// 处理操作
$action = $_GET['action'] ?? '';
$message = '';
$messageType = '';

if ($action === 'clear') {
    $tag = $_GET['tag'] ?? '';
    if ($tag) {
        // 清除指定标签的缓存
        if (PageCache::clearByTag($tag)) {
            $message = '标签 "' . htmlspecialchars($tag) . '" 的缓存已清除';
            $messageType = 'success';
        } else {
            $message = '清除失败';
            $messageType = 'error';
        }
    } else {
        // 清除所有页面缓存
        if (PageCache::clear()) {
            $message = '所有页面缓存已清除';
            $messageType = 'success';
        } else {
            $message = '清除失败';
            $messageType = 'error';
        }
    }
    header('Location: page_cache_manage.php?msg=' . urlencode($message) . '&type=' . $messageType);
    exit;
}

// 显示消息
if (isset($_GET['msg'])) {
    $message = $_GET['msg'];
    $messageType = $_GET['type'] ?? 'info';
}

// 获取缓存统计
$stats = PageCache::getStats();

// 获取所有缓存文件
$cacheFiles = [];
if (is_dir($cacheDir)) {
    $files = glob($cacheDir . '/*.html');
    foreach ($files as $file) {
        $filename = basename($file);
        $size = filesize($file);
        $modified = filemtime($file);
        $age = time() - $modified;
        
        // 尝试从元数据文件获取信息
        $metaFile = str_replace('.html', '.meta', $file);
        $hash = basename($filename, '.html');
        $tag = 'default';
        $timestamp = $modified;
        $isExpired = false;
        
        if (file_exists($metaFile)) {
            $meta = @json_decode(file_get_contents($metaFile), true);
            if ($meta) {
                $tag = $meta['tags'][0] ?? 'default';
                $timestamp = $meta['created_at'] ?? $modified;
                $isExpired = time() > ($meta['expires_at'] ?? PHP_INT_MAX);
            }
        } else {
            // 如果没有元数据文件，尝试从文件名解析（旧格式）
            $parts = explode('_', $filename);
            $tag = $parts[1] ?? 'default';
            $timestamp = isset($parts[2]) ? (int)str_replace('.html', '', $parts[2]) : $modified;
            $ttl = $config['cache']['page_ttl'] ?? 300;
            $isExpired = ($timestamp + $ttl) < time();
        }
        
        $cacheFiles[] = [
            'file' => $file,
            'filename' => $filename,
            'hash' => $hash,
            'tag' => $tag,
            'size' => $size,
            'modified' => $modified,
            'timestamp' => $timestamp,
            'age' => $age,
            'expires' => $isExpired ? $timestamp : ($timestamp + ($config['cache']['page_ttl'] ?? 300)),
            'isExpired' => $isExpired,
        ];
    }
}

// 按修改时间排序（最新的在前）
usort($cacheFiles, function($a, $b) {
    return $b['modified'] - $a['modified'];
});

// 按标签分组统计
$tagStats = [];
foreach ($cacheFiles as $cache) {
    $tag = $cache['tag'];
    if (!isset($tagStats[$tag])) {
        $tagStats[$tag] = [
            'count' => 0,
            'size' => 0,
            'expired' => 0,
        ];
    }
    $tagStats[$tag]['count']++;
    $tagStats[$tag]['size'] += $cache['size'];
    if ($cache['isExpired']) {
        $tagStats[$tag]['expired']++;
    }
}

ob_start();
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType === 'success' ? 'success' : ($messageType === 'error' ? 'danger' : 'info') ?>" style="margin-bottom: 16px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <span class="admin-table__muted">管理页面级别缓存，提升页面加载速度。</span>
    </div>
    <div class="admin-toolbar__right">
        <a href="?action=clear" 
           class="btn btn-primary"
           onclick="return confirm('确认清除所有页面缓存？');">
            清除所有缓存
        </a>
    </div>
</div>

<!-- 缓存统计 -->
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">页面缓存统计</h2>
    <table class="admin-table admin-table--kpi">
        <tbody>
        <tr>
            <td>
                <div class="admin-kpi-number"><?= $stats['total_files'] ?? 0 ?></div>
                <div class="admin-kpi-label">缓存文件数</div>
            </td>
            <td>
                <div class="admin-kpi-number">
                    <?php
                    $totalSize = $stats['total_size'] ?? 0;
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
                <div class="admin-kpi-number" style="color: <?= ($stats['valid_files'] ?? 0) > 0 ? '#34d399' : '#9ca3af' ?>;">
                    <?= $stats['valid_files'] ?? 0 ?>
                </div>
                <div class="admin-kpi-label">有效缓存</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: <?= ($stats['expired_files'] ?? 0) > 0 ? '#f59e0b' : '#34d399' ?>;">
                    <?= $stats['expired_files'] ?? 0 ?>
                </div>
                <div class="admin-kpi-label">过期缓存</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: <?= ($config['cache']['page_enabled'] ?? false) ? '#34d399' : '#9ca3af' ?>;">
                    <?= ($config['cache']['page_enabled'] ?? false) ? '已启用' : '未启用' ?>
                </div>
                <div class="admin-kpi-label">缓存状态</div>
            </td>
        </tr>
        </tbody>
    </table>
</div>

<!-- 按标签统计 -->
<?php if (!empty($tagStats)): ?>
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">按标签统计</h2>
    <table class="admin-table">
        <thead>
        <tr>
            <th style="width: 200px;">标签</th>
            <th style="width: 120px;">文件数</th>
            <th style="width: 120px;">总大小</th>
            <th style="width: 120px;">过期数</th>
            <th style="width: 120px;">操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tagStats as $tag => $stat): ?>
            <tr>
                <td>
                    <code class="code-badge"><?= htmlspecialchars($tag) ?></code>
                </td>
                <td><?= $stat['count'] ?></td>
                <td>
                    <?php
                    if ($stat['size'] > 1024 * 1024) {
                        echo number_format($stat['size'] / (1024 * 1024), 2) . ' MB';
                    } elseif ($stat['size'] > 1024) {
                        echo number_format($stat['size'] / 1024, 2) . ' KB';
                    } else {
                        echo $stat['size'] . ' B';
                    }
                    ?>
                </td>
                <td>
                    <span style="color: <?= $stat['expired'] > 0 ? '#f59e0b' : '#34d399' ?>;">
                        <?= $stat['expired'] ?>
                    </span>
                </td>
                <td>
                    <a href="?action=clear&tag=<?= urlencode($tag) ?>" 
                       class="btn btn-xs"
                       style="background:#b91c1c;color:#fff;border:none;"
                       onclick="return confirm('确认清除标签 \"<?= htmlspecialchars($tag) ?>\" 的所有缓存？');">
                        清除标签
                    </a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<!-- 缓存文件列表 -->
<div class="admin-card">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">缓存文件列表</h2>
    
    <?php if (empty($cacheFiles)): ?>
        <div style="padding: 40px; text-align: center; color: #9ca3af;">
            <div style="font-size: 48px; margin-bottom: 16px;">📄</div>
            <div>暂无页面缓存文件</div>
        </div>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th style="width: 30%;">文件名</th>
                <th style="width: 150px;">标签</th>
                <th style="width: 120px;">大小</th>
                <th style="width: 150px;">创建时间</th>
                <th style="width: 150px;">过期时间</th>
                <th style="width: 100px;">状态</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($cacheFiles as $cache): ?>
                <tr>
                    <td>
                        <code class="code-badge" style="font-size: 11px;">
                            <?= htmlspecialchars($cache['filename']) ?>
                        </code>
                    </td>
                    <td>
                        <code class="code-badge"><?= htmlspecialchars($cache['tag']) ?></code>
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
                    <td><?= date('Y-m-d H:i:s', $cache['timestamp']) ?></td>
                    <td>
                        <?php
                        $expiresIn = $cache['expires'] - time();
                        if ($expiresIn < 0) {
                            echo '<span style="color: #f59e0b;">已过期</span>';
                        } else {
                            if ($expiresIn < 60) {
                                echo $expiresIn . ' 秒后';
                            } elseif ($expiresIn < 3600) {
                                echo floor($expiresIn / 60) . ' 分钟后';
                            } else {
                                echo date('H:i:s', $cache['expires']);
                            }
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
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- 配置信息 -->
<div class="admin-card" style="margin-top: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">缓存配置</h2>
    <table class="admin-table admin-table--compact">
        <tbody>
        <tr>
            <td style="width: 200px; color: #9ca3af;">缓存目录</td>
            <td><code class="code-badge" style="font-size: 11px;"><?= htmlspecialchars($cacheDir) ?></code></td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">缓存启用</td>
            <td>
                <span style="color: <?= ($config['cache']['page_enabled'] ?? false) ? '#34d399' : '#9ca3af' ?>;">
                    <?= ($config['cache']['page_enabled'] ?? false) ? '✓ 已启用' : '✗ 未启用' ?>
                </span>
            </td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">缓存 TTL</td>
            <td><code class="code-badge"><?= $config['cache']['page_ttl'] ?? 300 ?> 秒</code></td>
        </tr>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

