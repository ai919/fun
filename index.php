<?php
require __DIR__ . '/lib/db_connect.php';

// 获取所有测试
$stmt = $pdo->query("SELECT * FROM tests ORDER BY id DESC");
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="utf-8">
    <title>趣味测试中心 · fun.dofun.fun</title>
    <link rel="stylesheet" href="/assets/css/style.css">
    <link rel="icon" type="image/x-icon" href="/favicon.ico">
    <link rel="shortcut icon" href="/favicon.ico">
</head>
<body>
<main class="home">
    <h1>DoFun 空间</h1>
    <p class="home-subtitle">挑一个你感兴趣的测试，开始玩一玩。</p>

    <div class="test-grid">
        <?php foreach ($tests as $test): ?>
            <?php
            $subtitle = $test['subtitle'] ?? '';
            if ($subtitle === '' && !empty($test['description'])) {
                $desc = $test['description'];
                if (function_exists('mb_substr')) {
                    $subtitle = mb_substr($desc, 0, 60, 'UTF-8');
                    if (mb_strlen($desc, 'UTF-8') > 60) {
                        $subtitle .= '…';
                    }
                } else {
                    $subtitle = substr($desc, 0, 60);
                    if (strlen($desc) > 60) {
                        $subtitle .= '…';
                    }
                }
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_runs WHERE test_id = ?");
            $stmt->execute([$test['id']]);
            $runCount = (int)$stmt->fetchColumn();
            ?>
            <article class="test-card">
                <div class="test-card-tag">测试</div>
                <h2 class="test-card-title">
                    <a href="/<?= htmlspecialchars($test['slug']) ?>">
                        <?= htmlspecialchars($test['title']) ?>
                    </a>
                </h2>

                <?php if ($subtitle !== ''): ?>
                    <p class="test-card-subtitle">
                        <?= htmlspecialchars($subtitle) ?>
                    </p>
                <?php endif; ?>

                <div class="test-card-meta">
                    <span class="test-card-count">
                        已有 <?= number_format($runCount) ?> 人测试
                    </span>
                </div>

                <a class="test-card-button"
                   href="/<?= htmlspecialchars($test['slug']) ?>">
                    开始测试
                </a>
            </article>
        <?php endforeach; ?>
    </div>
</main>
</body>
</html>
