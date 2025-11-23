<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/AdHelper.php';
require_once __DIR__ . '/../lib/csrf.php';

$pageTitle = '广告位管理';
$pageSubtitle = '管理网站广告位配置';
$activeMenu = 'ads';

$message = '';
$messageType = '';

// 处理删除
if (isset($_GET['delete']) && ctype_digit($_GET['delete'])) {
    $id = (int)$_GET['delete'];
    if (AdHelper::deletePosition($id)) {
        AdHelper::clearCache();
        $message = '广告位已删除';
        $messageType = 'success';
    } else {
        $message = '删除失败';
        $messageType = 'error';
    }
}

// 处理保存
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        http_response_code(403);
        die('CSRF token 验证失败，请刷新页面后重试');
    }

    $id = isset($_POST['id']) && ctype_digit($_POST['id']) ? (int)$_POST['id'] : null;
    
    $data = [
        'position_key' => trim($_POST['position_key'] ?? ''),
        'position_name' => trim($_POST['position_name'] ?? ''),
        'ad_type' => trim($_POST['ad_type'] ?? 'code'),
        'ad_code' => trim($_POST['ad_code'] ?? ''),
        'image_url' => trim($_POST['image_url'] ?? ''),
        'link_url' => trim($_POST['link_url'] ?? ''),
        'alt_text' => trim($_POST['alt_text'] ?? ''),
        'is_enabled' => isset($_POST['is_enabled']) && $_POST['is_enabled'] === '1' ? 1 : 0,
        'display_pages' => trim($_POST['display_pages'] ?? ''),
        'priority' => isset($_POST['priority']) ? (int)$_POST['priority'] : 0,
        'max_display_count' => isset($_POST['max_display_count']) ? (int)$_POST['max_display_count'] : 0,
        'start_date' => !empty($_POST['start_date']) ? trim($_POST['start_date']) : null,
        'end_date' => !empty($_POST['end_date']) ? trim($_POST['end_date']) : null,
    ];

    if ($id) {
        $data['id'] = $id;
    }

    if (AdHelper::savePosition($data)) {
        AdHelper::clearCache();
        $message = '广告位已保存';
        $messageType = 'success';
    } else {
        $message = '保存失败，请重试';
        $messageType = 'error';
    }
}

// 获取编辑的广告位
$editAd = null;
if (isset($_GET['edit']) && ctype_digit($_GET['edit'])) {
    $editAd = AdHelper::getPosition((int)$_GET['edit']);
}

// 获取所有广告位
$allAds = AdHelper::getAllPositions();

ob_start();
?>

