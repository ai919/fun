<?php
require __DIR__ . '/lib/db_connect.php';

$stmt = $pdo->query("SELECT * FROM tests ORDER BY id DESC");
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>DoFun空间 · 在趣味中更好地发现自己</title>
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
