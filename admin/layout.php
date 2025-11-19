<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$pageTitle    = $pageTitle    ?? 'DoFun 管理后台';
$pageHeading  = $pageHeading  ?? '';
$pageSubtitle = $pageSubtitle ?? '';
$activeMenu   = $activeMenu   ?? '';
$flashSuccess = $flashSuccess ?? null;
$flashError   = $flashError   ?? null;
$currentAdmin = $currentAdmin ?? (function_exists('current_admin') ? current_admin() : null);
$scriptName   = $_SERVER['SCRIPT_NAME'] ?? '';
$isTestsNavActive = (strpos($scriptName, 'tests.php') !== false) || ($activeMenu === 'tests');

$capturedContent = null;
$hasContentFile  = !empty($contentFile) && file_exists($contentFile);
if ($hasContentFile) {
    ob_start();
    include $contentFile;
    $capturedContent = ob_get_clean();
}
?>
<!doctype html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/assets/css/admin.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" href="/favicon.ico">
</head>
<body class="admin-body">

<div class="admin-shell">
    <aside class="admin-sidebar">
        <div class="admin-brand">
            <div class="brand-logo">DoFun 空间 · 后台</div>
            <div class="brand-subtitle">DoFun Admin Panel</div>
        </div>
        <nav class="admin-nav">
            <a href="/admin/index.php" class="nav-item <?= $activeMenu === 'dashboard' ? 'is-active' : '' ?>">
                <span class="nav-icon">🏠</span>
                <span class="nav-label">控制台</span>
            </a>
            <a href="/admin/tests.php" class="nav-item <?= $isTestsNavActive ? 'is-active' : '' ?>">
                <span class="nav-icon">📋</span>
                <span class="nav-label">测试列表</span>
            </a>
            <a href="/admin/new_test.php" class="nav-item <?= $activeMenu === 'new' ? 'is-active' : '' ?>">
                <span class="nav-icon">➕</span>
                <span class="nav-label">新增测试</span>
            </a>
            <a href="/admin/clone_test.php" class="nav-item <?= $activeMenu === 'clone' ? 'is-active' : '' ?>">
                <span class="nav-icon">✨</span>
                <span class="nav-label">克隆测试</span>
            </a>
            <a href="/admin/stats.php" class="nav-item <?= $activeMenu === 'stats' ? 'is-active' : '' ?>">
                <span class="nav-icon">📈</span>
                <span class="nav-label">数据统计</span>
            </a>
            <a href="/admin/backup_logs.php" class="nav-item <?= $activeMenu === 'backups' ? 'is-active' : '' ?>">
                <span class="nav-icon">💾</span>
                <span class="nav-label">备份记录</span>
            </a>
        </nav>
        <div class="admin-sidebar-footer">
            <a href="/" class="sidebar-link" target="_blank">打开前台首页</a>
            <a href="/admin/logout.php" class="sidebar-link">退出登录</a>
        </div>
    </aside>

    <main class="admin-main">
        <header class="admin-topbar">
            <div class="topbar-left">
                <div class="topbar-breadcrumb"><?= htmlspecialchars($pageTitle) ?></div>
            </div>
            <div class="topbar-right">
                <span class="topbar-user"><?= htmlspecialchars($currentAdmin['display_name'] ?? $currentAdmin['username'] ?? '管理员') ?></span>
                <a href="/admin/logout.php" class="btn btn-ghost btn-xs">退出</a>
            </div>
        </header>

        <section class="admin-content">
            <?php if ($pageHeading !== ''): ?>
                <div class="page-header">
                    <h1 class="page-title"><?= htmlspecialchars($pageHeading) ?></h1>
                    <?php if ($pageSubtitle !== ''): ?>
                        <p class="page-subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if ($flashSuccess): ?>
                <div class="alert alert-success"><?= htmlspecialchars($flashSuccess) ?></div>
            <?php endif; ?>

            <?php if ($flashError): ?>
                <div class="alert alert-danger"><?= htmlspecialchars($flashError) ?></div>
            <?php endif; ?>

            <?php
            if ($hasContentFile && $capturedContent !== null) {
                echo $capturedContent;
            }
            ?>
        </section>
    </main>
</div>

</body>
</html>
<?php
if (!defined('ADMIN_LAYOUT_CLOSED')) {
    define('ADMIN_LAYOUT_CLOSED', true);
}
