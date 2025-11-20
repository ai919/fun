<?php
require __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/seo_helper.php';

$stmt = $pdo->prepare("
    SELECT
        t.*,
        (SELECT COUNT(*) FROM test_runs r WHERE r.test_id = t.id) AS play_count
    FROM tests t
    WHERE (t.status = 'published' OR t.status = 1)
    ORDER BY t.sort_order DESC, t.id DESC
");
$stmt->execute();
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

$seo = [
    'title'       => 'DoFun 性格实验室 - 趣味测验中心',
    'description' => 'DoFun 甄选的测验合集：关于金钱、安全感、亲密关系与自我探索的小实验。',
    'url'         => df_current_url(),
    'image'       => df_base_url() . '/og.php?scope=home',
];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title><?= htmlspecialchars($seo['title']) ?></title>
    <meta name="description" content="<?= htmlspecialchars($seo['description']) ?>">
    <link rel="canonical" href="<?= htmlspecialchars($seo['url']) ?>">
    <meta property="og:title" content="<?= htmlspecialchars($seo['title']) ?>">
    <meta property="og:description" content="<?= htmlspecialchars($seo['description']) ?>">
    <meta property="og:image" content="<?= htmlspecialchars($seo['image']) ?>">
    <meta property="og:url" content="<?= htmlspecialchars($seo['url']) ?>">
    <meta property="og:type" content="website">
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" href="/favicon.ico">
</head>
<body>
<main class="home">
    <header class="site-header">
        <div class="site-title-wrap">
            <a href="/" class="site-title">DoFun空间</a>
        </div>
        <p class="site-subtitle">在趣味中更好地发现自己。</p>
    </header>

    <div class="quiz-grid">
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

            $emojiRaw = trim($test['emoji'] ?? '');
            $emoji = $emojiRaw !== '' ? htmlspecialchars($emojiRaw, ENT_QUOTES, 'UTF-8') : '';

            $playCount = isset($test['play_count']) ? (int)$test['play_count'] : 0;
            $playText = $playCount > 0 ? "已有 {$playCount} 人测试" : '等待第一个测试者';
            ?>
            <article class="quiz-card">
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
                    <h2 class="quiz-card-title" style="<?= $titleStyle ?>">
                        <a class="card-title" href="/test.php?slug=<?= urlencode($test['slug']) ?>">
                            <?php if ($emoji !== ''): ?>
                                <span class="card-emoji"><?= $emoji ?></span>
                            <?php endif; ?>
                            <span class="card-title-text">
                                <?= htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </a>
                    </h2>

                    <?php if (!empty($test['subtitle'])): ?>
                        <p class="quiz-card-desc">
                            <?= htmlspecialchars($test['subtitle'], ENT_QUOTES, 'UTF-8') ?>
                        </p>
                    <?php elseif (!empty($test['description'])): ?>
                        <p class="quiz-card-desc">
                            <?= htmlspecialchars(mb_substr($test['description'], 0, 48), ENT_QUOTES, 'UTF-8') ?>…
                        </p>
                    <?php endif; ?>
                </div>

                <div class="quiz-card-meta">
                    <div class="quiz-meta-count">
                        <?= htmlspecialchars($playText, ENT_QUOTES, 'UTF-8') ?>
                    </div>
                </div>

                <div class="quiz-card-footer">
                    <a class="quiz-btn" href="/test.php?slug=<?= urlencode($test['slug']) ?>">
                        开始测验
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
