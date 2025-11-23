<?php
require_once __DIR__ . '/lib/user_auth.php';
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/csrf.php';
require_once __DIR__ . '/lib/topbar.php';

$user = UserAuth::requireLogin();

$errors = [];
$success = '';

// 检查是否为 AJAX 请求
$isAjax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        $errors['general'] = 'CSRF token 验证失败，请刷新页面后重试';
    } else {
        $action = $_POST['action'] ?? '';

        if ($action === 'update_username') {
            $newUsername = trim($_POST['username'] ?? '');
            $result = UserAuth::updateUsername($user['id'], $newUsername);
            if ($result['success']) {
                $success = '用户名更新成功';
                // 重新获取用户信息
                $user = UserAuth::currentUser();
            } else {
                $errors['username'] = $result['message'] ?? '用户名更新失败';
            }
        } elseif ($action === 'update_password') {
            $oldPassword = $_POST['old_password'] ?? '';
            $newPassword = $_POST['new_password'] ?? '';
            $confirmPassword = $_POST['confirm_password'] ?? '';

            if ($newPassword !== $confirmPassword) {
                $errors['password'] = '两次输入的密码不一致';
            } else {
                $result = UserAuth::updatePassword($user['id'], $oldPassword, $newPassword);
                if ($result['success']) {
                    $success = '密码更新成功';
                } else {
                    $errors['password'] = $result['message'] ?? '密码更新失败';
                }
            }
        } elseif ($action === 'update_nickname') {
            $nickname = trim($_POST['nickname'] ?? '');
            $result = UserAuth::updateNickname($user['id'], $nickname);
            if ($result['success']) {
                $success = '昵称更新成功';
                // 重新获取用户信息
                $user = UserAuth::currentUser();
                
                // 如果是 AJAX 请求，返回 JSON
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => $success,
                        'nickname' => $user['nickname'] ?? '',
                        'email' => $user['email'] ?? ''
                    ]);
                    exit;
                }
            } else {
                $errors['nickname'] = $result['message'] ?? '昵称更新失败';
                
                // 如果是 AJAX 请求，返回 JSON
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => $errors['nickname']
                    ]);
                    exit;
                }
            }
        } elseif ($action === 'update_profile') {
            $profileData = [
                'gender' => $_POST['gender'] ?? '',
                'birth_date' => $_POST['birth_date'] ?? '',
                'zodiac' => $_POST['zodiac'] ?? '',
                'chinese_zodiac' => $_POST['chinese_zodiac'] ?? '',
                'personality' => $_POST['personality'] ?? '',
            ];
            $result = UserAuth::updateProfile($user['id'], $profileData);
            if ($result['success']) {
                $success = '个人信息更新成功';
                // 重新获取用户信息
                $user = UserAuth::currentUser();
                
                // 如果是 AJAX 请求，返回 JSON
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => true,
                        'message' => $success,
                        'profile' => [
                            'gender' => $user['gender'] ?? '',
                            'birth_date' => $user['birth_date'] ?? '',
                            'zodiac' => $user['zodiac'] ?? '',
                            'chinese_zodiac' => $user['chinese_zodiac'] ?? '',
                            'personality' => $user['personality'] ?? ''
                        ]
                    ]);
                    exit;
                }
            } else {
                $errors['profile'] = $result['message'] ?? '个人信息更新失败';
                
                // 如果是 AJAX 请求，返回 JSON
                if ($isAjax) {
                    header('Content-Type: application/json');
                    echo json_encode([
                        'success' => false,
                        'message' => $errors['profile']
                    ]);
                    exit;
                }
            }
        }
    }
    
    // 如果是 AJAX 请求但还没有退出（说明是其他操作或错误），返回 JSON
    if ($isAjax) {
        header('Content-Type: application/json');
        $response = ['success' => false];
        if (!empty($errors)) {
            $response['message'] = implode('; ', $errors);
        } elseif (!empty($success)) {
            $response['success'] = true;
            $response['message'] = $success;
        } else {
            $response['message'] = '未知错误';
        }
        echo json_encode($response);
        exit;
    }
}

