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
    <h1 class="site-title"><a href="/">DoFun空间</a></h1>
    <p class="site-subtitle">在趣味中更好地发现自己</p>

    <div class="test-grid">
        <?php foreach ($tests as $test): ?>
            <?php
            $description = trim($test['description'] ?? '');
            if ($description === '' && !empty($test['subtitle'])) {
                $description = trim($test['subtitle']);
            }
            if ($description !== '') {
                if (function_exists('mb_substr')) {
                    $descFull = $description;
                    $description = mb_substr($descFull, 0, 90, 'UTF-8');
                    if (mb_strlen($descFull, 'UTF-8') > 90) {
                        $description .= '…';
                    }
                } else {
                    $descFull   = $description;
                    $description = substr($descFull, 0, 90);
                    if (strlen($descFull) > 90) {
                        $description .= '…';
                    }
                }
            }

            $stmtRuns = $pdo->prepare("SELECT COUNT(*) FROM test_runs WHERE test_id = ?");
            $stmtRuns->execute([$test['id']]);
            $runCount = (int)$stmtRuns->fetchColumn();

            $emoji      = trim($test['title_emoji'] ?? '');
            $titleColor = trim($test['title_color'] ?? '');
            $tagsRaw    = trim($test['tags'] ?? '');
            $tagsArr    = $tagsRaw !== '' ? array_filter(array_map('trim', explode(',', $tagsRaw))) : [];
            ?>
            <article class="test-card">
                <div class="card-tags">
                    <?php if ($tagsArr): ?>
                        <?php foreach (array_slice($tagsArr, 0, 3) as $tag): ?>
                            <span class="badge badge-primary"><?= htmlspecialchars($tag) ?></span>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <span class="badge badge-muted">通用测验</span>
                    <?php endif; ?>
                </div>

                <h2 class="card-title">
                    <a href="/<?= htmlspecialchars($test['slug']) ?>">
                        <?php if ($emoji !== ''): ?>
                            <span class="card-emoji"><?= htmlspecialchars($emoji) ?></span>
                        <?php endif; ?>
                        <span class="card-title-text"
                              style="<?= $titleColor !== '' ? 'color:' . htmlspecialchars($titleColor) . ';' : '' ?>">
                            <?= htmlspecialchars($test['title']) ?>
                        </span>
                    </a>
                </h2>

                <?php if ($description !== ''): ?>
                    <p class="card-desc">
                        <?= htmlspecialchars($description) ?>
                    </p>
                <?php endif; ?>

                <div class="card-stats">
                    <span>
                        已有 <?= number_format($runCount) ?> 人参加测验
                    </span>
                </div>

                <div class="card-footer">
                    <a class="btn btn-primary btn-lg"
                       href="/<?= htmlspecialchars($test['slug']) ?>">
                        开始测验
                    </a>
                </div>
            </article>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
