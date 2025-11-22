<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';

$pageTitle = '系统配置';
$pageSubtitle = '管理应用配置（调试模式、日志级别等）';
$activeMenu = 'system';

$configFile = __DIR__ . '/../config/app.php';
$config = require $configFile;

// 处理保存
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $newConfig = [
        'debug' => isset($_POST['debug']) && $_POST['debug'] === '1',
        'environment' => $_POST['environment'] ?? 'production',
        'log' => [
            'dir' => $config['log']['dir'] ?? __DIR__ . '/../logs',
            'enabled' => isset($_POST['log_enabled']) && $_POST['log_enabled'] === '1',
            'level' => $_POST['log_level'] ?? 'INFO',
            'file' => isset($_POST['log_file']) && $_POST['log_file'] === '1',
            'system' => isset($_POST['log_system']) && $_POST['log_system'] === '1',
        ],
        'error' => [
            'display_details' => isset($_POST['error_display_details']) && $_POST['error_display_details'] === '1',
            'log_stack_trace' => isset($_POST['error_log_stack_trace']) && $_POST['error_log_stack_trace'] === '1',
            'error_page' => $config['error']['error_page'] ?? null,
        ],
        'timezone' => $_POST['timezone'] ?? 'Asia/Shanghai',
    ];
    
    // 生成配置文件内容
    $configContent = "<?php\n";
    $configContent .= "/**\n";
    $configContent .= " * 应用配置文件\n";
    $configContent .= " * \n";
    $configContent .= " * 包含应用级别的配置，如调试模式、错误处理等\n";
    $configContent .= " * 此文件可通过后台管理系统修改\n";
    $configContent .= " */\n";
    $configContent .= "return [\n";
    $configContent .= "    // 调试模式：开发环境设为 true，生产环境设为 false\n";
    $configContent .= "    'debug' => " . ($newConfig['debug'] ? 'true' : 'false') . ",\n";
    $configContent .= "    \n";
    $configContent .= "    // 应用环境：'development' | 'production' | 'testing'\n";
    $configContent .= "    'environment' => '" . addslashes($newConfig['environment']) . "',\n";
    $configContent .= "    \n";
    $configContent .= "    // 日志配置\n";
    $configContent .= "    'log' => [\n";
    $configContent .= "        // 日志目录（相对于项目根目录）\n";
    $configContent .= "        'dir' => __DIR__ . '/../logs',\n";
    $configContent .= "        \n";
    $configContent .= "        // 是否启用日志\n";
    $configContent .= "        'enabled' => " . ($newConfig['log']['enabled'] ? 'true' : 'false') . ",\n";
    $configContent .= "        \n";
    $configContent .= "        // 日志级别：'DEBUG' | 'INFO' | 'WARNING' | 'ERROR'\n";
    $configContent .= "        'level' => '" . addslashes($newConfig['log']['level']) . "',\n";
    $configContent .= "        \n";
    $configContent .= "        // 是否记录到文件\n";
    $configContent .= "        'file' => " . ($newConfig['log']['file'] ? 'true' : 'false') . ",\n";
    $configContent .= "        \n";
    $configContent .= "        // 是否同时记录到系统日志\n";
    $configContent .= "        'system' => " . ($newConfig['log']['system'] ? 'true' : 'false') . ",\n";
    $configContent .= "    ],\n";
    $configContent .= "    \n";
    $configContent .= "    // 错误处理配置\n";
    $configContent .= "    'error' => [\n";
    $configContent .= "        // 是否显示详细错误信息（仅在 debug 模式下生效）\n";
    $configContent .= "        'display_details' => " . ($newConfig['error']['display_details'] ? 'true' : 'false') . ",\n";
    $configContent .= "        \n";
    $configContent .= "        // 是否记录错误堆栈\n";
    $configContent .= "        'log_stack_trace' => " . ($newConfig['error']['log_stack_trace'] ? 'true' : 'false') . ",\n";
    $configContent .= "        \n";
    $configContent .= "        // 错误页面模板路径（可选）\n";
    $configContent .= "        'error_page' => null,\n";
    $configContent .= "    ],\n";
    $configContent .= "    \n";
    $configContent .= "    // 时区\n";
    $configContent .= "    'timezone' => '" . addslashes($newConfig['timezone']) . "',\n";
    $configContent .= "];\n";
    
    // 备份原配置
    $backupFile = $configFile . '.backup.' . date('YmdHis');
    @copy($configFile, $backupFile);
    
    // 写入新配置
    if (@file_put_contents($configFile, $configContent, LOCK_EX) !== false) {
        $message = '配置已保存';
        $messageType = 'success';
        // 重新加载配置
        $config = $newConfig;
    } else {
        $message = '保存失败，请检查文件权限';
        $messageType = 'error';
    }
}