// 获取测验记录统计
$statsStmt = $pdo->prepare("
    SELECT COUNT(*) as total_runs
    FROM test_runs
    WHERE user_id = :uid
");
$statsStmt->execute([':uid' => $user['id']]);
$stats = $statsStmt->fetch(PDO::FETCH_ASSOC);
$totalRuns = (int)($stats['total_runs'] ?? 0);

// 获取最近的测验记录（最多5条）
$recentStmt = $pdo->prepare("
    SELECT
        r.id,
        r.created_at,
        r.total_score,
        r.share_token,
        t.title AS test_title,
        t.slug   AS test_slug,
        res.title AS result_title
    FROM test_runs r
    INNER JOIN tests t ON r.test_id = t.id
    LEFT JOIN results res ON r.result_id = res.id
    WHERE r.user_id = :uid
    ORDER BY r.created_at DESC
    LIMIT 5
");
$recentStmt->execute([':uid' => $user['id']]);
$recentRuns = $recentStmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>用户资料 - DoFun</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <script src="/assets/js/theme-toggle.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const themeBtn = document.getElementById('theme-toggle-btn');
            if (themeBtn) {
                themeBtn.addEventListener('click', function() {
                    window.ThemeToggle.toggle();
                });
            }
        });
    </script>
</head>
<body class="page-profile">
<?php render_topbar(); ?>
<div class="profile-container">
    <header class="profile-header">
        <h1>用户资料</h1>
    </header>

    <?php if ($success): ?>
        <div class="alert alert-success" style="margin-bottom: 20px; padding: 12px 16px; background: #d1fae5; color: #065f46; border-radius: 8px; border: 1px solid #6ee7b7;">
            <?= htmlspecialchars($success, ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <?php if (isset($errors['general'])): ?>
        <div class="alert alert-error" style="margin-bottom: 20px; padding: 12px 16px; background: #fee2e2; color: #991b1b; border-radius: 8px; border: 1px solid #fca5a5;">
            <?= htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8') ?>
        </div>
    <?php endif; ?>

    <div class="profile-content">
        <!-- 修改用户名 -->
        <section class="profile-section">
            <h2 class="profile-section-title">修改用户名</h2>
            <form method="POST" class="profile-form">
                <?= CSRF::getTokenField() ?>
                <input type="hidden" name="action" value="update_username">
                <div class="form-group">
                    <label for="username">新用户名</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        value="<?= htmlspecialchars($user['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        required
                        pattern="[A-Za-z0-9]{3,25}"
                        title="3-25 位英文和数字组合"
                    >
                    <small class="form-hint">用户名需为 3-25 位英文和数字组合</small>
                    <?php if (isset($errors['username'])): ?>
                        <div class="form-error"><?= htmlspecialchars($errors['username'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-primary">更新用户名</button>
            </form>
        </section>

        <!-- 修改密码 -->
        <section class="profile-section">
            <h2 class="profile-section-title">修改密码</h2>
            <form method="POST" class="profile-form">
                <?= CSRF::getTokenField() ?>
                <input type="hidden" name="action" value="update_password">
                <div class="form-group">
                    <label for="old_password">原密码</label>
                    <input 
                        type="password" 
                        id="old_password" 
                        name="old_password" 
                        required
                        minlength="6"
                        maxlength="20"
                    >
                </div>
                <div class="form-group">
                    <label for="new_password">新密码</label>
                    <input 
                        type="password" 
                        id="new_password" 
                        name="new_password" 
                        required
                        minlength="6"
                        maxlength="20"
                    >
                    <small class="form-hint">密码长度需在 6-20 位</small>
                </div>
                <div class="form-group">
                    <label for="confirm_password">确认新密码</label>
                    <input 
                        type="password" 
                        id="confirm_password" 
                        name="confirm_password" 
                        required
                        minlength="6"
                        maxlength="20"
                    >
                    <?php if (isset($errors['password'])): ?>
                        <div class="form-error"><?= htmlspecialchars($errors['password'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>
                <button type="submit" class="btn-primary">更新密码</button>
            </form>
        </section>

        <!-- 修改昵称 -->
        <section class="profile-section">
            <h2 class="profile-section-title">修改昵称</h2>
            <form method="POST" class="profile-form" id="nickname-form" data-action="update_nickname">
                <?= CSRF::getTokenField() ?>
                <input type="hidden" name="action" value="update_nickname">
                <div class="form-group">
                    <label for="nickname">昵称</label>
                    <input 
                        type="text" 
                        id="nickname" 
                        name="nickname" 
                        value="<?= htmlspecialchars($user['nickname'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        maxlength="15"
                    >
                    <small class="form-hint">昵称长度需在 3-15 位，留空则清除昵称</small>
                    <div class="form-error" id="nickname-error" style="display: none;"></div>
                    <div class="form-success" id="nickname-success" style="display: none; color: #065f46; margin-top: 8px;"></div>
                </div>
                <button type="submit" class="btn-primary">更新昵称</button>
            </form>
        </section>

        <!-- 个人信息 -->
        <section class="profile-section">
            <h2 class="profile-section-title">个人信息</h2>
            <form method="POST" class="profile-form" id="profile-form" data-action="update_profile">
                <?= CSRF::getTokenField() ?>
                <input type="hidden" name="action" value="update_profile">
                <div class="form-group">
                    <label for="gender">性别</label>
                    <select id="gender" name="gender">
                        <option value="">不选择</option>
                        <option value="male" <?= ($user['gender'] ?? '') === 'male' ? 'selected' : '' ?>>男</option>
                        <option value="female" <?= ($user['gender'] ?? '') === 'female' ? 'selected' : '' ?>>女</option>
                        <option value="other" <?= ($user['gender'] ?? '') === 'other' ? 'selected' : '' ?>>其他</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="birth_date">出生年月日</label>
                    <input 
                        type="date" 
                        id="birth_date" 
                        name="birth_date" 
                        value="<?= htmlspecialchars($user['birth_date'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    >
                    <small class="form-hint">可选，用于计算年龄</small>
                </div>
                <div class="form-group">
                    <label for="zodiac">星座</label>
                    <select id="zodiac" name="zodiac" class="form-select">
                        <option value="">不选择</option>
                        <option value="白羊座" <?= ($user['zodiac'] ?? '') === '白羊座' ? 'selected' : '' ?>>白羊座</option>
                        <option value="金牛座" <?= ($user['zodiac'] ?? '') === '金牛座' ? 'selected' : '' ?>>金牛座</option>
                        <option value="双子座" <?= ($user['zodiac'] ?? '') === '双子座' ? 'selected' : '' ?>>双子座</option>
                        <option value="巨蟹座" <?= ($user['zodiac'] ?? '') === '巨蟹座' ? 'selected' : '' ?>>巨蟹座</option>
                        <option value="狮子座" <?= ($user['zodiac'] ?? '') === '狮子座' ? 'selected' : '' ?>>狮子座</option>
                        <option value="处女座" <?= ($user['zodiac'] ?? '') === '处女座' ? 'selected' : '' ?>>处女座</option>
                        <option value="天秤座" <?= ($user['zodiac'] ?? '') === '天秤座' ? 'selected' : '' ?>>天秤座</option>
                        <option value="天蝎座" <?= ($user['zodiac'] ?? '') === '天蝎座' ? 'selected' : '' ?>>天蝎座</option>
                        <option value="射手座" <?= ($user['zodiac'] ?? '') === '射手座' ? 'selected' : '' ?>>射手座</option>
                        <option value="摩羯座" <?= ($user['zodiac'] ?? '') === '摩羯座' ? 'selected' : '' ?>>摩羯座</option>
                        <option value="水瓶座" <?= ($user['zodiac'] ?? '') === '水瓶座' ? 'selected' : '' ?>>水瓶座</option>
                        <option value="双鱼座" <?= ($user['zodiac'] ?? '') === '双鱼座' ? 'selected' : '' ?>>双鱼座</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="chinese_zodiac">属相</label>
                    <select id="chinese_zodiac" name="chinese_zodiac" class="form-select">
                        <option value="">不选择</option>
                        <option value="鼠" <?= ($user['chinese_zodiac'] ?? '') === '鼠' ? 'selected' : '' ?>>鼠</option>
                        <option value="牛" <?= ($user['chinese_zodiac'] ?? '') === '牛' ? 'selected' : '' ?>>牛</option>
                        <option value="虎" <?= ($user['chinese_zodiac'] ?? '') === '虎' ? 'selected' : '' ?>>虎</option>
                        <option value="兔" <?= ($user['chinese_zodiac'] ?? '') === '兔' ? 'selected' : '' ?>>兔</option>
                        <option value="龙" <?= ($user['chinese_zodiac'] ?? '') === '龙' ? 'selected' : '' ?>>龙</option>
                        <option value="蛇" <?= ($user['chinese_zodiac'] ?? '') === '蛇' ? 'selected' : '' ?>>蛇</option>
                        <option value="马" <?= ($user['chinese_zodiac'] ?? '') === '马' ? 'selected' : '' ?>>马</option>
                        <option value="羊" <?= ($user['chinese_zodiac'] ?? '') === '羊' ? 'selected' : '' ?>>羊</option>
                        <option value="猴" <?= ($user['chinese_zodiac'] ?? '') === '猴' ? 'selected' : '' ?>>猴</option>
                        <option value="鸡" <?= ($user['chinese_zodiac'] ?? '') === '鸡' ? 'selected' : '' ?>>鸡</option>
                        <option value="狗" <?= ($user['chinese_zodiac'] ?? '') === '狗' ? 'selected' : '' ?>>狗</option>
                        <option value="猪" <?= ($user['chinese_zodiac'] ?? '') === '猪' ? 'selected' : '' ?>>猪</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="personality">人格</label>
                    <select id="personality" name="personality" class="form-select">
                        <option value="">不选择</option>
                        <option value="INTJ" <?= ($user['personality'] ?? '') === 'INTJ' ? 'selected' : '' ?>>INTJ - 建筑师</option>
                        <option value="INTP" <?= ($user['personality'] ?? '') === 'INTP' ? 'selected' : '' ?>>INTP - 逻辑学家</option>
                        <option value="ENTJ" <?= ($user['personality'] ?? '') === 'ENTJ' ? 'selected' : '' ?>>ENTJ - 指挥官</option>
                        <option value="ENTP" <?= ($user['personality'] ?? '') === 'ENTP' ? 'selected' : '' ?>>ENTP - 辩论家</option>
                        <option value="INFJ" <?= ($user['personality'] ?? '') === 'INFJ' ? 'selected' : '' ?>>INFJ - 提倡者</option>
                        <option value="INFP" <?= ($user['personality'] ?? '') === 'INFP' ? 'selected' : '' ?>>INFP - 调停者</option>
                        <option value="ENFJ" <?= ($user['personality'] ?? '') === 'ENFJ' ? 'selected' : '' ?>>ENFJ - 主人公</option>
                        <option value="ENFP" <?= ($user['personality'] ?? '') === 'ENFP' ? 'selected' : '' ?>>ENFP - 竞选者</option>
                        <option value="ISTJ" <?= ($user['personality'] ?? '') === 'ISTJ' ? 'selected' : '' ?>>ISTJ - 物流师</option>
                        <option value="ISFJ" <?= ($user['personality'] ?? '') === 'ISFJ' ? 'selected' : '' ?>>ISFJ - 守卫者</option>
                        <option value="ESTJ" <?= ($user['personality'] ?? '') === 'ESTJ' ? 'selected' : '' ?>>ESTJ - 总经理</option>
                        <option value="ESFJ" <?= ($user['personality'] ?? '') === 'ESFJ' ? 'selected' : '' ?>>ESFJ - 执政官</option>
                        <option value="ISTP" <?= ($user['personality'] ?? '') === 'ISTP' ? 'selected' : '' ?>>ISTP - 鉴赏家</option>
                        <option value="ISFP" <?= ($user['personality'] ?? '') === 'ISFP' ? 'selected' : '' ?>>ISFP - 探险家</option>
                        <option value="ESTP" <?= ($user['personality'] ?? '') === 'ESTP' ? 'selected' : '' ?>>ESTP - 企业家</option>
                        <option value="ESFP" <?= ($user['personality'] ?? '') === 'ESFP' ? 'selected' : '' ?>>ESFP - 表演者</option>
                    </select>
                    <small class="form-hint">MBTI 16型人格类型</small>
                </div>
                <div class="form-error" id="profile-error" style="display: none;"></div>
                <div class="form-success" id="profile-success" style="display: none; color: #065f46; margin-top: 8px;"></div>
                <button type="submit" class="btn-primary">更新个人信息</button>
            </form>
        </section>

        <!-- 测验记录 -->
        <section class="profile-section">
            <div class="profile-section-header">
                <h2 class="profile-section-title">测验记录</h2>
                <a href="/my_tests.php" class="btn-link">查看全部 →</a>
            </div>
            
            <div class="profile-stats">
                <div class="stat-item">
                    <span class="stat-label">总测验次数</span>
                    <span class="stat-value"><?= $totalRuns ?></span>
                </div>
            </div>

            <?php if (empty($recentRuns)): ?>
                <p class="profile-empty">你还没有任何测验记录，去首页找一个喜欢的测验试试吧～</p>
                <p><a href="/">返回首页</a></p>
            <?php else: ?>
                <div class="profile-runs-list">
                    <?php foreach ($recentRuns as $run): ?>
                        <div class="profile-run-item">
                            <div class="run-item-main">
                                <h3 class="run-item-title">
                                    <a href="/test.php?slug=<?= urlencode($run['test_slug']) ?>">
                                        <?= htmlspecialchars($run['test_title'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </h3>
                                <div class="run-item-meta">
                                    <?php if ($run['result_title']): ?>
                                        <span class="run-item-result">
                                            <?php
                                            $shareLink = !empty($run['share_token'])
                                                ? '/result.php?token=' . urlencode($run['share_token'])
                                                : '';
                                            ?>
                                            <?php if ($shareLink): ?>
                                                <a href="<?= $shareLink ?>">
                                                    <?= htmlspecialchars($run['result_title'], ENT_QUOTES, 'UTF-8') ?>
                                                </a>
                                            <?php else: ?>
                                                <?= htmlspecialchars($run['result_title'], ENT_QUOTES, 'UTF-8') ?>
                                            <?php endif; ?>
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($run['total_score'] !== null): ?>
                                        <span class="run-item-score">得分: <?= (int)$run['total_score'] ?></span>
                                    <?php endif; ?>
                                    <span class="run-item-time"><?= htmlspecialchars($run['created_at'], ENT_QUOTES, 'UTF-8') ?></span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if ($totalRuns > 5): ?>
                    <div style="margin-top: 16px; text-align: center;">
                        <a href="/my_tests.php" class="btn-secondary">查看全部记录</a>
                    </div>
                <?php endif; ?>
            <?php endif; ?>
        </section>
    </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // 更新顶部导航栏昵称显示
    function updateTopbarNickname(nickname, email) {
        var topbarNicknames = document.querySelectorAll('.tub-nickname');
        var displayName = nickname && nickname.trim() !== '' ? nickname : email;
        topbarNicknames.forEach(function(el) {
            var textSpan = el.querySelector('.tub-text');
            if (textSpan) {
                textSpan.textContent = displayName;
            }
            el.setAttribute('title', displayName);
        });
    }

    // 处理昵称表单提交
    var nicknameForm = document.getElementById('nickname-form');
    if (nicknameForm) {
        nicknameForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            var errorDiv = document.getElementById('nickname-error');
            var successDiv = document.getElementById('nickname-success');
            var submitBtn = this.querySelector('button[type="submit"]');
            var originalText = submitBtn.textContent;
            
            // 隐藏之前的消息
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            // 禁用按钮
            submitBtn.disabled = true;
            submitBtn.textContent = '更新中...';
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    // 成功：更新昵称显示
                    var nicknameInput = document.getElementById('nickname');
                    var newNickname = data.nickname || '';
                    var userEmail = data.email || '';
                    
                    // 更新顶部导航栏昵称
                    updateTopbarNickname(newNickname, userEmail);
                    
                    // 显示成功消息
                    successDiv.textContent = data.message || '昵称更新成功';
                    successDiv.style.display = 'block';
                    
                    // 3秒后隐藏成功消息
                    setTimeout(function() {
                        successDiv.style.display = 'none';
                    }, 3000);
                } else {
                    // 显示错误消息
                    errorDiv.textContent = data.message || '更新失败';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(function(error) {
                errorDiv.textContent = '更新失败，请稍后重试';
                errorDiv.style.display = 'block';
            })
            .finally(function() {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }

    // 处理个人信息表单提交
    var profileForm = document.getElementById('profile-form');
    if (profileForm) {
        profileForm.addEventListener('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            var errorDiv = document.getElementById('profile-error');
            var successDiv = document.getElementById('profile-success');
            var submitBtn = this.querySelector('button[type="submit"]');
            var originalText = submitBtn.textContent;
            
            // 隐藏之前的消息
            errorDiv.style.display = 'none';
            successDiv.style.display = 'none';
            
            // 禁用按钮
            submitBtn.disabled = true;
            submitBtn.textContent = '更新中...';
            
            fetch(window.location.href, {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: formData
            })
            .then(function(response) {
                return response.json();
            })
            .then(function(data) {
                if (data.success) {
                    // 成功：更新表单值
                    if (data.profile) {
                        var profile = data.profile;
                        if (document.getElementById('gender')) document.getElementById('gender').value = profile.gender || '';
                        if (document.getElementById('birth_date')) document.getElementById('birth_date').value = profile.birth_date || '';
                        if (document.getElementById('zodiac')) document.getElementById('zodiac').value = profile.zodiac || '';
                        if (document.getElementById('chinese_zodiac')) document.getElementById('chinese_zodiac').value = profile.chinese_zodiac || '';
                        if (document.getElementById('personality')) document.getElementById('personality').value = profile.personality || '';
                    }
                    
                    // 显示成功消息
                    successDiv.textContent = data.message || '个人信息更新成功';
                    successDiv.style.display = 'block';
                    
                    // 3秒后隐藏成功消息
                    setTimeout(function() {
                        successDiv.style.display = 'none';
                    }, 3000);
                } else {
                    // 显示错误消息
                    errorDiv.textContent = data.message || '更新失败';
                    errorDiv.style.display = 'block';
                }
            })
            .catch(function(error) {
                errorDiv.textContent = '更新失败，请稍后重试';
                errorDiv.style.display = 'block';
            })
            .finally(function() {
                submitBtn.disabled = false;
                submitBtn.textContent = originalText;
            });
        });
    }
});
</script>
</body>
</html>

