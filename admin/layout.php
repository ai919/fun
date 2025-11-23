<?php
require_once __DIR__ . '/auth.php';

if (!isset($pageTitle)) {
    $pageTitle = 'DoFun心理实验空间 后台';
}
if (!isset($activeMenu)) {
    $activeMenu = '';
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title><?= htmlspecialchars($pageTitle) ?> · DoFun心理实验空间 Admin</title>
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
                <div class="admin-logo-text__title">DoFun心理实验空间 后台</div>
                <div class="admin-logo-text__sub">在线测验实验室管理</div>
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
            <a href="admin_users.php"
               class="admin-nav__item <?= $activeMenu === 'admin_users' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">👥</span>
                <span class="admin-nav__label">管理员</span>
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
            <a href="system.php"
               class="admin-nav__item <?= $activeMenu === 'system' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">⚙️</span>
                <span class="admin-nav__label">系统管理</span>
            </a>
            <a href="motivational_quotes.php"
               class="admin-nav__item <?= $activeMenu === 'quotes' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">💬</span>
                <span class="admin-nav__label">心理名言</span>
            </a>
            <a href="seo_settings.php"
               class="admin-nav__item <?= $activeMenu === 'seo' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">🔍</span>
                <span class="admin-nav__label">SEO 设置</span>
            </a>
            <a href="seo_optimizer.php"
               class="admin-nav__item <?= $activeMenu === 'seo_optimizer' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">📊</span>
                <span class="admin-nav__label">SEO 优化器</span>
            </a>
            <a href="ad_positions.php"
               class="admin-nav__item <?= $activeMenu === 'ads' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">📢</span>
                <span class="admin-nav__label">广告位管理</span>
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
            <span>DoFun心理实验空间 Admin · <?= date('Y') ?></span>
            <span class="admin-footer__dot">·</span>
            <span>轻量测验管理后台</span>
        </footer>
    </div>
</div>

</body>
<script>
document.addEventListener('DOMContentLoaded', function () {
    document.querySelectorAll('.rte-toolbar[data-rte-for]').forEach(function (toolbar) {
        var editorId = toolbar.getAttribute('data-rte-for');
        var editor = document.getElementById(editorId);
        if (!editor) return;

        var form = editor.closest('form');
        if (!form) return;

        var hidden = form.querySelector('.rte-hidden-textarea[name="description"]');
        if (!hidden) return;

        if (hidden.value && editor.innerHTML.trim() === '') {
            editor.innerHTML = hidden.value;
        } else if (!hidden.value && editor.innerHTML.trim() !== '') {
            hidden.value = editor.innerHTML;
        }

        function syncHidden() {
            hidden.value = editor.innerHTML;
        }

        toolbar.addEventListener('click', function (e) {
            var btn = e.target.closest('[data-cmd]');
            if (!btn) return;
            var cmd = btn.getAttribute('data-cmd');
            var val = btn.getAttribute('data-value') || null;

            editor.focus();

            if (cmd === 'createLink') {
                var url = window.prompt('请输入链接URL（例如：https://example.com）');
                if (url) {
                    if (!/^https?:\/\//i.test(url)) {
                        url = 'https://' + url;
                    }
                    document.execCommand('createLink', false, url);
                }
            } else if (cmd === 'insertImage') {
                var imgUrl = window.prompt('请输入图片URL');
                if (imgUrl) {
                    document.execCommand('insertImage', false, imgUrl);
                }
            } else if (cmd === 'foreColor' || cmd === 'backColor') {
                if (val) {
                    document.execCommand(cmd, false, val);
                }
            } else {
                document.execCommand(cmd, false, null);
            }

            syncHidden();
        });

        var emojiPicker = toolbar.querySelector('.rte-emoji-picker');
        if (emojiPicker) {
            emojiPicker.addEventListener('change', function () {
                var emoji = this.value;
                if (!emoji) return;
                editor.focus();
                document.execCommand('insertText', false, emoji);
                this.value = '';
                syncHidden();
            });
        }

        editor.addEventListener('input', syncHidden);
        editor.addEventListener('blur', syncHidden);
        form.addEventListener('submit', function () {
            syncHidden();
        });
    });
});
</script>
</html>
<?php
if (!defined('IN_ADMIN')) {
    define('IN_ADMIN', true);
}
