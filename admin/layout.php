<?php
require_once __DIR__ . '/auth.php';

if (!isset($pageTitle)) {
    $pageTitle = 'DoFun 后台';
}
if (!isset($activeMenu)) {
    $activeMenu = '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?> · DoFun Admin</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="shortcut icon" href="../favicon.ico">
    <link rel="stylesheet" href="../assets/css/admin.css?v=20251120">
</head>
<body class="admin-body">
<div class="admin-shell">

    <!-- 左侧侧边栏 -->
    <aside class="admin-sidebar">
        <div class="admin-sidebar__logo">
            <div class="admin-logo-mark">DF</div>
            <div class="admin-logo-text">
                <div class="admin-logo-text__title">DoFun 后台</div>
                <div class="admin-logo-text__sub">在线趣味测试管理</div>
            </div>
        </div>

        <nav class="admin-nav">
            <a href="index.php"
               class="admin-nav__item <?= $activeMenu === 'dashboard' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">📊</span>
                <span class="admin-nav__label">概览</span>
            </a>
            <a href="tests.php"
               class="admin-nav__item <?= $activeMenu === 'tests' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">🧪</span>
                <span class="admin-nav__label">测验管理</span>
            </a>
            <a href="stats.php"
               class="admin-nav__item <?= $activeMenu === 'stats' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">📈</span>
                <span class="admin-nav__label">统计</span>
            </a>
            <a href="backup_logs.php"
               class="admin-nav__item <?= $activeMenu === 'backup' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">💾</span>
                <span class="admin-nav__label">备份 & 日志</span>
            </a>
        </nav>

        <div class="admin-sidebar__footer">
            <a href="logout.php" class="admin-nav__item admin-nav__item--muted">
                <span class="admin-nav__icon">🚪</span>
                <span class="admin-nav__label">退出登录</span>
            </a>
            <div class="admin-sidebar__meta">
                <span class="admin-meta-key">环境</span>
                <span class="admin-meta-value">
                    <?= htmlspecialchars(php_uname('n')) ?>
                </span>
            </div>
        </div>
    </aside>

    <!-- 右侧主区域 -->
    <div class="admin-main">
        <!-- 顶栏 -->
        <header class="admin-topbar">
            <div class="admin-topbar__left">
                <h1 class="admin-page-title"><?= htmlspecialchars($pageTitle) ?></h1>
                <?php if (!empty($pageSubtitle ?? '')): ?>
                    <p class="admin-page-subtitle"><?= htmlspecialchars($pageSubtitle) ?></p>
                <?php endif; ?>
            </div>
            <div class="admin-topbar__right">
                <span class="admin-topbar__user">👤 管理员</span>
                <a class="admin-topbar__link" href="../" target="_blank">打开前台</a>
            </div>
        </header>

        <!-- 内容 -->
        <main class="admin-content">
            <?php
            // 兼容两种用法：
            // 1）页面通过 $content 注入
            // 2）页面直接 echo 出内容（layout 只做外壳）
            if (isset($content)) {
                echo $content;
            } elseif (isset($contentFile) && file_exists($contentFile)) {
                include $contentFile;
            }
            ?>
        </main>

        <footer class="admin-footer">
            <span>DoFun Admin · <?= date('Y') ?></span>
            <span class="admin-footer__dot">·</span>
            <span>轻量测验管理后台</span>
        </footer>
    </div>
</div>

</body>
</html>
