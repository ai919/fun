<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/SettingsHelper.php';

$pageTitle = 'Google Analytics 设置';
$pageSubtitle = '配置 Google Analytics 跟踪代码';
$activeMenu = 'system';

// 处理保存
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $enabled = isset($_POST['ga_enabled']) && $_POST['ga_enabled'] === '1';
    $code = trim($_POST['ga_code'] ?? '');
    
    // 保存设置
    $success = true;
    $success = $success && SettingsHelper::set('google_analytics_enabled', $enabled ? '1' : '0', '是否启用 Google Analytics');
    $success = $success && SettingsHelper::set('google_analytics_code', $code, 'Google Analytics 跟踪代码');
    
    if ($success) {
        SettingsHelper::clearCache();
        $message = '设置已保存成功！';
        $messageType = 'success';
    } else {
        $message = '保存失败，请重试。';
        $messageType = 'error';
    }
}

// 获取当前设置
$gaEnabled = SettingsHelper::isGoogleAnalyticsEnabled();
$gaCode = SettingsHelper::getGoogleAnalyticsCode();

ob_start();
?>

<?php if ($message): ?>
    <div class="admin-message admin-message--<?= $messageType ?>" style="margin-bottom: 16px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <div class="admin-card" style="margin-bottom: 16px;">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 16px;">Google Analytics 配置</h2>
        
        <div style="margin-bottom: 20px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 12px;">
                <input type="checkbox" name="ga_enabled" value="1" <?= $gaEnabled ? 'checked' : '' ?>>
                <span style="font-size: 13px; font-weight: 600; color: #e5e7eb;">
                    启用 Google Analytics
                </span>
            </label>
            <p style="font-size: 12px; color: #9ca3af; margin: 0; padding-left: 24px;">
                启用后，Google Analytics 代码将自动插入到所有前台页面的 &lt;head&gt; 标签中
            </p>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #e5e7eb;">
                Google Analytics 代码
            </label>
            <div style="margin-bottom: 8px;">
                <textarea 
                    name="ga_code" 
                    rows="8" 
                    style="width: 100%; padding: 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px; font-family: 'Consolas', 'Monaco', 'Courier New', monospace; resize: vertical;"
                    placeholder="请输入 Google Analytics 代码...&#10;&#10;方式一：输入 GA4 测量 ID（如：G-XXXXXXXXXX）&#10;方式二：输入完整的 Google Analytics 脚本代码"
                ><?= htmlspecialchars($gaCode) ?></textarea>
            </div>
            <div style="font-size: 12px; color: #9ca3af; line-height: 1.6;">
                <p style="margin: 0 0 8px 0;"><strong>支持两种输入方式：</strong></p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li style="margin-bottom: 4px;">
                        <strong>方式一（推荐）：</strong>输入 GA4 测量 ID，格式为 <code style="background: rgba(55,65,81,0.5); padding: 2px 6px; border-radius: 3px;">G-XXXXXXXXXX</code>
                        <br>系统会自动生成完整的跟踪脚本
                    </li>
                    <li style="margin-bottom: 4px;">
                        <strong>方式二：</strong>输入完整的 Google Analytics 脚本代码
                        <br>适用于需要自定义配置或使用 Universal Analytics 的情况
                    </li>
                </ul>
            </div>
        </div>
        
        <div style="margin-bottom: 20px; padding: 12px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 6px;">
            <div style="font-size: 12px; color: #93c5fd; line-height: 1.6;">
                <p style="margin: 0 0 8px 0; font-weight: 600;">💡 如何获取 Google Analytics 代码？</p>
                <ol style="margin: 0; padding-left: 20px;">
                    <li style="margin-bottom: 4px;">访问 <a href="https://analytics.google.com" target="_blank" style="color: #60a5fa; text-decoration: underline;">Google Analytics</a> 并登录</li>
                    <li style="margin-bottom: 4px;">创建或选择一个属性（Property）</li>
                    <li style="margin-bottom: 4px;">在"管理" → "数据流"中找到您的网站数据流</li>
                    <li style="margin-bottom: 4px;">复制"测量 ID"（格式：G-XXXXXXXXXX）</li>
                    <li style="margin-bottom: 4px;">将测量 ID 粘贴到上方输入框中</li>
                </ol>
            </div>
        </div>
    </div>
    
    <div class="admin-toolbar">
        <div class="admin-toolbar__left">
            <span class="admin-table__muted">设置保存后，Google Analytics 代码将自动出现在所有前台页面中。</span>
        </div>
        <div class="admin-toolbar__right">
            <button type="submit" class="btn btn-primary">保存设置</button>
        </div>
    </div>
</form>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';

