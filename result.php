<?php
require_once __DIR__ . '/seo_helper.php';
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/html_purifier.php';

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
</head>
<body>

<?php
$emoji = trim($finalTest['emoji'] ?? ($finalTest['title_emoji'] ?? ''));
?>

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
                <div class="result-description" style="margin-top:12px;">
                    <strong>你的维度分布：</strong>
                    <?php foreach ($dimensionScores as $dimKey => $dimScore): ?>
                        <span style="display:inline-block;margin-right:8px;">
                            <?= htmlspecialchars($dimKey) ?> <?= htmlspecialchars((string)$dimScore) ?>
                        </span>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($finalResult['image_url'])): ?>
                <div style="margin-top:12px;">
                    <img src="<?= htmlspecialchars($finalResult['image_url']) ?>" alt="result image" style="max-width:100%;border-radius:12px;">
                </div>
            <?php endif; ?>
        </section>
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
        toastEl.classList.add('copy-toast--show');
        if (toastTimer) {
            clearTimeout(toastTimer);
        }
        toastTimer = setTimeout(function () {
            toastEl.classList.remove('copy-toast--show');
        }, 3000);
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
        });
    }

    if (copyTextBtn) {
        var shareText = <?php
        $shareTemplate = '我在「DoFun心理实验空间」做了《' . ($finalTest['title'] ?? '') . '》测验，结果是：' . ($finalResult['title'] ?? '') . '。你也可以来测测看：';
            echo json_encode($shareTemplate);
        ?> + shareUrl;
        copyTextBtn.addEventListener('click', function () {
            copyText(shareText);
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
