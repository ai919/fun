<?php
$config = require __DIR__ . '/../../backup_config.php';
$backupUrl = '/backup.php?token=' . urlencode($config['token']);
?>
<h2 style="margin-top:0;font-size:18px;margin-bottom:10px;">欢迎来到 DoFun 控制台</h2>
<p style="font-size:13px;color:#9ca3af;margin-bottom:16px;">
    在这里你可以管理测验、查看备份记录，并逐步把 DoFun 打磨成一个真正的性格实验平台。
    建议定期点击「立即备份」保存一份完整的 ZIP。
</p>

<div style="display:flex;flex-wrap:wrap;gap:12px;">
    <div style="flex:1;min-width:220px;background:#020617;border-radius:10px;padding:14px 16px;border:1px solid rgba(55,65,81,0.85);">
        <div style="font-size:12px;color:#9ca3af;margin-bottom:6px;">快捷入口</div>
        <div style="display:flex;flex-direction:column;gap:6px;">
            <a href="/admin/tests.php" style="font-size:13px;color:#e5e7eb;text-decoration:none;">→ 管理测验</a>
            <a href="/admin/new_test.php" style="font-size:13px;color:#e5e7eb;text-decoration:none;">→ 新建测验</a>
            <a href="/admin/backup_logs.php" style="font-size:13px;color:#a5b4fc;text-decoration:none;">→ 查看备份记录</a>
            <a href="<?= htmlspecialchars($backupUrl) ?>" style="font-size:13px;color:#fbbf24;text-decoration:none;" target="_blank">→ 立即备份整站</a>
        </div>
    </div>
    <div style="flex:2;min-width:220px;background:#020617;border-radius:10px;padding:14px 16px;border:1px solid rgba(55,65,81,0.85);">
        <div style="font-size:12px;color:#9ca3af;margin-bottom:6px;">开发提醒</div>
        <p style="font-size:13px;color:#cbd5f5;line-height:1.6;margin:0;">
            当前仍在逐步迁移旧版后台页面。登录框与布局已经升级为新样式，
            接下来可以逐页把测验管理、结果管理等模块迁入该系统。
        </p>
    </div>
</div>
