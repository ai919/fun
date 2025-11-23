<?php
require_once __DIR__ . '/seo_helper.php';
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/html_purifier.php';
require_once __DIR__ . '/lib/SettingsHelper.php';
require_once __DIR__ . '/lib/topbar.php';
require_once __DIR__ . '/lib/AdHelper.php';

$shareTokenParam = isset($_GET['token']) ? trim((string)$_GET['token']) : '';
$runIdParam      = isset($_GET['run']) ? (int)$_GET['run'] : 0;

$runStmt = null;
$runRow  = null;
if ($shareTokenParam !== '') {
    $runStmt = $pdo->prepare("SELECT * FROM test_runs WHERE share_token = :token LIMIT 1");
    $runStmt->execute([':token' => $shareTokenParam]);
    $runRow = $runStmt->fetch(PDO::FETCH_ASSOC);
} elseif ($runIdParam > 0) {
    $runStmt = $pdo->prepare("SELECT * FROM test_runs WHERE id = :id LIMIT 1");
    $runStmt->execute([':id' => $runIdParam]);
    $runRow = $runStmt->fetch(PDO::FETCH_ASSOC);
}

if (!$runRow) {
    http_response_code(404);
    echo '结果链接已失效或不存在。';
    exit;
}

$testId   = (int)$runRow['test_id'];
$resultId = isset($runRow['result_id']) ? (int)$runRow['result_id'] : 0;

$testStmt = $pdo->prepare("SELECT * FROM tests WHERE id = ? LIMIT 1");
$testStmt->execute([$testId]);
$finalTest = $testStmt->fetch(PDO::FETCH_ASSOC);

$finalResult = null;
if ($resultId > 0) {
    $resStmt = $pdo->prepare("SELECT * FROM results WHERE id = ? AND test_id = ? LIMIT 1");
    $resStmt->execute([$resultId, $testId]);
    $finalResult = $resStmt->fetch(PDO::FETCH_ASSOC);
}

if (!$finalTest || !$finalResult) {
    http_response_code(404);
    echo '结果已失效或未找到。';
    exit;
}

$dimensionScores = [];
require_once __DIR__ . '/lib/Constants.php';
if (strtolower($finalTest['scoring_mode'] ?? Constants::SCORING_MODE_SIMPLE) === Constants::SCORING_MODE_DIMENSIONS) {
    $dimStmt = $pdo->prepare(
        "SELECT dimension_key, score_value
         FROM test_run_scores
         WHERE test_run_id = :rid
         ORDER BY dimension_key ASC"
    );
    $dimStmt->execute([':rid' => (int)$runRow['id']]);
    while ($row = $dimStmt->fetch(PDO::FETCH_ASSOC)) {
        $dimensionScores[$row['dimension_key']] = (float)$row['score_value'];
    }
}

$shareToken = $shareTokenParam;
if ($shareToken === '' && !empty($runRow['share_token'])) {
    $shareToken = $runRow['share_token'];
}
$shareUrl = $shareToken !== ''
    ? build_canonical_url('/result.php?token=' . urlencode($shareToken))
    : build_canonical_url();

$seo = $finalTest && $finalResult
    ? build_seo_meta('result', ['test' => $finalTest, 'result' => $finalResult])
    : build_seo_meta('generic', [
        'title' => '测验结果',
        'description' => '探索你的测验结果。',
        'canonical' => $shareUrl,
    ]);
?>
<!doctype html>
<html lang="zh-CN">
<head>
<?php render_seo_head($seo); ?>
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
    <?php SettingsHelper::renderGoogleAnalytics(); ?>
</head>
<body>

<?php render_topbar(); ?>

<?php
$emoji = trim($finalTest['emoji'] ?? ($finalTest['title_emoji'] ?? ''));
?>

<?php
// 结果页顶部广告
$resultTopAd = AdHelper::render('result_top', 'result');
if ($resultTopAd):
?>
<div class="ad-wrapper ad-wrapper--result-top">
    <?= $resultTopAd ?>
</div>
<?php endif; ?>

