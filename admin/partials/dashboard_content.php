<?php
$config = require __DIR__ . '/../../backup_config.php';
$backupUrl = '/backup.php?token=' . urlencode($config['token']);
?>
<h2 class="admin-dashboard-welcome">欢迎来到 DoFun心理实验空间 控制台</h2>
<p class="admin-dashboard-intro">
    在这里你可以管理测验、查看备份记录，并逐步把 DoFun心理实验空间 打磨成一个真正的性格实验平台。
    建议定期点击「立即备份」保存一份完整的 ZIP。
</p>

<div class="admin-dashboard-grid">
    <div class="admin-dashboard-card admin-dashboard-card--quicklinks">
        <div class="admin-dashboard-card__label">快捷入口</div>
        <div class="admin-dashboard-card__links">
            <a href="/admin/tests.php" class="admin-dashboard-link">→ 管理测验</a>
            <a href="/admin/new_test.php" class="admin-dashboard-link">→ 新建测验</a>
            <a href="/admin/backup_logs.php" class="admin-dashboard-link admin-dashboard-link--primary">→ 查看备份记录</a>
            <a href="<?= htmlspecialchars($backupUrl) ?>" class="admin-dashboard-link admin-dashboard-link--warning" target="_blank">→ 立即备份整站</a>
        </div>
    </div>
    <div class="admin-dashboard-card admin-dashboard-card--info">
        <div class="admin-dashboard-card__label">开发提醒</div>
        <p class="admin-dashboard-card__text">
            当前仍在逐步迁移旧版后台页面。登录框与布局已经升级为新样式，
            接下来可以逐页把测验管理、结果管理等模块迁入该系统。
        </p>
    </div>
</div>
