<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/SettingsHelper.php';
require_once __DIR__ . '/../lib/csrf.php';

$pageTitle = '网站基本信息设置';
$pageSubtitle = '管理网站名称、副标题、首页显示配置等基本信息';
$activeMenu = 'site_settings';

$message = '';
$messageType = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        http_response_code(403);
        die('CSRF token 验证失败，请刷新页面后重试');
    }

    $settings = [
        'site_name' => trim($_POST['site_name'] ?? ''),
        'site_subtitle' => trim($_POST['site_subtitle'] ?? ''),
        'site_description' => trim($_POST['site_description'] ?? ''),
        'site_keywords' => trim($_POST['site_keywords'] ?? ''),
        'home_tests_limit' => (int)($_POST['home_tests_limit'] ?? 20),
        'home_tag_limit' => (int)($_POST['home_tag_limit'] ?? 10),
        'home_tests_per_page' => (int)($_POST['home_tests_per_page'] ?? 20),
        // SEO设置
        'seo_site_name' => trim($_POST['seo_site_name'] ?? ''),
        'seo_default_title' => trim($_POST['seo_default_title'] ?? ''),
        'seo_default_description' => trim($_POST['seo_default_description'] ?? ''),
        'seo_default_image' => trim($_POST['seo_default_image'] ?? ''),
        'seo_default_keywords' => trim($_POST['seo_default_keywords'] ?? ''),
        'seo_robots_default' => trim($_POST['seo_robots_default'] ?? 'index,follow'),
        'seo_og_type_default' => trim($_POST['seo_og_type_default'] ?? 'website'),
        'seo_twitter_card' => trim($_POST['seo_twitter_card'] ?? 'summary_large_image'),
    ];

    // 验证数值范围
    $settings['home_tests_limit'] = max(1, min(200, $settings['home_tests_limit']));
    $settings['home_tag_limit'] = max(1, min(50, $settings['home_tag_limit']));
    $settings['home_tests_per_page'] = max(1, min(100, $settings['home_tests_per_page']));

    $success = true;
    foreach ($settings as $key => $value) {
        $description = match($key) {
            'site_name' => '网站名称（显示在首页标题）',
            'site_subtitle' => '网站副标题（显示在首页标题下方）',
            'site_description' => '网站描述（用于SEO和分享）',
            'site_keywords' => '网站关键词（用于SEO，多个关键词用逗号分隔）',
            'home_tests_limit' => '首页显示测验卡片数量限制（1-200）',
            'home_tag_limit' => '首页显示标签数量（1-50）',
            'home_tests_per_page' => '首页每页显示测验数量（1-100）',
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
        // 清除相关缓存
        require_once __DIR__ . '/../lib/CacheHelper.php';
        CacheHelper::delete('published_tests_list');
        CacheHelper::deletePattern('top_tags_*');
        
        $message = '网站基本信息设置已保存';
        $messageType = 'success';
    } else {
        $message = '保存失败，请重试';
        $messageType = 'error';
    }
}

