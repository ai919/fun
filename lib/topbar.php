<?php
/**
 * 统一的顶部导航栏组件
 * 在所有前台页面使用，包含登录/注册状态和返回首页功能
 */
require_once __DIR__ . '/user_auth.php';
require_once __DIR__ . '/motivational_quotes.php';
require_once __DIR__ . '/NotificationHelper.php';

function render_topbar($isTestPage = false, $isHomePage = false) {
    $user = UserAuth::currentUser();
    $unreadCount = $user ? NotificationHelper::getUnreadCount($user['id']) : 0;
    
    // 如果在测验页面，显示特殊布局
    if ($isTestPage) {
        $randomQuote = MotivationalQuotes::getRandomQuote();
        ?>
<div class="top-user-bar top-user-bar--test">
    <div class="top-user-bar-inner top-user-bar-inner--test">
        <!-- 左侧：返回首页 -->
        <div class="topbar-left">
            <a href="/" class="topbar-home-link">
                <span class="home-arrow">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="15 18 9 12 15 6"></polyline>
                    </svg>
                </span>
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
                <span class="theme-icon-light">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="5"></circle>
                        <line x1="12" y1="1" x2="12" y2="3"></line>
                        <line x1="12" y1="21" x2="12" y2="23"></line>
                        <line x1="4.22" y1="4.22" x2="5.64" y2="5.64"></line>
                        <line x1="18.36" y1="18.36" x2="19.78" y2="19.78"></line>
                        <line x1="1" y1="12" x2="3" y2="12"></line>
                        <line x1="21" y1="12" x2="23" y2="12"></line>
                        <line x1="4.22" y1="19.78" x2="5.64" y2="18.36"></line>
                        <line x1="18.36" y1="5.64" x2="19.78" y2="4.22"></line>
                    </svg>
                </span>
                <span class="theme-icon-dark">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"></path>
                    </svg>
                </span>
            </button>
            <?php if ($user): ?>
                <a href="/notifications.php" class="tub-link tub-link-icon" style="position: relative;" title="通知">
                    <span class="tub-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                            <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                        </svg>
                    </span>
                    <span class="tub-text">通知</span>
                    <?php if ($unreadCount > 0): ?>
                        <span style="
                            position: absolute;
                            top: -4px;
                            right: -8px;
                            background: #ef4444;
                            color: white;
                            font-size: 11px;
                            font-weight: 600;
                            padding: 2px 6px;
                            border-radius: 10px;
                            min-width: 18px;
                            text-align: center;
                            line-height: 1.4;
                        "><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                    <?php endif; ?>
                </a>
                <a href="/profile.php" class="tub-link tub-link-icon tub-nickname" title="<?php echo htmlspecialchars($user['nickname'] ?: $user['email']); ?>">
                    <span class="tub-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                            <circle cx="12" cy="7" r="4"></circle>
                        </svg>
                    </span>
                    <span class="tub-text"><?php echo htmlspecialchars($user['nickname'] ?: $user['email']); ?></span>
                </a>
                <a href="/my_tests.php" class="tub-link tub-link-icon" title="我的测验">
                    <span class="tub-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                            <polyline points="14 2 14 8 20 8"></polyline>
                            <line x1="16" y1="13" x2="8" y2="13"></line>
                            <line x1="16" y1="17" x2="8" y2="17"></line>
                            <polyline points="10 9 9 9 8 9"></polyline>
                        </svg>
                    </span>
                    <span class="tub-text">我的测验</span>
                </a>
                <a href="/logout.php" class="tub-link tub-link-icon" title="退出">
                    <span class="tub-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                            <polyline points="16 17 21 12 16 7"></polyline>
                            <line x1="21" y1="12" x2="9" y2="12"></line>
                        </svg>
                    </span>
                    <span class="tub-text">退出</span>
                </a>
            <?php else: ?>
                <a href="/login.php" class="tub-link tub-link-icon tub-link-login" title="登录">
                    <span class="tub-icon">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                            <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                        </svg>
                    </span>
                    <span class="tub-text">登录</span>
                </a>
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
            <span class="home-arrow">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="15 18 9 12 15 6"></polyline>
                </svg>
            </span>
            <span class="home-text">返回首页</span>
        </a>
        <?php endif; ?>
        <div class="topbar-spacer"></div>
        <button type="button" id="theme-toggle-btn" class="theme-toggle-btn" aria-label="切换主题" title="切换暗色/亮色模式">
            <span class="theme-icon-light">☀️</span>
            <span class="theme-icon-dark">🌙</span>
        </button>
        <?php if ($user): ?>
            <a href="/notifications.php" class="tub-link tub-link-icon" style="position: relative;" title="通知">
                <span class="tub-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"></path>
                        <path d="M13.73 21a2 2 0 0 1-3.46 0"></path>
                    </svg>
                </span>
                <span class="tub-text">通知</span>
                <?php if ($unreadCount > 0): ?>
                    <span style="
                        position: absolute;
                        top: -4px;
                        right: -8px;
                        background: #ef4444;
                        color: white;
                        font-size: 11px;
                        font-weight: 600;
                        padding: 2px 6px;
                        border-radius: 10px;
                        min-width: 18px;
                        text-align: center;
                        line-height: 1.4;
                    "><?= $unreadCount > 99 ? '99+' : $unreadCount ?></span>
                <?php endif; ?>
            </a>
            <a href="/profile.php" class="tub-link tub-link-icon tub-nickname" title="<?php echo htmlspecialchars($user['nickname'] ?: $user['email']); ?>">
                <span class="tub-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                        <circle cx="12" cy="7" r="4"></circle>
                    </svg>
                </span>
                <span class="tub-text"><?php echo htmlspecialchars($user['nickname'] ?: $user['email']); ?></span>
            </a>
            <a href="/my_tests.php" class="tub-link tub-link-icon" title="我的测验">
                <span class="tub-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </span>
                <span class="tub-text">我的测验</span>
            </a>
            <a href="/logout.php" class="tub-link tub-link-icon" title="退出">
                <span class="tub-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"></path>
                        <polyline points="16 17 21 12 16 7"></polyline>
                        <line x1="21" y1="12" x2="9" y2="12"></line>
                    </svg>
                </span>
                <span class="tub-text">退出</span>
            </a>
        <?php else: ?>
            <a href="/login.php" class="tub-link tub-link-icon tub-link-login" title="登录">
                <span class="tub-icon">
                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </span>
                <span class="tub-text">登录</span>
            </a>
        <?php endif; ?>
    </div>
</div>
    <?php
}

