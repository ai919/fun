<?php
/**
 * 统一的顶部导航栏组件
 * 在所有前台页面使用，包含登录/注册状态和返回首页功能
 */
require_once __DIR__ . '/user_auth.php';
require_once __DIR__ . '/motivational_quotes.php';

function render_topbar($isTestPage = false, $isHomePage = false) {
    $user = UserAuth::currentUser();
    
    // 如果在测验页面，显示特殊布局
    if ($isTestPage) {
        $randomQuote = MotivationalQuotes::getRandomQuote();
        ?>
<div class="top-user-bar top-user-bar--test">
    <div class="top-user-bar-inner top-user-bar-inner--test">
        <!-- 左侧：返回首页 -->
        <div class="topbar-left">
            <a href="/" class="topbar-home-link">
                <span class="home-arrow">←</span>
                <span class="home-text">返回首页</span>
            </a>
        </div>
        
        <!-- 中间：随机名言 -->
        <div class="topbar-center">
            <?php if ($randomQuote): ?>
                <div class="topbar-quote">
                    <span class="quote-text"><?= htmlspecialchars($randomQuote) ?></span>
                </div>
            <?php endif; ?>
        </div>
        
        <!-- 右侧：用户信息和主题切换 -->
        <div class="topbar-right">
            <button type="button" id="theme-toggle-btn" class="theme-toggle-btn" aria-label="切换主题" title="切换暗色/亮色模式">
                <span class="theme-icon-light">☀️</span>
                <span class="theme-icon-dark">🌙</span>
            </button>
            <?php if ($user): ?>
                <a href="/profile.php" class="tub-nickname">
                    <?php echo htmlspecialchars($user['nickname'] ?: $user['email']); ?>
                </a>
                <a href="/my_tests.php" class="tub-link">我的测验</a>
                <a href="/logout.php" class="tub-link">退出</a>
            <?php else: ?>
                <a href="/login.php" class="tub-link">登录</a>
                <a href="/register.php" class="tub-link">注册</a>
            <?php endif; ?>
        </div>
    </div>
</div>
        <?php
        return;
    }
    
    // 默认布局（非测验页面）
    ?>
<div class="top-user-bar">
    <div class="top-user-bar-inner">
        <?php if (!$isHomePage): ?>
        <a href="/" class="topbar-home-link">
            <span class="home-arrow">←</span>
            <span class="home-text">返回首页</span>
        </a>
        <?php endif; ?>
        <div class="topbar-spacer"></div>
        <button type="button" id="theme-toggle-btn" class="theme-toggle-btn" aria-label="切换主题" title="切换暗色/亮色模式">
            <span class="theme-icon-light">☀️</span>
            <span class="theme-icon-dark">🌙</span>
        </button>
        <?php if ($user): ?>
            <a href="/profile.php" class="tub-nickname">
                <?php echo htmlspecialchars($user['nickname'] ?: $user['email']); ?>
            </a>
            <a href="/my_tests.php" class="tub-link">我的测验</a>
            <a href="/logout.php" class="tub-link">退出</a>
        <?php else: ?>
            <a href="/login.php" class="tub-link">登录</a>
            <a href="/register.php" class="tub-link">注册</a>
        <?php endif; ?>
    </div>
</div>
    <?php
}

