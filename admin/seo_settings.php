<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/SettingsHelper.php';
require_once __DIR__ . '/../lib/csrf.php';

$pageTitle = 'SEO 设置';
$pageSubtitle = '管理全局 SEO 配置，包括站点名称、默认标题、描述等';
$activeMenu = 'seo';

$message = '';
$messageType = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        http_response_code(403);
        die('CSRF token 验证失败，请刷新页面后重试');
    }

    $settings = [
        'seo_site_name' => trim($_POST['seo_site_name'] ?? ''),
        'seo_default_title' => trim($_POST['seo_default_title'] ?? ''),
        'seo_default_description' => trim($_POST['seo_default_description'] ?? ''),
        'seo_default_image' => trim($_POST['seo_default_image'] ?? ''),
        'seo_default_keywords' => trim($_POST['seo_default_keywords'] ?? ''),
        'seo_robots_default' => trim($_POST['seo_robots_default'] ?? 'index,follow'),
        'seo_og_type_default' => trim($_POST['seo_og_type_default'] ?? 'website'),
        'seo_twitter_card' => trim($_POST['seo_twitter_card'] ?? 'summary_large_image'),
    ];

    $success = true;
    foreach ($settings as $key => $value) {
        $description = match($key) {
            'seo_site_name' => '网站名称（用于 SEO title 和 OG site_name）',
            'seo_default_title' => '默认页面标题',
            'seo_default_description' => '默认页面描述（meta description）',
            'seo_default_image' => '默认 OG 图片 URL（留空则使用 /assets/img/dofun-poster-bg.jpg）',
            'seo_default_keywords' => '默认关键词（meta keywords）',
            'seo_robots_default' => '默认 robots 设置',
            'seo_og_type_default' => '默认 OG type',
            'seo_twitter_card' => 'Twitter Card 类型',
            default => null,
        };
        
        if (!SettingsHelper::set($key, $value, $description)) {
            $success = false;
        }
    }

    if ($success) {
        $message = 'SEO 设置已保存';
        $messageType = 'success';
    } else {
        $message = '保存失败，请重试';
        $messageType = 'error';
    }
}

// 获取当前设置
$currentSettings = [
    'seo_site_name' => SettingsHelper::get('seo_site_name', 'DoFun心理实验空间'),
    'seo_default_title' => SettingsHelper::get('seo_default_title', 'DoFun心理实验空间｜心理 性格 性情：更专业的在线测验实验室'),
    'seo_default_description' => SettingsHelper::get('seo_default_description', 'DoFun心理实验空间，是一个轻量、有趣的在线测验实验室，提供人格、情感、社交、生活方式等多个方向的心理小测试，帮你以更轻松的方式认识自己。'),
    'seo_default_image' => SettingsHelper::get('seo_default_image', ''),
    'seo_default_keywords' => SettingsHelper::get('seo_default_keywords', '心理测试,性格测试,在线测验,心理实验,人格测试'),
    'seo_robots_default' => SettingsHelper::get('seo_robots_default', 'index,follow'),
    'seo_og_type_default' => SettingsHelper::get('seo_og_type_default', 'website'),
    'seo_twitter_card' => SettingsHelper::get('seo_twitter_card', 'summary_large_image'),
];

ob_start();
?>

<?php if ($message): ?>
    <div class="admin-message admin-message--<?= $messageType ?>" style="margin-bottom: 16px; padding: 12px 16px; border-radius: 6px; background: <?= $messageType === 'success' ? '#065f46' : '#7f1d1d' ?>; color: <?= $messageType === 'success' ? '#d1fae5' : '#fca5a5' ?>;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <span class="admin-table__muted">配置全局 SEO 设置，这些设置将作为所有页面的默认值。</span>
    </div>
</div>