<?php if ($message): ?>
    <div class="admin-message admin-message--<?= $messageType ?>" style="margin-bottom: 16px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div style="display: flex; gap: 20px; margin-bottom: 24px;">
    <div style="flex: 1;">
        <div class="admin-card">
            <h2 class="admin-page-title" style="margin: 0 0 20px 0; font-size: 18px; font-weight: 600;">广告位列表</h2>
            
            <table class="admin-table">
                <thead>
                    <tr>
                        <th>标识</th>
                        <th>名称</th>
                        <th>类型</th>
                        <th>显示页面</th>
                        <th style="text-align: center;">状态</th>
                        <th style="text-align: center;">优先级</th>
                        <th style="text-align: center;">操作</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($allAds)): ?>
                        <tr>
                            <td colspan="7" style="text-align: center; padding: 24px; color: var(--admin-text-secondary);">
                                暂无广告位，请先运行数据库迁移文件创建默认广告位
                            </td>
                        </tr>
                    <?php else: ?>
                        <?php foreach ($allAds as $ad): ?>
                            <tr>
                                <td>
                                    <code class="code-badge">
                                        <?= htmlspecialchars($ad['position_key']) ?>
                                    </code>
                                </td>
                                <td>
                                    <div class="admin-table__title">
                                        <?= htmlspecialchars($ad['position_name'] ?? '') ?>
                                    </div>
                                </td>
                                <td>
                                    <?php
                                    $typeLabels = ['code' => '代码', 'image' => '图片', 'text' => '文字'];
                                    echo $typeLabels[$ad['ad_type']] ?? $ad['ad_type'];
                                    ?>
                                </td>
                                <td>
                                    <?= htmlspecialchars($ad['display_pages'] ?: '全部') ?>
                                </td>
                                <td style="text-align: center;">
                                    <?php if ($ad['is_enabled']): ?>
                                        <span class="code-badge code-badge--success">✓ 启用</span>
                                    <?php else: ?>
                                        <span class="code-badge code-badge--error">✗ 禁用</span>
                                    <?php endif; ?>
                                </td>
                                <td style="text-align: center;">
                                    <span class="code-badge"><?= $ad['priority'] ?></span>
                                </td>
                                <td class="admin-table__actions">
                                    <a href="?edit=<?= $ad['id'] ?>" class="btn btn-xs btn-primary">编辑</a>
                                    <a href="?delete=<?= $ad['id'] ?>" 
                                       onclick="return confirm('确定要删除这个广告位吗？')"
                                       class="btn btn-xs btn-ghost" style="color: #dc2626;">删除</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <div style="width: 420px;">
        <div class="admin-card">
            <h2 class="admin-page-title" style="margin: 0 0 20px 0; font-size: 18px; font-weight: 600;">
                <?= $editAd ? '编辑广告位' : '新建广告位' ?>
            </h2>
            
            <form method="POST" action="">
                <?= CSRF::getTokenField() ?>
                <?php if ($editAd): ?>
                    <input type="hidden" name="id" value="<?= $editAd['id'] ?>">
                <?php endif; ?>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">广告位标识 *</label>
                    <input type="text" name="position_key" value="<?= htmlspecialchars($editAd['position_key'] ?? '') ?>" 
                           required class="form-input">
                    <small class="form-hint">唯一标识，如：home_top, test_middle</small>
                </div>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">广告位名称 *</label>
                    <input type="text" name="position_name" value="<?= htmlspecialchars($editAd['position_name'] ?? '') ?>" 
                           required class="form-input">
                </div>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">广告类型 *</label>
                    <select name="ad_type" id="ad_type" class="form-input">
                        <option value="code" <?= ($editAd['ad_type'] ?? 'code') === 'code' ? 'selected' : '' ?>>代码广告（HTML/JavaScript）</option>
                        <option value="image" <?= ($editAd['ad_type'] ?? '') === 'image' ? 'selected' : '' ?>>图片广告</option>
                        <option value="text" <?= ($editAd['ad_type'] ?? '') === 'text' ? 'selected' : '' ?>>文字广告</option>
                    </select>
                </div>
                
                <div class="form-group" id="ad_code_field" style="margin-bottom: 16px; <?= ($editAd['ad_type'] ?? 'code') !== 'code' ? 'display: none;' : '' ?>">
                    <label class="form-label">广告代码</label>
                    <textarea name="ad_code" rows="6" class="form-input" style="font-family: ui-monospace, monospace; font-size: 12px;"><?= htmlspecialchars($editAd['ad_code'] ?? '') ?></textarea>
                    <small class="form-hint">支持 HTML 和 JavaScript 代码</small>
                </div>
                
                <div id="image_fields" style="margin-bottom: 16px; <?= ($editAd['ad_type'] ?? 'code') !== 'image' ? 'display: none;' : '' ?>">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label class="form-label">图片URL</label>
                        <input type="url" name="image_url" value="<?= htmlspecialchars($editAd['image_url'] ?? '') ?>" class="form-input">
                    </div>
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label class="form-label">链接地址</label>
                        <input type="url" name="link_url" value="<?= htmlspecialchars($editAd['link_url'] ?? '') ?>" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Alt文本</label>
                        <input type="text" name="alt_text" value="<?= htmlspecialchars($editAd['alt_text'] ?? '') ?>" class="form-input">
                    </div>
                </div>
                
                <div id="text_fields" style="margin-bottom: 16px; <?= ($editAd['ad_type'] ?? 'code') !== 'text' ? 'display: none;' : '' ?>">
                    <div class="form-group" style="margin-bottom: 12px;">
                        <label class="form-label">文字内容</label>
                        <textarea name="ad_code" rows="3" class="form-input"><?= htmlspecialchars($editAd['ad_code'] ?? '') ?></textarea>
                    </div>
                    <div class="form-group">
                        <label class="form-label">链接地址</label>
                        <input type="url" name="link_url" value="<?= htmlspecialchars($editAd['link_url'] ?? '') ?>" class="form-input">
                    </div>
                </div>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">显示页面</label>
                    <input type="text" name="display_pages" value="<?= htmlspecialchars($editAd['display_pages'] ?? '') ?>" class="form-input">
                    <small class="form-hint">逗号分隔：home,test,result（留空表示全部页面）</small>
                </div>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">优先级</label>
                    <input type="number" name="priority" value="<?= $editAd['priority'] ?? 0 ?>" class="form-input">
                    <small class="form-hint">数字越大越优先显示</small>
                </div>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">开始日期</label>
                    <input type="datetime-local" name="start_date" value="<?= $editAd['start_date'] ? date('Y-m-d\TH:i', strtotime($editAd['start_date'])) : '' ?>" class="form-input">
                </div>
                
                <div class="form-group" style="margin-bottom: 16px;">
                    <label class="form-label">结束日期</label>
                    <input type="datetime-local" name="end_date" value="<?= $editAd['end_date'] ? date('Y-m-d\TH:i', strtotime($editAd['end_date'])) : '' ?>" class="form-input">
                </div>
                
                <div class="form-group" style="margin-bottom: 20px;">
                    <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                        <input type="checkbox" name="is_enabled" value="1" <?= ($editAd['is_enabled'] ?? 0) ? 'checked' : '' ?>>
                        <span style="color: var(--admin-text-primary);">启用此广告位</span>
                    </label>
                </div>
                
                <div style="display: flex; gap: 8px;">
                    <button type="submit" class="btn btn-primary" style="flex: 1;">保存</button>
                    <?php if ($editAd): ?>
                        <a href="ad_positions.php" class="btn btn-ghost">取消</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
        
        <div class="admin-card" style="margin-top: 16px;">
            <h3 style="margin: 0 0 12px 0; font-size: 16px; font-weight: 600;">建议的广告位位置</h3>
            <ul style="margin: 0; padding-left: 20px; color: var(--text-secondary); font-size: 14px; line-height: 1.8;">
                <li><strong>首页顶部</strong> (home_top) - Header下方</li>
                <li><strong>首页中间</strong> (home_middle) - 测验列表中间</li>
                <li><strong>首页底部</strong> (home_bottom) - 页面底部</li>
                <li><strong>测验页顶部</strong> (test_top) - 题目上方</li>
                <li><strong>测验页中间</strong> (test_middle) - 题目之间</li>
                <li><strong>测验页底部</strong> (test_bottom) - 提交按钮下方</li>
                <li><strong>结果页顶部</strong> (result_top) - 结果上方</li>
                <li><strong>结果页中间</strong> (result_middle) - 结果中间</li>
                <li><strong>结果页底部</strong> (result_bottom) - 页面底部</li>
            </ul>
        </div>
    </div>
</div>

<script>
document.getElementById('ad_type').addEventListener('change', function() {
    const type = this.value;
    document.getElementById('ad_code_field').style.display = type === 'code' ? 'block' : 'none';
    document.getElementById('image_fields').style.display = type === 'image' ? 'block' : 'none';
    document.getElementById('text_fields').style.display = type === 'text' ? 'block' : 'none';
});
</script>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';
?>

