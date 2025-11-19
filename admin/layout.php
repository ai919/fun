<?php
// admin/layout.php
// 提供 admin_header() / admin_footer() 两个函数给后台页面使用

if (!function_exists('admin_header')) {
    function admin_header(string $title = '后台 · fun_quiz'): void
    {
        ?>
        <!doctype html>
        <html lang="zh-CN">
        <head>
            <meta charset="utf-8">
            <title><?= htmlspecialchars($title) ?></title>
            <link rel="stylesheet" href="/assets/css/style.css">
            <link rel="icon" type="image/x-icon" href="/favicon.ico">
            <link rel="shortcut icon" href="/favicon.ico">
            <style>
                /* 通用后台布局样式，尽量简单一点 */
                * { box-sizing: border-box; }
                html, body {
                    margin: 0;
                    padding: 0;
                    font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, "PingFang SC", "Microsoft YaHei", sans-serif;
                    background: #f3f4f6;
                }
                a { color: #2563eb; text-decoration: none; }
                a:hover { text-decoration: underline; }

                .admin-wrap {
                    display: flex;
                    min-height: 100vh;
                }

                .admin-sidebar {
                    width: 220px;
                    background: #111827;
                    color: #e5e7eb;
                    padding: 16px 0;
                    display: flex;
                    flex-direction: column;
                }

                .admin-logo {
                    font-size: 18px;
                    font-weight: 600;
                    padding: 0 20px 16px;
                    color: #f9fafb;
                }

                .admin-logo small {
                    display: block;
                    font-size: 12px;
                    color: #9ca3af;
                    margin-top: 2px;
                }

                .admin-menu {
                    flex: 1;
                    padding: 8px 0;
                }

                .admin-menu a {
                    display: block;
                    padding: 8px 20px;
                    font-size: 14px;
                    color: #e5e7eb;
                }

                .admin-menu a:hover {
                    background: #1f2937;
                }

                .admin-menu a.active {
                    background: #2563eb;
                    color: #f9fafb;
                }

                .admin-sidebar-footer {
                    padding: 8px 20px;
                    border-top: 1px solid #1f2937;
                    font-size: 12px;
                    color: #6b7280;
                }

                .admin-main {
                    flex: 1;
                    display: flex;
                    flex-direction: column;
                }

                .admin-topbar {
                    height: 50px;
                    background: #ffffff;
                    border-bottom: 1px solid #e5e7eb;
                    display: flex;
                    align-items: center;
                    justify-content: space-between;
                    padding: 0 20px;
                    position: sticky;
                    top: 0;
                    z-index: 10;
                }

                .admin-topbar-title {
                    font-size: 15px;
                    font-weight: 500;
                }

                .admin-topbar-right {
                    font-size: 13px;
                    color: #6b7280;
                }

                .admin-topbar-right a {
                    color: #4b5563;
                }

                .admin-content {
                    padding: 20px 24px 32px;
                }
            </style>
        </head>
        <body>
        <div class="admin-wrap">
            <aside class="admin-sidebar">
                <div class="admin-logo">
                    fun_quiz 后台
                    <small>Fun Tests Admin</small>
                </div>
                <nav class="admin-menu">
                    <?php
                    $path = $_SERVER['SCRIPT_NAME'] ?? '';
                    $isTests   = str_contains($path, '/admin/tests.php');
                    $isNew     = str_contains($path, '/admin/new_test.php');
                    $isClone   = str_contains($path, '/admin/clone_test.php');
                    $isStats   = str_contains($path, '/admin/stats.php');
                    ?>
                    <a href="/admin/tests.php" class="<?= $isTests ? 'active' : '' ?>">📋 测试列表</a>
                    <a href="/admin/new_test.php" class="<?= $isNew ? 'active' : '' ?>">➕ 新增测试</a>
                    <a href="/admin/clone_test.php" class="<?= $isClone ? 'active' : '' ?>">📂 克隆测试</a>
                    <a href="/admin/stats.php" class="<?= $isStats ? 'active' : '' ?>">📊 数据统计</a>
                </nav>
                <div class="admin-sidebar-footer">
                    <div><a href="/" target="_blank">打开前台首页</a></div>
                    <div><a href="/admin/logout.php">退出登录</a></div>
                </div>
            </aside>

            <main class="admin-main">
                <header class="admin-topbar">
                    <div class="admin-topbar-title"><?= htmlspecialchars($title) ?></div>
                    <div class="admin-topbar-right">
                        已登录 · <a href="/admin/logout.php">退出</a>
                    </div>
                </header>
                <div class="admin-content">
        <?php
    }

    function admin_footer(): void
    {
        ?>
                </div> <!-- admin-content -->
            </main>
        </div> <!-- admin-wrap -->
        </body>
        </html>
        <?php
    }
}
