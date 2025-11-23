<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/SettingsHelper.php';
require_once __DIR__ . '/../lib/csrf.php';

$pageTitle = '显示设置';
$pageSubtitle = '配置前台显示相关设置，如测验人数美化等';
$activeMenu = 'system';

// 处理保存
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        http_response_code(403);
        die('CSRF token 验证失败，请刷新页面后重试');
    }

    $beautifiedValue = trim($_POST['play_count_beautified'] ?? '');
    
    // 验证输入：必须是正整数或空字符串
    if ($beautifiedValue !== '' && (!is_numeric($beautifiedValue) || (int)$beautifiedValue <= 0)) {
        $message = '美化数据必须是正整数，或留空以使用真实数据。';
        $messageType = 'error';
    } else {
        // 保存设置
        $success = SettingsHelper::set(
            'play_count_beautified', 
            $beautifiedValue, 
            '测验人数美化数据（仅用于前台显示，不影响真实数据）'
        );
        
        if ($success) {
            SettingsHelper::clearCache();
            $message = '设置已保存成功！';
            $messageType = 'success';
        } else {
            $message = '保存失败，请重试。';
            $messageType = 'error';
        }
    }
}

// 获取当前设置
$beautifiedValue = SettingsHelper::get('play_count_beautified', '');

// 获取真实测验人数（用于对比显示）
$realPlayCount = 0;
try {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_runs");
    $stmt->execute();
    $realPlayCount = (int)$stmt->fetchColumn();
} catch (PDOException $e) {
    // 忽略错误
}

ob_start();
?>

<?php if ($message): ?>
    <div class="admin-message admin-message--<?= $messageType ?>" style="margin-bottom: 16px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <?= CSRF::getTokenField() ?>
    
    <div class="admin-card" style="margin-bottom: 16px;">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 16px;">测验人数美化设置</h2>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #e5e7eb;">
                美化数据
            </label>
            <div style="margin-bottom: 8px;">
                <input 
                    type="number" 
                    name="play_count_beautified" 
                    value="<?= htmlspecialchars($beautifiedValue) ?>"
                    min="1"
                    step="1"
                    placeholder="留空则使用真实数据"
                    style="width: 100%; max-width: 300px; padding: 8px 12px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 14px;"
                >
            </div>
            <div style="font-size: 12px; color: #9ca3af; line-height: 1.6;">
                <p style="margin: 0 0 8px 0;">设置一个固定的美化数值，用于前台显示测验人数。</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li style="margin-bottom: 4px;">
                        <strong>留空：</strong>使用真实的测验人数
                    </li>
                    <li style="margin-bottom: 4px;">
                        <strong>设置数值：</strong>前台将显示此数值（但不会小于真实数据）
                    </li>
                </ul>
            </div>
        </div>
        
        <?php if ($realPlayCount > 0 || $beautifiedValue !== ''): ?>
        <div style="margin-bottom: 20px; padding: 12px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 6px;">
            <div style="font-size: 12px; color: #93c5fd; line-height: 1.6;">
                <p style="margin: 0 0 8px 0; font-weight: 600;">📊 当前数据对比</p>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 12px; margin-top: 8px;">
                    <div>
                        <div style="color: #9ca3af; font-size: 11px; margin-bottom: 4px;">真实测验人数</div>
                        <div style="font-size: 18px; font-weight: 600; color: #e5e7eb;">
                            <?= number_format($realPlayCount) ?>
                        </div>
                    </div>
                    <div>
                        <div style="color: #9ca3af; font-size: 11px; margin-bottom: 4px;">美化数据</div>
                        <div style="font-size: 18px; font-weight: 600; color: #e5e7eb;">
                            <?= $beautifiedValue !== '' ? number_format((int)$beautifiedValue) : '<span style="color: #9ca3af;">未设置</span>' ?>
                        </div>
                    </div>
                    <div>
                        <div style="color: #9ca3af; font-size: 11px; margin-bottom: 4px;">前台显示</div>
                        <div style="font-size: 18px; font-weight: 600; color: #60a5fa;">
                            <?php
                            if ($beautifiedValue !== '') {
                                $displayCount = max((int)$beautifiedValue, $realPlayCount);
                                echo number_format($displayCount);
                            } else {
                                echo number_format($realPlayCount);
                            }
                            ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
        
        <div style="margin-bottom: 20px; padding: 12px; background: rgba(251, 191, 36, 0.1); border: 1px solid rgba(251, 191, 36, 0.3); border-radius: 6px;">
            <div style="font-size: 12px; color: #fcd34d; line-height: 1.6;">
                <p style="margin: 0 0 8px 0; font-weight: 600;">💡 使用说明</p>
                <ul style="margin: 0; padding-left: 20px;">
                    <li style="margin-bottom: 4px;">美化数据仅用于前台显示，不会影响数据库中的真实数据</li>
                    <li style="margin-bottom: 4px;">如果设置的美化数据小于真实数据，前台将显示真实数据（确保不会显示更小的数值）</li>
                    <li style="margin-bottom: 4px;">此设置会影响首页卡片和测验页面的"已有 X 人测验"显示</li>
                    <li style="margin-bottom: 4px;">后台统计和数据分析仍使用真实数据</li>
                </ul>
            </div>
        </div>
    </div>
    
    <div class="admin-toolbar">
        <div class="admin-toolbar__left">
            <span class="admin-table__muted">设置保存后，前台显示的测验人数将使用美化数据（如果已设置）。</span>
        </div>
        <div class="admin-toolbar__right">
            <button type="submit" class="btn btn-primary">保存设置</button>
        </div>
    </div>
</form>

<?php
$content = ob_get_clean();
require_once __DIR__ . '/layout.php';

