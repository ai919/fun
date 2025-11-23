<?php
/**
 * 安装向导界面
 */

// 检查是否已安装
$lockFile = __DIR__ . '/../.installed';
if (file_exists($lockFile)) {
    header('Location: /');
    exit;
}

$step = $_GET['step'] ?? 1;
$error = $_GET['error'] ?? '';
$success = $_GET['success'] ?? '';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>安装向导 - DoFun 测验平台</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        .container {
            background: white;
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            padding: 40px;
        }
        h1 {
            color: #333;
            margin-bottom: 10px;
            font-size: 28px;
        }
        .subtitle {
            color: #666;
            margin-bottom: 30px;
            font-size: 14px;
        }
        .step-indicator {
            display: flex;
            margin-bottom: 30px;
            gap: 10px;
        }
        .step {
            flex: 1;
            height: 4px;
            background: #e0e0e0;
            border-radius: 2px;
            position: relative;
        }
        .step.active {
            background: #667eea;
        }
        .step.completed {
            background: #10b981;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 8px;
            color: #333;
            font-weight: 500;
            font-size: 14px;
        }
        input[type="text"],
        input[type="password"],
        input[type="email"] {
            width: 100%;
            padding: 12px;
            border: 2px solid #e0e0e0;
            border-radius: 6px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        .help-text {
            font-size: 12px;
            color: #999;
            margin-top: 4px;
        }
        .btn {
            background: #667eea;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 6px;
            font-size: 16px;
            font-weight: 500;
            cursor: pointer;
            width: 100%;
            transition: background 0.3s;
        }
        .btn:hover {
            background: #5568d3;
        }
        .btn:disabled {
            background: #ccc;
            cursor: not-allowed;
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        .alert-error {
            background: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        .alert-success {
            background: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        .requirements {
            background: #f5f5f5;
            padding: 20px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .requirements h3 {
            margin-bottom: 15px;
            font-size: 16px;
            color: #333;
        }
        .requirement {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
            font-size: 14px;
        }
        .requirement .icon {
            width: 20px;
            height: 20px;
            margin-right: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .requirement.pass .icon {
            color: #10b981;
        }
        .requirement.fail .icon {
            color: #ef4444;
        }
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        .loading.active {
            display: block;
        }
        .spinner {
            border: 3px solid #f3f3f3;
            border-top: 3px solid #667eea;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>欢迎使用 DoFun 测验平台</h1>
        <p class="subtitle">请按照以下步骤完成安装</p>

        <div class="step-indicator">
            <div class="step <?= $step >= 1 ? 'active' : '' ?> <?= $step > 1 ? 'completed' : '' ?>"></div>
            <div class="step <?= $step >= 2 ? 'active' : '' ?> <?= $step > 2 ? 'completed' : '' ?>"></div>
            <div class="step <?= $step >= 3 ? 'active' : '' ?> <?= $step > 3 ? 'completed' : '' ?>"></div>
        </div>

        <?php if ($error): ?>
            <div class="alert alert-error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <?php if ($success): ?>
            <div class="alert alert-success"><?= htmlspecialchars($success) ?></div>
        <?php endif; ?>

        <?php if ($step == 1): ?>
            <!-- 步骤 1: 环境检查 -->
            <div class="requirements">
                <h3>环境检查</h3>
                <div id="requirements-list">
                    <div class="requirement">
                        <div class="icon">⏳</div>
                        <span>正在检查...</span>
                    </div>
                </div>
            </div>
            <button class="btn" onclick="checkRequirements()">重新检查</button>
            <button class="btn" onclick="nextStep()" id="next-btn" style="display:none; margin-top:10px;">下一步</button>

        <?php elseif ($step == 2): ?>
            <!-- 步骤 2: 数据库配置 -->
            <form id="db-form" onsubmit="return testConnection(event)">
                <div class="form-group">
                    <label>数据库主机</label>
                    <input type="text" name="db_host" value="127.0.0.1" required>
                    <div class="help-text">通常是 localhost 或 127.0.0.1</div>
                </div>
                <div class="form-group">
                    <label>数据库名称</label>
                    <input type="text" name="db_name" value="fun_quiz" required>
                    <div class="help-text">如果数据库不存在，安装程序会自动创建</div>
                </div>
                <div class="form-group">
                    <label>数据库用户名</label>
                    <input type="text" name="db_user" value="root" required>
                </div>
                <div class="form-group">
                    <label>数据库密码</label>
                    <input type="password" name="db_pass" value="">
                </div>
                <button type="submit" class="btn">测试连接</button>
            </form>

        <?php elseif ($step == 3): ?>
            <!-- 步骤 3: 管理员账户 -->
            <form id="admin-form" onsubmit="return createAdmin(event)">
                <div class="form-group">
                    <label>管理员用户名</label>
                    <input type="text" name="admin_username" value="admin" required>
                </div>
                <div class="form-group">
                    <label>管理员密码</label>
                    <input type="password" name="admin_password" required minlength="6">
                    <div class="help-text">至少 6 个字符</div>
                </div>
                <div class="form-group">
                    <label>确认密码</label>
                    <input type="password" name="admin_password_confirm" required>
                </div>
                <div class="form-group">
                    <label>显示名称（可选）</label>
                    <input type="text" name="admin_display_name" value="管理员">
                </div>
                <button type="submit" class="btn">完成安装</button>
            </form>
        <?php endif; ?>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p>正在处理，请稍候...</p>
        </div>
    </div>

    <script>
        // 检查环境要求
        function checkRequirements() {
            fetch('check.php')
                .then(res => res.json())
                .then(data => {
                    const list = document.getElementById('requirements-list');
                    list.innerHTML = '';
                    
                    const requirements = [
                        { name: 'PHP 版本 >= 7.4', pass: data.php_version },
                        { name: 'PDO MySQL 扩展', pass: data.pdo_mysql },
                        { name: 'JSON 扩展', pass: data.json },
                        { name: 'config 目录可写', pass: data.config_writable },
                        { name: 'cache 目录可写', pass: data.cache_writable },
                    ];

                    let allPass = true;
                    requirements.forEach(req => {
                        const div = document.createElement('div');
                        div.className = `requirement ${req.pass ? 'pass' : 'fail'}`;
                        div.innerHTML = `
                            <div class="icon">${req.pass ? '✓' : '✗'}</div>
                            <span>${req.name}</span>
                        `;
                        list.appendChild(div);
                        if (!req.pass) allPass = false;
                    });

                    if (allPass) {
                        document.getElementById('next-btn').style.display = 'block';
                    }
                })
                .catch(err => {
                    alert('检查失败: ' + err.message);
                });
        }

        function nextStep() {
            window.location.href = '?step=2';
        }

        // 测试数据库连接
        function testConnection(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            const loading = document.getElementById('loading');
            loading.classList.add('active');

            fetch('process.php?action=test_connection', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    loading.classList.remove('active');
                    if (data.success) {
                        // 保存数据库配置到 sessionStorage
                        sessionStorage.setItem('db_config', JSON.stringify({
                            host: formData.get('db_host'),
                            name: formData.get('db_name'),
                            user: formData.get('db_user'),
                            pass: formData.get('db_pass')
                        }));
                        window.location.href = '?step=3';
                    } else {
                        alert('连接失败: ' + data.message);
                    }
                })
                .catch(err => {
                    loading.classList.remove('active');
                    alert('请求失败: ' + err.message);
                });
        }

        // 创建管理员并完成安装
        function createAdmin(e) {
            e.preventDefault();
            const form = e.target;
            const formData = new FormData(form);
            
            // 验证密码
            if (formData.get('admin_password') !== formData.get('admin_password_confirm')) {
                alert('两次输入的密码不一致');
                return false;
            }

            // 获取数据库配置
            const dbConfig = JSON.parse(sessionStorage.getItem('db_config') || '{}');
            Object.keys(dbConfig).forEach(key => {
                formData.append('db_' + key, dbConfig[key]);
            });

            const loading = document.getElementById('loading');
            loading.classList.add('active');

            fetch('process.php?action=install', {
                method: 'POST',
                body: formData
            })
                .then(res => res.json())
                .then(data => {
                    loading.classList.remove('active');
                    if (data.success) {
                        alert('安装成功！正在跳转到登录页面...');
                        window.location.href = '/admin/login.php';
                    } else {
                        alert('安装失败: ' + data.message);
                    }
                })
                .catch(err => {
                    loading.classList.remove('active');
                    alert('请求失败: ' + err.message);
                });
        }

        // 页面加载时自动检查环境
        window.addEventListener('DOMContentLoaded', function() {
            if (<?= $step ?> === 1) {
                checkRequirements();
            }
        });
    </script>
</body>
</html>