ob_start();
?>

<?php if ($message): ?>
    <div class="alert alert-<?= $messageType === 'success' ? 'success' : 'danger' ?>" style="margin-bottom: 16px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<form method="POST" action="">
    <div class="admin-card" style="margin-bottom: 16px;">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 16px;">基本配置</h2>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #e5e7eb;">
                调试模式
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="debug" value="1" <?= $config['debug'] ? 'checked' : '' ?>>
                <span style="font-size: 13px; color: #9ca3af;">
                    启用调试模式（生产环境请关闭）
                </span>
            </label>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #e5e7eb;">
                应用环境
            </label>
            <select name="environment" style="width: 100%; max-width: 300px; padding: 8px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;">
                <option value="development" <?= $config['environment'] === 'development' ? 'selected' : '' ?>>Development</option>
                <option value="production" <?= $config['environment'] === 'production' ? 'selected' : '' ?>>Production</option>
                <option value="testing" <?= $config['environment'] === 'testing' ? 'selected' : '' ?>>Testing</option>
            </select>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #e5e7eb;">
                时区
            </label>
            <input type="text" name="timezone" value="<?= htmlspecialchars($config['timezone']) ?>" 
                   style="width: 100%; max-width: 300px; padding: 8px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;">
            <div style="font-size: 12px; color: #9ca3af; margin-top: 4px;">
                例如：Asia/Shanghai, UTC, America/New_York
            </div>
        </div>
    </div>
    
    <div class="admin-card" style="margin-bottom: 16px;">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 16px;">日志配置</h2>
        
        <div style="margin-bottom: 20px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 12px;">
                <input type="checkbox" name="log_enabled" value="1" <?= $config['log']['enabled'] ? 'checked' : '' ?>>
                <span style="font-size: 13px; font-weight: 600; color: #e5e7eb;">启用日志记录</span>
            </label>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: #e5e7eb;">
                日志级别
            </label>
            <select name="log_level" style="width: 100%; max-width: 300px; padding: 8px; background: #020617; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; color: #e5e7eb; font-size: 13px;">
                <option value="DEBUG" <?= $config['log']['level'] === 'DEBUG' ? 'selected' : '' ?>>DEBUG - 所有日志</option>
                <option value="INFO" <?= $config['log']['level'] === 'INFO' ? 'selected' : '' ?>>INFO - 信息及以上</option>
                <option value="WARNING" <?= $config['log']['level'] === 'WARNING' ? 'selected' : '' ?>>WARNING - 警告及以上</option>
                <option value="ERROR" <?= $config['log']['level'] === 'ERROR' ? 'selected' : '' ?>>ERROR - 仅错误</option>
            </select>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                <input type="checkbox" name="log_file" value="1" <?= $config['log']['file'] ? 'checked' : '' ?>>
                <span style="font-size: 13px; color: #9ca3af;">记录到文件</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="log_system" value="1" <?= $config['log']['system'] ? 'checked' : '' ?>>
                <span style="font-size: 13px; color: #9ca3af;">同时记录到系统日志</span>
            </label>
        </div>
    </div>
    
    <div class="admin-card" style="margin-bottom: 16px;">
        <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 16px;">错误处理配置</h2>
        
        <div style="margin-bottom: 20px;">
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer; margin-bottom: 8px;">
                <input type="checkbox" name="error_display_details" value="1" <?= $config['error']['display_details'] ? 'checked' : '' ?>>
                <span style="font-size: 13px; color: #9ca3af;">显示详细错误信息（仅在调试模式下生效）</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; cursor: pointer;">
                <input type="checkbox" name="error_log_stack_trace" value="1" <?= $config['error']['log_stack_trace'] ? 'checked' : '' ?>>
                <span style="font-size: 13px; color: #9ca3af;">记录错误堆栈跟踪</span>
            </label>
        </div>
    </div>
    
    <div class="admin-toolbar">
        <div class="admin-toolbar__left">
            <span class="admin-table__muted">修改配置后请谨慎操作，建议先备份配置文件。</span>
        </div>
        <div class="admin-toolbar__right">
            <button type="submit" class="btn btn-primary">保存配置</button>
        </div>
    </div>
</form>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

