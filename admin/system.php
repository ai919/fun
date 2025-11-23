<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../config/app.php';

$pageTitle = '系统管理';
$pageSubtitle = '系统配置、日志和缓存管理';
$activeMenu = 'system';

$config = require __DIR__ . '/../config/app.php';
$logDir = $config['log']['dir'] ?? __DIR__ . '/../logs';
$cacheDir = __DIR__ . '/../cache';

// 统计信息
$logStats = [];
$logLevels = ['error', 'warning', 'info', 'debug'];
foreach ($logLevels as $level) {
    $file = $logDir . '/' . $level . '.log';
    if (file_exists($file) && is_readable($file)) {
        $lines = @file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        $logStats[$level] = [
            'count' => $lines ? count($lines) : 0,
            'size' => filesize($file),
        ];
    } else {
        $logStats[$level] = ['count' => 0, 'size' => 0];
    }
}

$cacheFiles = glob($cacheDir . '/*.cache');
$cacheCount = count($cacheFiles);
$cacheSize = array_sum(array_map('filesize', $cacheFiles));

ob_start();
?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <span class="admin-table__muted">系统管理工具，用于监控和维护系统运行状态。</span>
    </div>
</div>

<div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 16px; margin-bottom: 16px;">
    <!-- 系统配置 -->
    <a href="system_config.php" style="text-decoration: none;">
        <div class="admin-card" style="height: 100%; transition: transform 0.2s; cursor: pointer;" 
             onmouseover="this.style.transform='translateY(-2px)'" 
             onmouseout="this.style.transform='translateY(0)'">
            <div style="font-size: 32px; margin-bottom: 12px;">⚙️</div>
            <h3 style="font-size: 16px; font-weight: 600; color: #e5e7eb; margin-bottom: 8px;">系统配置</h3>
            <p style="font-size: 13px; color: #9ca3af; margin-bottom: 12px; line-height: 1.5;">
                管理调试模式、日志级别、错误处理等系统配置
            </p>
            <div style="font-size: 12px; color: #60a5fa;">
                环境: <?= htmlspecialchars(strtoupper($config['environment'])) ?> · 
                调试: <?= $config['debug'] ? '开启' : '关闭' ?>
            </div>
        </div>
    </a>
    
    <!-- 系统日志 -->
    <a href="system_logs.php" style="text-decoration: none;">
        <div class="admin-card" style="height: 100%; transition: transform 0.2s; cursor: pointer;" 
             onmouseover="this.style.transform='translateY(-2px)'" 
             onmouseout="this.style.transform='translateY(0)'">
            <div style="font-size: 32px; margin-bottom: 12px;">📋</div>
            <h3 style="font-size: 16px; font-weight: 600; color: #e5e7eb; margin-bottom: 8px;">系统日志</h3>
            <p style="font-size: 13px; color: #9ca3af; margin-bottom: 12px; line-height: 1.5;">
                查看错误、警告、信息和调试日志
            </p>
            <div style="font-size: 12px; color: #60a5fa;">
                错误: <?= number_format($logStats['error']['count']) ?> · 
                警告: <?= number_format($logStats['warning']['count']) ?>
            </div>
        </div>
    </a>
    
    <!-- 缓存管理 -->
    <a href="cache_manage.php" style="text-decoration: none;">
        <div class="admin-card" style="height: 100%; transition: transform 0.2s; cursor: pointer;" 
             onmouseover="this.style.transform='translateY(-2px)'" 
             onmouseout="this.style.transform='translateY(0)'">
            <div style="font-size: 32px; margin-bottom: 12px;">📦</div>
            <h3 style="font-size: 16px; font-weight: 600; color: #e5e7eb; margin-bottom: 8px;">缓存管理</h3>
            <p style="font-size: 13px; color: #9ca3af; margin-bottom: 12px; line-height: 1.5;">
                查看和管理系统缓存文件，提升性能
            </p>
            <div style="font-size: 12px; color: #60a5fa;">
                文件数: <?= $cacheCount ?> · 
                总大小: <?php
                    if ($cacheSize > 1024 * 1024) {
                        echo number_format($cacheSize / (1024 * 1024), 2) . ' MB';
                    } elseif ($cacheSize > 1024) {
                        echo number_format($cacheSize / 1024, 2) . ' KB';
                    } else {
                        echo $cacheSize . ' B';
                    }
                ?>
            </div>
        </div>
    </a>
    
    <!-- 数据库迁移 -->
    <a href="migrations.php" style="text-decoration: none;">
        <div class="admin-card" style="height: 100%; transition: transform 0.2s; cursor: pointer;" 
             onmouseover="this.style.transform='translateY(-2px)'" 
             onmouseout="this.style.transform='translateY(0)'">
            <div style="font-size: 32px; margin-bottom: 12px;">🗄️</div>
            <h3 style="font-size: 16px; font-weight: 600; color: #e5e7eb; margin-bottom: 8px;">数据库迁移</h3>
            <p style="font-size: 13px; color: #9ca3af; margin-bottom: 12px; line-height: 1.5;">
                管理数据库结构变更，支持版本控制和回滚
            </p>
            <div style="font-size: 12px; color: #60a5fa;">
                迁移管理 · 版本控制
            </div>
        </div>
    </a>
    
    <!-- 数据库查询日志 -->
    <a href="db_query_logs.php" style="text-decoration: none;">
        <div class="admin-card" style="height: 100%; transition: transform 0.2s; cursor: pointer;" 
             onmouseover="this.style.transform='translateY(-2px)'" 
             onmouseout="this.style.transform='translateY(0)'">
            <div style="font-size: 32px; margin-bottom: 12px;">📊</div>
            <h3 style="font-size: 16px; font-weight: 600; color: #e5e7eb; margin-bottom: 8px;">查询日志</h3>
            <p style="font-size: 13px; color: #9ca3af; margin-bottom: 12px; line-height: 1.5;">
                查看数据库查询日志，分析查询性能和优化慢查询
            </p>
            <div style="font-size: 12px; color: #60a5fa;">
                性能分析 · 慢查询
            </div>
        </div>
    </a>
