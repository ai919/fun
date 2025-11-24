<?php
require_once __DIR__ . '/auth.php';
// 确保所有使用 layout 的页面都需要登录（双重保护）
if (!current_admin()) {
    header('Location: /admin/login.php');
    exit;
}

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
    <script src="../assets/js/theme-toggle.js"></script>
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
            <a href="quiz_import.php"
               class="admin-nav__item <?= $activeMenu === 'quiz_import' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">📥</span>
                <span class="admin-nav__label">测验导入</span>
            </a>
            <a href="ad_positions.php"
               class="admin-nav__item <?= $activeMenu === 'ads' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">📢</span>
                <span class="admin-nav__label">广告位管理</span>
            </a>
            <a href="test_beautify.php"
               class="admin-nav__item <?= $activeMenu === 'test_beautify' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">✨</span>
                <span class="admin-nav__label">数据美化</span>
            </a>
            <a href="site_settings.php"
               class="admin-nav__item <?= $activeMenu === 'site_settings' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">🌐</span>
                <span class="admin-nav__label">网站设置</span>
            </a>
            <a href="notifications.php"
               class="admin-nav__item <?= $activeMenu === 'notifications' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">🔔</span>
                <span class="admin-nav__label">通知管理</span>
            </a>
            <a href="motivational_quotes.php"
               class="admin-nav__item <?= $activeMenu === 'quotes' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">💬</span>
                <span class="admin-nav__label">心理名言</span>
            </a>
            <a href="users.php"
               class="admin-nav__item <?= $activeMenu === 'users' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">👤</span>
                <span class="admin-nav__label">用户管理</span>
            </a>
            <a href="stats.php"
               class="admin-nav__item <?= $activeMenu === 'stats' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">📈</span>
                <span class="admin-nav__label">数据统计</span>
            </a>
            <a href="seo_optimizer.php"
               class="admin-nav__item <?= $activeMenu === 'seo_optimizer' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">📊</span>
                <span class="admin-nav__label">SEO 优化器</span>
            </a>
            <a href="admin_users.php"
               class="admin-nav__item <?= $activeMenu === 'admin_users' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">👥</span>
                <span class="admin-nav__label">管理员</span>
            </a>
            <a href="system.php"
               class="admin-nav__item <?= $activeMenu === 'system' ? 'is-active' : '' ?>">
                <span class="admin-nav__icon">⚙️</span>
                <span class="admin-nav__label">系统管理</span>
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
                <button type="button" id="theme-toggle-btn" class="theme-toggle-btn" aria-label="切换主题" title="切换暗色/亮色模式">
                    <span class="theme-icon-light">☀️</span>
                    <span class="theme-icon-dark">🌙</span>
                </button>
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
        var emojiDropdown = toolbar.querySelector('.emoji-dropdown-grid');
        if (emojiPicker && emojiDropdown) {
            // 阻止原生select的下拉显示，改用自定义下拉网格
            emojiPicker.addEventListener('mousedown', function(e) {
                e.preventDefault();
                var isVisible = emojiDropdown.style.display === 'grid';
                // 关闭所有其他emoji下拉
                document.querySelectorAll('.emoji-dropdown-grid').forEach(function(dropdown) {
                    if (dropdown !== emojiDropdown) {
                        dropdown.style.display = 'none';
                    }
                });
                emojiDropdown.style.display = isVisible ? 'none' : 'grid';
            });
            
            // 阻止键盘操作打开原生下拉
            emojiPicker.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    var isVisible = emojiDropdown.style.display === 'grid';
                    emojiDropdown.style.display = isVisible ? 'none' : 'grid';
                }
            });
            
            // 点击下拉项时插入emoji到编辑器
            emojiDropdown.querySelectorAll('.emoji-dropdown-item').forEach(function(item) {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var emoji = this.getAttribute('data-emoji');
                    if (emoji) {
                        editor.focus();
                        document.execCommand('insertText', false, emoji);
                        emojiDropdown.style.display = 'none';
                        emojiPicker.value = '';
                        syncHidden();
                    }
                });
            });
            
            // 点击外部关闭下拉
            document.addEventListener('click', function(e) {
                var wrapper = toolbar.querySelector('.rte-emoji-picker-wrapper');
                if (wrapper && !wrapper.contains(e.target) && !emojiDropdown.contains(e.target)) {
                    emojiDropdown.style.display = 'none';
                }
            });
        }

        // 颜色选择器
        var colorTrigger = toolbar.querySelector('.rte-color-trigger');
        var colorPicker = toolbar.querySelector('.rte-color-picker');
        if (colorTrigger && colorPicker) {
            colorTrigger.addEventListener('click', function (e) {
                e.preventDefault();
                e.stopPropagation();
                var isVisible = colorPicker.style.display !== 'none';
                // 关闭所有其他颜色选择器
                document.querySelectorAll('.rte-color-picker').forEach(function (picker) {
                    picker.style.display = 'none';
                });
                colorPicker.style.display = isVisible ? 'none' : 'block';
            });

            colorPicker.querySelectorAll('.rte-color-btn').forEach(function (btn) {
                btn.addEventListener('click', function (e) {
                    e.preventDefault();
                    e.stopPropagation();
                    var color = this.getAttribute('data-color');
                    var cmd = colorTrigger.getAttribute('data-cmd');
                    editor.focus();
                    document.execCommand(cmd, false, color);
                    colorPicker.style.display = 'none';
                    syncHidden();
                });
            });

            // 点击外部关闭颜色选择器
            document.addEventListener('click', function (e) {
                if (!colorTrigger.contains(e.target) && !colorPicker.contains(e.target)) {
                    colorPicker.style.display = 'none';
                }
            });
        }

        editor.addEventListener('input', syncHidden);
        editor.addEventListener('blur', syncHidden);
        form.addEventListener('submit', function () {
            syncHidden();
        });
    });
    
    // Emoji下拉选择器
    var emojiSelect = document.getElementById('emoji-select');
    var emojiDropdown = document.getElementById('emoji-dropdown-grid');
    if (emojiSelect && emojiDropdown) {
        // 阻止原生select的下拉显示，改用自定义下拉网格
        emojiSelect.addEventListener('mousedown', function(e) {
            e.preventDefault();
            var isVisible = emojiDropdown.style.display === 'grid';
            emojiDropdown.style.display = isVisible ? 'none' : 'grid';
        });
        
        // 阻止键盘操作打开原生下拉
        emojiSelect.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                var isVisible = emojiDropdown.style.display === 'grid';
                emojiDropdown.style.display = isVisible ? 'none' : 'grid';
            }
        });
        
        // 点击下拉项时选择
        emojiDropdown.querySelectorAll('.emoji-dropdown-item').forEach(function(item) {
            item.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var emoji = this.getAttribute('data-emoji');
                emojiSelect.value = emoji || '';
                emojiDropdown.style.display = 'none';
                // 触发change事件以便其他代码可以监听
                emojiSelect.dispatchEvent(new Event('change', { bubbles: true }));
            });
        });
        
        // 点击外部关闭下拉
        document.addEventListener('click', function(e) {
            if (!emojiSelect.contains(e.target) && !emojiDropdown.contains(e.target)) {
                emojiDropdown.style.display = 'none';
            }
        });
    }
    
    // 主题切换按钮事件
    const themeBtn = document.getElementById('theme-toggle-btn');
    if (themeBtn) {
        themeBtn.addEventListener('click', function() {
            window.ThemeToggle.toggle();
        });
    }
});
</script>
</html>
<?php
if (!defined('IN_ADMIN')) {
    define('IN_ADMIN', true);
}