// 获取当前设置
$currentSettings = [
    'site_name' => SettingsHelper::get('site_name', 'DoFun心理实验空间'),
    'site_subtitle' => SettingsHelper::get('site_subtitle', '心理 性格 性情：更专业的在线测验实验室'),
    'site_description' => SettingsHelper::get('site_description', 'DoFun心理实验空间，是一个轻量、有趣的在线测验实验室，提供人格、情感、社交、生活方式等多个方向的心理小测试，帮你以更轻松的方式认识自己。'),
    'site_keywords' => SettingsHelper::get('site_keywords', '心理测试,性格测试,在线测验,心理实验,人格测试'),
    'home_tests_limit' => (int)SettingsHelper::get('home_tests_limit', 20),
    'home_tag_limit' => (int)SettingsHelper::get('home_tag_limit', 10),
    'home_tests_per_page' => (int)SettingsHelper::get('home_tests_per_page', 20),
    // SEO设置
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
        <span class="admin-table__muted">配置网站基本信息，这些设置将影响首页显示和用户体验。</span>
    </div>
</div>

<form method="POST" action="">
    <?= CSRF::getTokenField() ?>
    
    <div class="admin-card" style="margin-bottom: 16px;">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 16px;">网站基本信息</h2>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                网站名称 <span style="color: #9ca3af;">(必填)</span>
            </label>
            <input type="text" 
                   name="site_name" 
                   value="<?= htmlspecialchars($currentSettings['site_name']) ?>"
                   required
                   maxlength="100"
                   style="width: 100%; max-width: 500px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;"
                   placeholder="例如：DoFun心理实验空间">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                显示在首页标题位置
            </div>
        </div>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                网站副标题
            </label>
            <input type="text" 
                   name="site_subtitle" 
                   value="<?= htmlspecialchars($currentSettings['site_subtitle']) ?>"
                   maxlength="200"
                   style="width: 100%; max-width: 500px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;"
                   placeholder="例如：心理 性格 性情：更专业的在线测验实验室">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                显示在首页标题下方
            </div>
        </div>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                网站描述
            </label>
            <textarea name="site_description" 
                      rows="3"
                      maxlength="500"
                      style="width: 100%; max-width: 600px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px; resize: vertical;"
                      placeholder="网站简介，用于SEO和分享"><?= htmlspecialchars($currentSettings['site_description']) ?></textarea>
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                用于SEO meta description 和社交媒体分享
            </div>
        </div>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                网站关键词
            </label>
            <input type="text" 
                   name="site_keywords" 
                   value="<?= htmlspecialchars($currentSettings['site_keywords']) ?>"
                   maxlength="200"
                   style="width: 100%; max-width: 500px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;"
                   placeholder="例如：心理测试,性格测试,在线测验,心理实验,人格测试">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                多个关键词用逗号分隔，用于SEO
            </div>
        </div>
    </div>
    
    <div class="admin-card" style="margin-bottom: 16px;">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 16px;">首页显示配置</h2>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                首页显示测验卡片数量
            </label>
            <input type="number" 
                   name="home_tests_limit" 
                   value="<?= $currentSettings['home_tests_limit'] ?>"
                   min="1"
                   max="200"
                   required
                   style="width: 100%; max-width: 200px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                限制首页显示的测验卡片数量（1-200），建议设置为 20-50
            </div>
        </div>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                首页显示标签数量
            </label>
            <input type="number" 
                   name="home_tag_limit" 
                   value="<?= $currentSettings['home_tag_limit'] ?>"
                   min="1"
                   max="50"
                   required
                   style="width: 100%; max-width: 200px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                首页标签筛选器显示的标签数量（1-50），建议设置为 10-20
            </div>
        </div>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                首页每页显示测验数量
            </label>
            <input type="number" 
                   name="home_tests_per_page" 
                   value="<?= $currentSettings['home_tests_per_page'] ?>"
                   min="1"
                   max="100"
                   required
                   style="width: 100%; max-width: 200px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                如果启用分页，每页显示的测验数量（1-100），建议设置为 20-30
            </div>
        </div>
    </div>
    
    <div class="admin-card" style="margin-bottom: 16px;">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 16px;">SEO 设置</h2>
        
        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                SEO 网站名称 <span style="color: #9ca3af;">(必填)</span>
            </label>
            <input type="text" 
                   name="seo_site_name" 
                   value="<?= htmlspecialchars($currentSettings['seo_site_name']) ?>"
                   required
                   maxlength="100"
                   style="width: 100%; max-width: 500px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;"
                   placeholder="用于 SEO title 和 Open Graph site_name">
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
                   maxlength="200"
                   style="width: 100%; max-width: 600px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;"
                   placeholder="默认页面标题">
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
                      maxlength="500"
                      style="width: 100%; max-width: 600px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px; resize: vertical;"
                      placeholder="默认页面描述（meta description）"><?= htmlspecialchars($currentSettings['seo_default_description']) ?></textarea>
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
                   maxlength="200"
                   style="width: 100%; max-width: 500px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;"
                   placeholder="多个关键词用逗号分隔">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                多个关键词用逗号分隔
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                默认 OG 图片 URL
            </label>
            <input type="url" 
                   name="seo_default_image" 
                   value="<?= htmlspecialchars($currentSettings['seo_default_image']) ?>"
                   placeholder="https://example.com/image.jpg"
                   style="width: 100%; max-width: 500px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                留空则使用默认图片：/assets/img/dofun-poster-bg.jpg<br>
                建议尺寸：1200x630 像素
            </div>
        </div>

        <div style="margin-bottom: 16px;">
            <label style="display: block; font-size: 13px; font-weight: 500; color: #e5e7eb; margin-bottom: 6px;">
                默认 Robots 设置
            </label>
            <select name="seo_robots_default" 
                    style="width: 100%; max-width: 300px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;">
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
                    style="width: 100%; max-width: 300px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;">
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
                    style="width: 100%; max-width: 300px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;">
                <option value="summary" <?= $currentSettings['seo_twitter_card'] === 'summary' ? 'selected' : '' ?>>summary</option>
                <option value="summary_large_image" <?= $currentSettings['seo_twitter_card'] === 'summary_large_image' ? 'selected' : '' ?>>summary_large_image</option>
                <option value="app" <?= $currentSettings['seo_twitter_card'] === 'app' ? 'selected' : '' ?>>app</option>
                <option value="player" <?= $currentSettings['seo_twitter_card'] === 'player' ? 'selected' : '' ?>>player</option>
            </select>
        </div>
    </div>
    
    <div class="admin-toolbar">
        <div class="admin-toolbar__left">
            <span class="admin-table__muted">修改设置后，相关缓存将自动清除。</span>
        </div>
        <div class="admin-toolbar__right">
            <button type="submit" class="btn btn-primary">保存设置</button>
        </div>
    </div>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';
?>