</div>

<!-- 系统状态 -->
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">系统状态</h2>
    <table class="admin-table admin-table--compact">
        <tbody>
        <tr>
            <td style="width: 150px; color: #9ca3af;">PHP 版本</td>
            <td><code class="code-badge"><?= PHP_VERSION ?></code></td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">服务器时间</td>
            <td><?= date('Y-m-d H:i:s') ?></td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">时区</td>
            <td><?= htmlspecialchars($config['timezone']) ?></td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">调试模式</td>
            <td>
                <span style="color: <?= $config['debug'] ? '#f59e0b' : '#34d399' ?>;">
                    <?= $config['debug'] ? '开启' : '关闭' ?>
                </span>
            </td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">日志级别</td>
            <td><code class="code-badge"><?= htmlspecialchars($config['log']['level']) ?></code></td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">日志目录</td>
            <td><code class="code-badge" style="font-size: 11px;"><?= htmlspecialchars($logDir) ?></code></td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">缓存目录</td>
            <td><code class="code-badge" style="font-size: 11px;"><?= htmlspecialchars($cacheDir) ?></code></td>
        </tr>
        </tbody>
    </table>
</div>

<!-- 数据库连接状态 -->
<?php
require_once __DIR__ . '/../lib/DatabaseConnection.php';
$dbStats = DatabaseConnection::getStats();
$dbConnected = DatabaseConnection::isConnected();

// 获取数据库信息
$dbInfo = [];
if ($dbConnected) {
    try {
        $dbInfo['version'] = $pdo->query('SELECT VERSION()')->fetchColumn();
        $dbInfo['database'] = $pdo->query('SELECT DATABASE()')->fetchColumn();
        $dbInfo['charset'] = $pdo->query('SELECT @@character_set_database')->fetchColumn();
        $dbInfo['collation'] = $pdo->query('SELECT @@collation_database')->fetchColumn();
        
        // 获取表数量
        $tableCount = $pdo->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE()")->fetchColumn();
        $dbInfo['tables'] = $tableCount;
        
        // 获取连接数
        $connectionCount = $pdo->query("SHOW STATUS LIKE 'Threads_connected'")->fetchColumn(1);
        $dbInfo['connections'] = $connectionCount;
    } catch (Exception $e) {
        $dbInfo['error'] = $e->getMessage();
    }
}