<form method="POST" action="">
    <?= CSRF::getTokenField() ?>
    
    <div class="admin-card" style="margin-bottom: 16px;">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 16px;">基础设置</h2>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                网站名称 <span style="color: #9ca3af;">(必填)</span>
            </label>
            <input type="text" 
                   name="seo_site_name" 
                   value="<?= htmlspecialchars($currentSettings['seo_site_name']) ?>"
                   required
                   style="width: 100%; padding: 8px 12px; background: #1f2937; border: 1px solid #374151; border-radius: 6px; color: #e5e7eb; font-size: 14px;">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                用于 SEO title 和 Open Graph site_name
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                默认页面标题
            </label>
            <input type="text" 
                   name="seo_default_title" 
                   value="<?= htmlspecialchars($currentSettings['seo_default_title']) ?>"
                   style="width: 100%; padding: 8px 12px; background: #1f2937; border: 1px solid #374151; border-radius: 6px; color: #e5e7eb; font-size: 14px;">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                建议长度：30-60 字符
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                默认页面描述
            </label>
            <textarea name="seo_default_description" 
                      rows="3"
                      style="width: 100%; padding: 8px 12px; background: #1f2937; border: 1px solid #374151; border-radius: 6px; color: #e5e7eb; font-size: 14px; font-family: inherit; resize: vertical;"><?= htmlspecialchars($currentSettings['seo_default_description']) ?></textarea>
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                建议长度：120-160 字符
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                默认关键词
            </label>
            <input type="text" 
                   name="seo_default_keywords" 
                   value="<?= htmlspecialchars($currentSettings['seo_default_keywords']) ?>"
                   style="width: 100%; padding: 8px 12px; background: #1f2937; border: 1px solid #374151; border-radius: 6px; color: #e5e7eb; font-size: 14px;">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                多个关键词用逗号分隔
            </div>
        </div>
    </div>

    <div class="admin-card" style="margin-bottom: 16px;">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 16px;">图片设置</h2>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                默认 OG 图片 URL
            </label>
            <input type="url" 
                   name="seo_default_image" 
                   value="<?= htmlspecialchars($currentSettings['seo_default_image']) ?>"
                   placeholder="https://example.com/image.jpg"
                   style="width: 100%; padding: 8px 12px; background: #1f2937; border: 1px solid #374151; border-radius: 6px; color: #e5e7eb; font-size: 14px;">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                留空则使用默认图片：/assets/img/dofun-poster-bg.jpg<br>
                建议尺寸：1200x630 像素
            </div>
        </div>
    </div>

    <div class="admin-card" style="margin-bottom: 16px;">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 16px;">高级设置</h2>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                默认 Robots 设置
            </label>
            <select name="seo_robots_default" 
                    style="width: 100%; padding: 8px 12px; background: #1f2937; border: 1px solid #374151; border-radius: 6px; color: #e5e7eb; font-size: 14px;">
                <option value="index,follow" <?= $currentSettings['seo_robots_default'] === 'index,follow' ? 'selected' : '' ?>>index,follow</option>
                <option value="index,nofollow" <?= $currentSettings['seo_robots_default'] === 'index,nofollow' ? 'selected' : '' ?>>index,nofollow</option>
                <option value="noindex,follow" <?= $currentSettings['seo_robots_default'] === 'noindex,follow' ? 'selected' : '' ?>>noindex,follow</option>
                <option value="noindex,nofollow" <?= $currentSettings['seo_robots_default'] === 'noindex,nofollow' ? 'selected' : '' ?>>noindex,nofollow</option>
            </select>
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                默认 OG Type
            </label>
            <select name="seo_og_type_default" 
                    style="width: 100%; padding: 8px 12px; background: #1f2937; border: 1px solid #374151; border-radius: 6px; color: #e5e7eb; font-size: 14px;">
                <option value="website" <?= $currentSettings['seo_og_type_default'] === 'website' ? 'selected' : '' ?>>website</option>
                <option value="article" <?= $currentSettings['seo_og_type_default'] === 'article' ? 'selected' : '' ?>>article</option>
                <option value="product" <?= $currentSettings['seo_og_type_default'] === 'product' ? 'selected' : '' ?>>product</option>
            </select>
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                Twitter Card 类型
            </label>
            <select name="seo_twitter_card" 
                    style="width: 100%; padding: 8px 12px; background: #1f2937; border: 1px solid #374151; border-radius: 6px; color: #e5e7eb; font-size: 14px;">
                <option value="summary" <?= $currentSettings['seo_twitter_card'] === 'summary' ? 'selected' : '' ?>>summary</option>
                <option value="summary_large_image" <?= $currentSettings['seo_twitter_card'] === 'summary_large_image' ? 'selected' : '' ?>>summary_large_image</option>
                <option value="app" <?= $currentSettings['seo_twitter_card'] === 'app' ? 'selected' : '' ?>>app</option>
                <option value="player" <?= $currentSettings['seo_twitter_card'] === 'player' ? 'selected' : '' ?>>player</option>
            </select>
        </div>
    </div>

    <div style="text-align: right; padding-top: 16px; border-top: 1px solid #374151;">
        <button type="submit" class="btn btn-primary" style="background: #3b82f6; color: #fff; border: none; padding: 10px 24px; border-radius: 6px; font-size: 14px; font-weight: 500; cursor: pointer;">
            保存设置
        </button>
    </div>
</form>

<div class="admin-card" style="margin-top: 24px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">使用说明</h2>
    <div style="font-size: 13px; color: #d1d5db; line-height: 1.6;">
        <ul style="margin: 0; padding-left: 20px;">
            <li>这些设置将作为所有页面的默认 SEO 值</li>
            <li>如果某个测验或页面有自定义 SEO 设置，将优先使用自定义设置</li>
            <li>建议定期检查 SEO 设置，确保信息准确且符合搜索引擎优化最佳实践</li>
            <li>可以在 <a href="seo_optimizer.php" style="color: #60a5fa;">SEO 优化器</a> 中查看各个测验的 SEO 评分</li>
        </ul>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

