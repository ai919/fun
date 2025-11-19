<?php
require __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/seo_helper.php';

$stmt = $pdo->prepare("SELECT * FROM tests WHERE (status = 'published' OR status = 1) ORDER BY sort_order ASC, id DESC");
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

            $titleStyle = '';
            if (!empty($test['title_color'])) {
                $color = htmlspecialchars($test['title_color'], ENT_QUOTES, 'UTF-8');
                $titleStyle = "color: {$color};";
            }

            $emoji = !empty($test['title_emoji'])
                ? htmlspecialchars($test['title_emoji'], ENT_QUOTES, 'UTF-8')
                : '🧩';

            $runCount = isset($test['run_count']) ? (int)$test['run_count'] : 0;
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
                    <span class="quiz-emoji"><?= $emoji ?></span>
                </div>

                <h2 class="quiz-card-title" style="<?= $titleStyle ?>">
                    <?= htmlspecialchars($test['title'], ENT_QUOTES, 'UTF-8') ?>
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

                <div class="quiz-card-meta">
                    <div class="quiz-meta-count">
                        已有 <?= $runCount ?> 人测试
                    </div>
                </div>

                <div class="quiz-card-footer">
                    <a class="quiz-btn" href="/<?= urlencode($test['slug']) ?>">
                        开始测验
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
