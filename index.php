<?php
require __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/seo_helper.php';
require_once __DIR__ . '/lib/user_auth.php';
require_once __DIR__ . '/lib/html_purifier.php';
require_once __DIR__ . '/lib/Constants.php';
require_once __DIR__ . '/lib/CacheHelper.php';
require_once __DIR__ . '/lib/SettingsHelper.php';
require_once __DIR__ . '/lib/topbar.php';
require_once __DIR__ . '/lib/AdHelper.php';

// 获取首页显示测验数量限制
$homeTestsLimit = (int)SettingsHelper::get('home_tests_limit', 20);
$homeTestsLimit = max(1, min(200, $homeTestsLimit)); // 限制在1-200之间

// 尝试从缓存获取测验列表（缓存5分钟）
$cacheKey = 'published_tests_list';
$tests = CacheHelper::get($cacheKey, 300);

if ($tests === null) {
    // 缓存未命中，从数据库查询
    $stmt = $pdo->prepare("
        SELECT
            t.*,
            (SELECT COUNT(*) FROM test_runs r WHERE r.test_id = t.id) AS play_count
        FROM tests t
        WHERE (t.status = ? OR t.status = 1)
        ORDER BY t.sort_order DESC, t.id DESC
        LIMIT ?
    ");
    $stmt->bindValue(1, Constants::TEST_STATUS_PUBLISHED, PDO::PARAM_STR);
    $stmt->bindValue(2, $homeTestsLimit, PDO::PARAM_INT);
    $stmt->execute();
    $tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // 存入缓存
    CacheHelper::set($cacheKey, $tests);
} else {
    // 如果从缓存获取，也需要限制数量
    $tests = array_slice($tests, 0, $homeTestsLimit);
}

// 获取热门标签（使用最多的标签）
$tagLimit = (int)SettingsHelper::get('home_tag_limit', 10);
$tagLimit = max(1, min(50, $tagLimit)); // 限制在1-50之间

$topTagsCacheKey = 'top_tags_' . $tagLimit;
$topTags = CacheHelper::get($topTagsCacheKey, 600); // 缓存10分钟

if ($topTags === null) {
    // 从所有已发布的测验中统计标签使用次数
    $tagCounts = [];
    foreach ($tests as $test) {
        if (!empty($test['tags'])) {
            $tags = array_filter(array_map('trim', explode(',', $test['tags'])));
            foreach ($tags as $tag) {
                if (!empty($tag)) {
                    $tagCounts[$tag] = ($tagCounts[$tag] ?? 0) + 1;
                }
            }
        }
    }
    
    // 按使用次数排序，取前N个
    arsort($tagCounts);
    $topTags = array_slice(array_keys($tagCounts), 0, $tagLimit, true);
    
    // 存入缓存
    CacheHelper::set($topTagsCacheKey, $topTags);
}

$seo = build_seo_meta('home', [
    'breadcrumbs' => [
        ['name' => '首页', 'url' => '/'],
    ],
]);
$user = UserAuth::currentUser();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<?php render_seo_head($seo); ?>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" href="/favicon.ico">
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
    <script>
        // 标签筛选功能（客户端筛选，提升用户体验）
        document.addEventListener('DOMContentLoaded', function() {
            const tagFilterItems = document.querySelectorAll('.tag-filter-item');
            const testCards = document.querySelectorAll('.test-card');
            
            // 从URL获取当前选中的标签
            const urlParams = new URLSearchParams(window.location.search);
            const currentTag = urlParams.get('tag') || '';
            
            // 筛选函数
            function filterByTag(tag) {
                let visibleCount = 0;
                testCards.forEach(function(card) {
                    const cardTags = card.getAttribute('data-tags');
                    if (!tag || (cardTags && cardTags.includes(tag))) {
                        card.style.display = '';
                        visibleCount++;
                    } else {
                        card.style.display = 'none';
                    }
                });
                
                // 更新活动状态
                tagFilterItems.forEach(function(btn) {
                    btn.classList.remove('active');
                    const btnTag = btn.getAttribute('data-tag') || '';
                    if ((!tag && !btnTag) || (tag && btnTag === tag)) {
                        btn.classList.add('active');
                    }
                });
                
                // 显示/隐藏无结果提示
                const grid = document.getElementById('testsGrid');
                let noResultsMsg = document.getElementById('noResultsMessage');
                if (visibleCount === 0) {
                    if (!noResultsMsg) {
                        noResultsMsg = document.createElement('div');
                        noResultsMsg.id = 'noResultsMessage';
                        noResultsMsg.className = 'no-results-message';
                        noResultsMsg.textContent = '暂无匹配的测验';
                        grid.parentNode.insertBefore(noResultsMsg, grid.nextSibling);
                    }
                    noResultsMsg.style.display = 'block';
                } else {
                    if (noResultsMsg) {
                        noResultsMsg.style.display = 'none';
                    }
                }
            }
            
            // 页面加载时应用筛选
            if (currentTag) {
                filterByTag(currentTag);
            }
            
            // 绑定点击事件（包括"全部"按钮）
            tagFilterItems.forEach(function(item) {
                item.addEventListener('click', function(e) {
                    e.preventDefault();
                    const tag = this.getAttribute('data-tag') || '';
                    
                    // 更新URL（不刷新页面）
                    const url = new URL(window.location);
                    if (tag) {
                        url.searchParams.set('tag', tag);
                    } else {
                        url.searchParams.delete('tag');
                    }
                    window.history.pushState({}, '', url);
                    
                    // 应用筛选
                    filterByTag(tag);
                });
            });
            
            // 处理浏览器前进/后退
            window.addEventListener('popstate', function() {
                const urlParams = new URLSearchParams(window.location.search);
                const tag = urlParams.get('tag') || '';
                filterByTag(tag);
            });
        });
    </script>
    <?php SettingsHelper::renderGoogleAnalytics(); ?>
</head>
<body>
<?php if (!defined('IN_ADMIN')): ?>
<?php render_topbar(false, true); ?>
<?php endif; ?>
<main class="home">
    <div class="page-container">
        <header class="site-header">
            <div class="site-title-wrap">
                <h1 class="site-title"><?= htmlspecialchars(SettingsHelper::get('site_name', 'DoFun心理实验空间')) ?></h1>
            </div>
            <?php $siteSubtitle = SettingsHelper::get('site_subtitle', '心理 性格 性情：更专业的在线测验实验室'); ?>
            <?php if (!empty($siteSubtitle)): ?>
            <p class="site-subtitle"><?= htmlspecialchars($siteSubtitle) ?></p>
            <?php endif; ?>
        </header>

        <?php
        // 首页顶部广告
        $homeTopAd = AdHelper::render('home_top', 'home');
        if ($homeTopAd):
        ?>
        <div class="ad-wrapper ad-wrapper--home-top">
            <?= $homeTopAd ?>
        </div>
        <?php endif; ?>

        <!-- 标签筛选器 -->
        <?php if (!empty($topTags)): ?>
        <?php
        // 获取当前选中的标签（用于高亮显示）
        $selectedTag = isset($_GET['tag']) ? trim($_GET['tag']) : '';
        // 定义标签颜色主题数组
        $tagColors = ['blue', 'purple', 'pink', 'green', 'orange', 'teal', 'indigo', 'rose', 'amber', 'cyan'];
        ?>
        <div class="tag-filter-section">
            <div class="tag-filter-list">
                <a href="/" class="tag-filter-item tag-color-default <?= $selectedTag === '' ? 'active' : '' ?>">
                    全部
                </a>
                <?php foreach ($topTags as $index => $tag): ?>
                <?php $colorClass = 'tag-color-' . $tagColors[$index % count($tagColors)]; ?>
                <a href="/?tag=<?= urlencode($tag) ?>" 
                   class="tag-filter-item <?= $colorClass ?> <?= $selectedTag === $tag ? 'active' : '' ?>"
                   data-tag="<?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>">
                    <?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?>
                </a>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="quiz-grid tests-grid" id="testsGrid">
        <?php foreach ($tests as $test): ?>
            <?php
            $tags = [];
            if (!empty($test['tags'])) {
                $tags = array_filter(array_map('trim', explode(',', $test['tags'])));
            }

            $titleStyle = 'color: #111827;';
            $titleColor = trim($test['title_color'] ?? '');
            if ($titleColor !== '' && preg_match('/^#[0-9a-fA-F]{6}$/', $titleColor)) {
                $color = htmlspecialchars($titleColor, ENT_QUOTES, 'UTF-8');
                $titleStyle = "color: {$color};";
            }

                $emojiRaw = trim($test['emoji'] ?? ($test['title_emoji'] ?? ''));
                $emoji = $emojiRaw !== '' ? htmlspecialchars($emojiRaw, ENT_QUOTES, 'UTF-8') : '';

            $realPlayCount = isset($test['play_count']) ? (int)$test['play_count'] : 0;
            $testId = (int)$test['id'];
            $playCount = SettingsHelper::getBeautifiedPlayCount($realPlayCount, $testId);
            $playText = $playCount > 0 ? "已有 {$playCount} 人测验" : '等待第一位测验者';
            ?>
                <article class="quiz-card test-card" data-tags="<?= htmlspecialchars(implode(',', $tags ?: ['测验']), ENT_QUOTES, 'UTF-8') ?>">
                    <div class="quiz-card-top">
                        <div class="quiz-tag-list">
                            <?php if ($tags): ?>
                                <?php foreach ($tags as $tag): ?>
                                    <span class="quiz-tag"><?= htmlspecialchars($tag, ENT_QUOTES, 'UTF-8') ?></span>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <span class="quiz-tag">测验</span>
                        <?php endif; ?>
                    </div>
                </div>

                    <div class="quiz-card-body">
                        <div class="card-header">
                            <div class="card-title-row">
                                <?php if ($emoji !== ''): ?>
                                    <span class="card-title-emoji"><?= $emoji ?></span>
                                <?php endif; ?>
                                <h3 class="card-title" style="<?= $titleStyle ?>">
                                    <a class="card-title-link" href="/test.php?slug=<?= urlencode($test['slug']) ?>">
                                        <?= htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') ?>
                                    </a>
                                </h3>
                            </div>
                        </div>

                    <?php if (!empty($test['subtitle'])): ?>
                        <p class="card-description">
                            <?= htmlspecialchars($test['subtitle'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php elseif (!empty($test['description'])): ?>
                        <p class="card-description">
                            <?= htmlspecialchars(mb_substr(strip_tags($test['description']), 0, 80), ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php else: ?>
                        <p class="card-description">&nbsp;</p>
                    <?php endif; ?>
                </div>

                    <div class="card-footer">
                        <?php if ($playCount > 0): ?>
                            <span class="card-play-count">
                                已有 <strong><?= $playCount ?></strong> 人测验
                            </span>
                        <?php else: ?>
                            <span class="card-play-count">等待第一位测验者</span>
                        <?php endif; ?>
                        <a class="card-button" href="/test.php?slug=<?= urlencode($test['slug']) ?>">
                            开始测验
                        </a>
                    </div>
            </article>
        <?php endforeach; ?>
        </div>

        <?php
        // 首页中间广告（在测验列表中间）
        $homeMiddleAd = AdHelper::render('home_middle', 'home');
        if ($homeMiddleAd):
        ?>
        <div class="ad-wrapper ad-wrapper--home-middle">
            <?= $homeMiddleAd ?>
        </div>
        <?php endif; ?>

        <?php
        // 首页底部广告
        $homeBottomAd = AdHelper::render('home_bottom', 'home');
        if ($homeBottomAd):
        ?>
        <div class="ad-wrapper ad-wrapper--home-bottom">
            <?= $homeBottomAd ?>
        </div>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