// 获取配置信息
require_once __DIR__ . '/../lib/Config.php';
Config::init();
$dbConfig = Config::get('db');
?>
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">
        数据库连接状态
        <span style="font-size: 12px; font-weight: normal; margin-left: 8px;">
            <span style="color: <?= $dbConnected ? '#34d399' : '#ef4444' ?>;">
                <?= $dbConnected ? '● 已连接' : '● 未连接' ?>
            </span>
        </span>
    </h2>
    <?php if ($dbConnected && !empty($dbInfo)): ?>
        <table class="admin-table admin-table--compact">
            <tbody>
            <tr>
                <td style="width: 150px; color: #9ca3af;">数据库版本</td>
                <td><code class="code-badge"><?= htmlspecialchars($dbInfo['version'] ?? 'N/A') ?></code></td>
            </tr>
            <tr>
                <td style="color: #9ca3af;">当前数据库</td>
                <td><code class="code-badge"><?= htmlspecialchars($dbInfo['database'] ?? 'N/A') ?></code></td>
            </tr>
            <tr>
                <td style="color: #9ca3af;">字符集</td>
                <td><code class="code-badge"><?= htmlspecialchars($dbInfo['charset'] ?? 'N/A') ?></code></td>
            </tr>
            <tr>
                <td style="color: #9ca3af;">排序规则</td>
                <td><code class="code-badge"><?= htmlspecialchars($dbInfo['collation'] ?? 'N/A') ?></code></td>
            </tr>
            <tr>
                <td style="color: #9ca3af;">数据表数量</td>
                <td><code class="code-badge"><?= htmlspecialchars($dbInfo['tables'] ?? 'N/A') ?></code></td>
            </tr>
            <tr>
                <td style="color: #9ca3af;">当前连接数</td>
                <td><code class="code-badge"><?= htmlspecialchars($dbInfo['connections'] ?? 'N/A') ?></code></td>
            </tr>
            <tr>
                <td style="color: #9ca3af;">持久连接</td>
                <td>
                    <span style="color: <?= $dbStats['persistent'] ? '#34d399' : '#9ca3af' ?>;">
                        <?= $dbStats['persistent'] ? '已启用' : '未启用' ?>
                    </span>
                </td>
            </tr>
            <tr>
                <td style="color: #9ca3af;">数据库主机</td>
                <td><code class="code-badge" style="font-size: 11px;"><?= htmlspecialchars($dbConfig['host'] ?? 'N/A') ?></code></td>
            </tr>
            </tbody>
        </table>
    <?php else: ?>
        <div style="padding: 20px; text-align: center; color: #9ca3af;">
            <div style="font-size: 32px; margin-bottom: 8px;">⚠️</div>
            <div>无法获取数据库信息</div>
            <?php if (isset($dbInfo['error'])): ?>
                <div style="font-size: 12px; color: #ef4444; margin-top: 8px;">
                    <?= htmlspecialchars($dbInfo['error']) ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>

<!-- 配置和类状态 -->
<?php
$configLoaded = class_exists('Config');
$databaseClassExists = class_exists('Database');
$responseClassExists = class_exists('Response');
$migrationClassExists = class_exists('Migration');
?>
<div class="admin-card">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">核心类状态</h2>
    <table class="admin-table admin-table--compact">
        <tbody>
        <tr>
            <td style="width: 150px; color: #9ca3af;">Config 类</td>
            <td>
                <span style="color: <?= $configLoaded ? '#34d399' : '#ef4444' ?>;">
                    <?= $configLoaded ? '● 已加载' : '● 未加载' ?>
                </span>
            </td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">Database 类</td>
            <td>
                <span style="color: <?= $databaseClassExists ? '#34d399' : '#ef4444' ?>;">
                    <?= $databaseClassExists ? '● 已加载' : '● 未加载' ?>
                </span>
            </td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">Response 类</td>
            <td>
                <span style="color: <?= $responseClassExists ? '#34d399' : '#ef4444' ?>;">
                    <?= $responseClassExists ? '● 已加载' : '● 未加载' ?>
                </span>
            </td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">Migration 类</td>
            <td>
                <span style="color: <?= $migrationClassExists ? '#34d399' : '#ef4444' ?>;">
                    <?= $migrationClassExists ? '● 已加载' : '● 未加载' ?>
                </span>
            </td>
        </tr>
        <tr>
            <td style="color: #9ca3af;">DatabaseConnection 类</td>
            <td>
                <span style="color: <?= class_exists('DatabaseConnection') ? '#34d399' : '#ef4444' ?>;">
                    <?= class_exists('DatabaseConnection') ? '● 已加载' : '● 未加载' ?>
                </span>
            </td>
        </tr>
        </tbody>
    </table>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