<div class="result-page">
    <?php if ($finalTest): ?>
        <header class="result-hero">
            <?php if ($emoji !== ''): ?>
                <div class="result-emoji"><?= htmlspecialchars($emoji) ?></div>
            <?php endif; ?>
            <div class="result-pill">测验结果</div>
            <p class="result-subtitle">
                来自测验：<?= htmlspecialchars($finalTest['title'] ?? '') ?>
            </p>
            <h1 class="result-title"><?= htmlspecialchars($finalResult['title'] ?? '测验结果') ?></h1>
        </header>
    <?php endif; ?>

    <?php if (!$finalResult): ?>
        <p>暂未匹配到结果，可能是后台还未配置完整。</p>
    <?php else: ?>
        <section class="result-body">
            <p class="result-highlight">
                这代表你在此次测验中，呈现出的核心倾向是：
                <strong><?= htmlspecialchars($finalResult['title']) ?></strong>
            </p>
            <div class="result-description">
                <?= HTMLPurifier::purifyWithBreaks($finalResult['description'] ?? '', true) ?>
            </div>
            <?php if (!empty($dimensionScores)): ?>
                <div class="dimension-distribution" style="margin-top:24px;">
                    <h3 class="dimension-title">你的维度分布</h3>
                    <div class="dimension-list">
                        <?php 
                        // 计算最大值用于百分比显示
                        $maxScore = max(array_values($dimensionScores));
                        $maxScore = $maxScore > 0 ? $maxScore : 1; // 避免除零
                        
                        // 维度名称映射（可选，用于显示中文名称）
                        $dimensionNames = [
                            'CAT' => ['name' => '猫系', 'emoji' => '🐱', 'color' => '#8b5cf6'],
                            'DOG' => ['name' => '狗系', 'emoji' => '🐶', 'color' => '#f59e0b'],
                            'FOX' => ['name' => '狐系', 'emoji' => '🦊', 'color' => '#ef4444'],
                            'DEER' => ['name' => '鹿系', 'emoji' => '🦌', 'color' => '#10b981'],
                            'OWL' => ['name' => '鸮系', 'emoji' => '🦉', 'color' => '#3b82f6'],
                            'P' => ['name' => '氛围型', 'emoji' => '✨', 'color' => '#ec4899'],
                            'C' => ['name' => '冷静型', 'emoji' => '🧊', 'color' => '#06b6d4'],
                            'E' => ['name' => '自信型', 'emoji' => '🔥', 'color' => '#f97316'],
                            'W' => ['name' => '有趣型', 'emoji' => '🎭', 'color' => '#a855f7'],
                        ];
                        
                        // 按分数排序（从高到低）
                        arsort($dimensionScores);
                        
                        foreach ($dimensionScores as $dimKey => $dimScore): 
                            $dimInfo = $dimensionNames[$dimKey] ?? ['name' => $dimKey, 'emoji' => '📊', 'color' => '#6b7280'];
                            $percentage = ($dimScore / $maxScore) * 100;
                        ?>
                            <div class="dimension-item">
                                <div class="dimension-header">
                                    <div class="dimension-label">
                                        <span class="dimension-emoji"><?= htmlspecialchars($dimInfo['emoji']) ?></span>
                                        <span class="dimension-name"><?= htmlspecialchars($dimInfo['name']) ?></span>
                                        <span class="dimension-key"><?= htmlspecialchars($dimKey) ?></span>
                                    </div>
                                    <div class="dimension-value"><?= htmlspecialchars((string)$dimScore) ?></div>
                                </div>
                                <div class="dimension-bar-container">
                                    <div class="dimension-bar" 
                                         style="width: <?= $percentage ?>%; background-color: <?= htmlspecialchars($dimInfo['color']) ?>;"
                                         data-dim="<?= htmlspecialchars($dimKey) ?>"
                                         data-score="<?= htmlspecialchars((string)$dimScore) ?>">
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            <?php if (!empty($finalResult['image_url'])): ?>
                <div style="margin-top:12px;">
                    <img src="<?= htmlspecialchars($finalResult['image_url']) ?>" alt="result image" style="max-width:100%;border-radius:12px;">
                </div>
            <?php endif; ?>
        </section>

        <?php
        // 结果页中间广告
        $resultMiddleAd = AdHelper::render('result_middle', 'result');
        if ($resultMiddleAd):
        ?>
        <div class="ad-wrapper ad-wrapper--result-middle">
            <?= $resultMiddleAd ?>
        </div>
        <?php endif; ?>
    <?php endif; ?>

    <div class="result-share-actions">
        <button type="button" class="btn-ghost-muted" id="copy-link-btn">复制结果链接</button>
        <button type="button" class="btn-ghost-muted" id="copy-text-btn">复制分享文案</button>
        <button type="button" class="btn-ghost-soft-red" id="save-poster-btn">保存结果海报</button>
    </div>

    <footer class="result-actions">
        <?php if ($finalTest): ?>
            <a href="/test.php?slug=<?= urlencode($finalTest['slug'] ?? '') ?>" class="btn-secondary">再测一次</a>
        <?php endif; ?>
        <a href="/index.php" class="btn-primary">返回全部测验</a>
    </footer>

    <?php
    // 结果页底部广告
    $resultBottomAd = AdHelper::render('result_bottom', 'result');
    if ($resultBottomAd):
    ?>
    <div class="ad-wrapper ad-wrapper--result-bottom">
        <?= $resultBottomAd ?>
    </div>
    <?php endif; ?>
</div>

<div id="result-poster" class="result-poster">
  <div class="result-poster-inner">
    <div class="poster-header">
    <div class="poster-brand">DoFun心理实验空间 · 测验结果</div>
      <div class="poster-test-title">来自测验：<?= htmlspecialchars($finalTest['title'] ?? '') ?></div>
    </div>

    <div class="poster-result-block">
      <div class="poster-result-label">你的结果</div>
      <div class="poster-result-title">
        <?php if (!empty($finalTest['emoji'])): ?>
          <span class="poster-result-emoji"><?= htmlspecialchars($finalTest['emoji']) ?></span>
        <?php endif; ?>
        <span class="poster-result-text"><?= htmlspecialchars($finalResult['title'] ?? '') ?></span>
      </div>
    </div>

    <div class="poster-description">
      <?= HTMLPurifier::purifyWithBreaks($finalResult['description'] ?? '', true) ?>
    </div>

    <div class="poster-footer">
      <div class="poster-footer-brand">dofun.fun · 在线趣味测试更好发现自己</div>
    </div>
  </div>
</div>

<div class="copy-toast" id="copy-toast">已复制到剪贴板</div>
<script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

<script>
(function () {
    var copyLinkBtn = document.getElementById('copy-link-btn');
    var copyTextBtn = document.getElementById('copy-text-btn');
    var toastEl = document.getElementById('copy-toast');
    if (!copyLinkBtn && !copyTextBtn) return;

var shareUrl = <?php echo json_encode($shareUrl); ?> || window.location.href;

    var toastTimer = null;
    function showToast(text) {
        if (!toastEl) return;
        toastEl.textContent = text;
        toastEl.classList.remove('copy-toast--hide');
        toastEl.classList.add('copy-toast--show');
        if (toastTimer) {
            clearTimeout(toastTimer);
        }
        toastTimer = setTimeout(function () {
            toastEl.classList.remove('copy-toast--show');
            toastEl.classList.add('copy-toast--hide');
            // 动画结束后移除 hide 类
            setTimeout(function() {
                toastEl.classList.remove('copy-toast--hide');
            }, 300);
        }, 5000); // 改为5秒自动隐藏
    }

    function copyText(text) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function () {
                showToast('已复制到剪贴板');
            }).catch(function () {
                window.prompt('复制失败，请手动复制：', text);
            });
        } else {
            window.prompt('请手动复制以下内容：', text);
        }
    }

    if (copyLinkBtn) {
        copyLinkBtn.addEventListener('click', function () {
            copyText(shareUrl);
            // 记录分享统计
            recordShare('copy_link');
        });
    }

    if (copyTextBtn) {
        var shareText = <?php
        $shareTemplate = '我在「DoFun心理实验空间」做了《' . ($finalTest['title'] ?? '') . '》测验，结果是：' . ($finalResult['title'] ?? '') . '。你也可以来测测看：';
            echo json_encode($shareTemplate);
        ?> + shareUrl;
        copyTextBtn.addEventListener('click', function () {
            copyText(shareText);
            // 记录分享统计
            recordShare('copy_text');
        });
    }
    
    // 记录分享统计
    function recordShare(platform) {
        var shareToken = <?php echo json_encode($shareToken ?? ''); ?>;
        if (!shareToken) return;
        
        // 使用 fetch API 发送统计请求
        fetch('/api/share_stats.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                share_token: shareToken,
                platform: platform
            })
        }).catch(function(err) {
            console.log('Share stats error:', err);
        });
    }
})();

(function () {
    var btn = document.getElementById("save-poster-btn");
    var poster = document.getElementById("result-poster");
    if (!btn || !poster || typeof html2canvas === 'undefined') return;

    btn.addEventListener("click", function () {
      poster.style.display = "block";

      html2canvas(poster, {
        scale: 2,
        useCORS: true,
        logging: false
      }).then(function (canvas) {
        poster.style.display = "none";

        var dataURL = canvas.toDataURL("image/png");
        var link = document.createElement("a");
        link.href = dataURL;
        link.download = "测验结果.png";
        link.click();
      }).catch(function () {
        poster.style.display = "none";
        alert("生成海报时出错，请稍后再试");
      });
    });
  })();
</script>
</body>
</html>
