<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/SEOContentOptimizer.php';

$pageTitle = 'SEO 内容优化';
$pageSubtitle = '批量检查测验 SEO 分数并提供优化建议';
$activeMenu = 'system';

// 获取所有测验
$tests = $pdo->query("
    SELECT id, title, description, slug, cover_image, status, created_at
    FROM tests
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 分析每个测验的 SEO 分数
$seoReports = [];
$totalScore = 0;
$scoreCount = 0;

foreach ($tests as $test) {
    $report = SEOContentOptimizer::generateReport($test);
    $seoReports[] = [
        'test' => $test,
        'report' => $report,
    ];
    $totalScore += $report['score'];
    $scoreCount++;
}

// 计算平均分数
$averageScore = $scoreCount > 0 ? round($totalScore / $scoreCount, 1) : 0;

// 按分数排序
usort($seoReports, function($a, $b) {
    return $a['report']['score'] - $b['report']['score'];
});

// 统计分数分布
$scoreDistribution = [
    'excellent' => 0, // 90-100
    'good' => 0,      // 70-89
    'fair' => 0,      // 50-69
    'poor' => 0,      // 0-49
];

foreach ($seoReports as $item) {
    $score = $item['report']['score'];
    if ($score >= 90) {
        $scoreDistribution['excellent']++;
    } elseif ($score >= 70) {
        $scoreDistribution['good']++;
    } elseif ($score >= 50) {
        $scoreDistribution['fair']++;
    } else {
        $scoreDistribution['poor']++;
    }
}

// 处理单个测验优化
$testId = $_GET['test_id'] ?? null;
$testReport = null;
if ($testId) {
    $test = $pdo->prepare("SELECT * FROM tests WHERE id = ?");
    $test->execute([$testId]);
    $testData = $test->fetch(PDO::FETCH_ASSOC);
    if ($testData) {
        $testReport = SEOContentOptimizer::generateReport($testData);
        // 获取内部链接建议
        $allTests = $pdo->query("SELECT id, title, slug FROM tests WHERE id != ? AND status = 'published' LIMIT 50")
            ->fetchAll(PDO::FETCH_ASSOC);
        $linkSuggestions = SEOContentOptimizer::suggestInternalLinks($testData, $allTests, 5);
    }
}

ob_start();
?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <span class="admin-table__muted">批量检查测验 SEO 优化情况，提升搜索引擎排名。</span>
    </div>
</div>

<?php if ($testId && $testReport): ?>
    <!-- 单个测验详细报告 -->
    <div class="admin-card" style="margin-bottom: 16px;">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
            <h2 class="admin-page-title" style="font-size: 15px; margin: 0;">
                SEO 优化报告：<?= htmlspecialchars($testData['title']) ?>
            </h2>
            <a href="seo_optimizer.php" class="btn btn-xs">返回列表</a>
        </div>

        <!-- SEO 分数 -->
        <div style="text-align: center; padding: 24px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 8px; margin-bottom: 24px;">
            <div style="font-size: 48px; font-weight: bold; color: #fff; margin-bottom: 8px;">
                <?= $testReport['score'] ?>
            </div>
            <div style="font-size: 14px; color: rgba(255,255,255,0.9);">
                SEO 分数
                <?php
                if ($testReport['score'] >= 90) {
                    echo '<span style="margin-left: 8px;">⭐ 优秀</span>';
                } elseif ($testReport['score'] >= 70) {
                    echo '<span style="margin-left: 8px;">✓ 良好</span>';
                } elseif ($testReport['score'] >= 50) {
                    echo '<span style="margin-left: 8px;">⚠ 一般</span>';
                } else {
                    echo '<span style="margin-left: 8px;">✗ 需改进</span>';
                }
                ?>
            </div>
        </div>

        <!-- 标题优化 -->
        <div style="margin-bottom: 24px;">
            <h3 style="font-size: 14px; font-weight: 600; color: #e5e7eb; margin-bottom: 12px;">标题优化</h3>
            <div style="background: #1f2937; padding: 16px; border-radius: 6px; margin-bottom: 12px;">
                <div style="font-size: 12px; color: #9ca3af; margin-bottom: 8px;">原始标题</div>
                <div style="color: #e5e7eb; margin-bottom: 12px;"><?= htmlspecialchars($testReport['title']['original']) ?></div>
                <div style="font-size: 12px; color: #9ca3af; margin-bottom: 8px;">优化后标题</div>
                <div style="color: #60a5fa;"><?= htmlspecialchars($testReport['title']['optimized']) ?></div>
                <div style="font-size: 12px; color: #9ca3af; margin-top: 8px;">
                    长度: <?= $testReport['title']['length'] ?> 字符
                    <?php if ($testReport['title']['length'] >= 30 && $testReport['title']['length'] <= 60): ?>
                        <span style="color: #34d399; margin-left: 8px;">✓ 推荐范围</span>
                    <?php else: ?>
                        <span style="color: #f59e0b; margin-left: 8px;">⚠ 不在推荐范围（30-60字符）</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($testReport['title']['suggestions'])): ?>
                <div style="background: #374151; padding: 12px; border-radius: 6px; border-left: 3px solid #f59e0b;">
                    <div style="font-size: 12px; color: #f59e0b; margin-bottom: 4px;">优化建议</div>
                    <?php foreach ($testReport['title']['suggestions'] as $suggestion): ?>
                        <div style="font-size: 13px; color: #d1d5db; margin-bottom: 4px;">• <?= htmlspecialchars($suggestion) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 描述优化 -->
        <div style="margin-bottom: 24px;">
            <h3 style="font-size: 14px; font-weight: 600; color: #e5e7eb; margin-bottom: 12px;">描述优化</h3>
            <div style="background: #1f2937; padding: 16px; border-radius: 6px; margin-bottom: 12px;">
                <div style="font-size: 12px; color: #9ca3af; margin-bottom: 8px;">原始描述</div>
                <div style="color: #e5e7eb; margin-bottom: 12px; line-height: 1.6;">
                    <?= htmlspecialchars(mb_substr($testReport['description']['original'], 0, 200)) ?>
                    <?= mb_strlen($testReport['description']['original']) > 200 ? '...' : '' ?>
                </div>
                <div style="font-size: 12px; color: #9ca3af; margin-bottom: 8px;">优化后描述</div>
                <div style="color: #60a5fa; line-height: 1.6;"><?= htmlspecialchars($testReport['description']['optimized']) ?></div>
                <div style="font-size: 12px; color: #9ca3af; margin-top: 8px;">
                    长度: <?= $testReport['description']['length'] ?> 字符
                    <?php if ($testReport['description']['length'] >= 120 && $testReport['description']['length'] <= 160): ?>
                        <span style="color: #34d399; margin-left: 8px;">✓ 推荐范围</span>
                    <?php else: ?>
                        <span style="color: #f59e0b; margin-left: 8px;">⚠ 不在推荐范围（120-160字符）</span>
                    <?php endif; ?>
                </div>
            </div>
            <?php if (!empty($testReport['description']['suggestions'])): ?>
                <div style="background: #374151; padding: 12px; border-radius: 6px; border-left: 3px solid #f59e0b;">
                    <div style="font-size: 12px; color: #f59e0b; margin-bottom: 4px;">优化建议</div>
                    <?php foreach ($testReport['description']['suggestions'] as $suggestion): ?>
                        <div style="font-size: 13px; color: #d1d5db; margin-bottom: 4px;">• <?= htmlspecialchars($suggestion) ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- 问题列表 -->
        <?php if (!empty($testReport['issues'])): ?>
            <div style="margin-bottom: 24px;">
                <h3 style="font-size: 14px; font-weight: 600; color: #e5e7eb; margin-bottom: 12px;">发现的问题</h3>
                <div style="background: #7f1d1d; padding: 16px; border-radius: 6px; border-left: 3px solid #ef4444;">
                    <?php foreach ($testReport['issues'] as $issue): ?>
                        <div style="font-size: 13px; color: #fca5a5; margin-bottom: 8px;">
                            <span style="color: #ef4444; margin-right: 8px;">✗</span>
                            <?= htmlspecialchars($issue) ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 内部链接建议 -->
        <?php if (!empty($linkSuggestions)): ?>
            <div style="margin-bottom: 24px;">
                <h3 style="font-size: 14px; font-weight: 600; color: #e5e7eb; margin-bottom: 12px;">内部链接建议</h3>
                <div style="background: #1f2937; padding: 16px; border-radius: 6px;">
                    <div style="font-size: 12px; color: #9ca3af; margin-bottom: 12px;">
                        以下测验与当前内容相关，建议添加内部链接：
                    </div>
                    <?php foreach ($linkSuggestions as $suggestion): ?>
                        <div style="padding: 12px; background: #111827; border-radius: 4px; margin-bottom: 8px;">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <div>
                                    <div style="color: #e5e7eb; font-weight: 500; margin-bottom: 4px;">
                                        <?= htmlspecialchars($suggestion['test']['title']) ?>
                                    </div>
                                    <div style="font-size: 12px; color: #9ca3af;">
                                        相关性: <?= round($suggestion['relevance'], 1) ?>%
                                    </div>
                                </div>
                                <a href="../test.php?slug=<?= urlencode($suggestion['test']['slug']) ?>" 
                                   target="_blank"
                                   class="btn btn-xs"
                                   style="background: #3b82f6; color: #fff; border: none;">
                                    查看
                                </a>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>

        <!-- 操作按钮 -->
        <div style="text-align: center; padding-top: 16px; border-top: 1px solid #374151;">
            <a href="edit_test.php?id=<?= $testId ?>" class="btn btn-primary">编辑测验</a>
        </div>
    </div>
<?php endif; ?>

<!-- SEO 统计概览 -->
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">SEO 统计概览</h2>
    <table class="admin-table admin-table--kpi">
        <tbody>
        <tr>
            <td>
                <div class="admin-kpi-number"><?= $averageScore ?></div>
                <div class="admin-kpi-label">平均 SEO 分数</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: #34d399;"><?= $scoreDistribution['excellent'] ?></div>
                <div class="admin-kpi-label">优秀 (90-100)</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: #60a5fa;"><?= $scoreDistribution['good'] ?></div>
                <div class="admin-kpi-label">良好 (70-89)</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: #f59e0b;"><?= $scoreDistribution['fair'] ?></div>
                <div class="admin-kpi-label">一般 (50-69)</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: #ef4444;"><?= $scoreDistribution['poor'] ?></div>
                <div class="admin-kpi-label">需改进 (0-49)</div>
            </td>
        </tr>
        </tbody>
    </table>
</div>

<!-- 测验 SEO 列表 -->
<div class="admin-card">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">测验 SEO 评分列表</h2>
    
    <?php if (empty($seoReports)): ?>
        <div style="padding: 40px; text-align: center; color: #9ca3af;">
            <div style="font-size: 48px; margin-bottom: 16px;">📝</div>
            <div>暂无测验数据</div>
        </div>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th style="width: 40%;">测验标题</th>
                <th style="width: 100px;">SEO 分数</th>
                <th style="width: 150px;">标题长度</th>
                <th style="width: 150px;">描述长度</th>
                <th style="width: 100px;">状态</th>
                <th style="width: 120px;">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($seoReports as $item): 
                $test = $item['test'];
                $report = $item['report'];
                $score = $report['score'];
                $scoreColor = $score >= 90 ? '#34d399' : ($score >= 70 ? '#60a5fa' : ($score >= 50 ? '#f59e0b' : '#ef4444'));
            ?>
                <tr>
                    <td>
                        <div style="font-weight: 500; color: #e5e7eb; margin-bottom: 4px;">
                            <?= htmlspecialchars($test['title']) ?>
                        </div>
                        <?php if (!empty($report['issues'])): ?>
                            <div style="font-size: 11px; color: #f59e0b;">
                                <?= count($report['issues']) ?> 个问题
                            </div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-size: 18px; font-weight: bold; color: <?= $scoreColor ?>;">
                            <?= $score ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-size: 13px; color: #e5e7eb;">
                            <?= $report['title']['length'] ?> 字符
                        </div>
                        <?php if ($report['title']['length'] < 30 || $report['title']['length'] > 60): ?>
                            <div style="font-size: 11px; color: #f59e0b;">⚠ 不在推荐范围</div>
                        <?php else: ?>
                            <div style="font-size: 11px; color: #34d399;">✓ 正常</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <div style="font-size: 13px; color: #e5e7eb;">
                            <?= $report['description']['length'] ?> 字符
                        </div>
                        <?php if ($report['description']['length'] < 120 || $report['description']['length'] > 160): ?>
                            <div style="font-size: 11px; color: #f59e0b;">⚠ 不在推荐范围</div>
                        <?php else: ?>
                            <div style="font-size: 11px; color: #34d399;">✓ 正常</div>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="color: <?= $test['status'] === 'published' ? '#34d399' : '#9ca3af' ?>;">
                            <?= $test['status'] === 'published' ? '已发布' : '草稿' ?>
                        </span>
                    </td>
                    <td>
                        <a href="?test_id=<?= $test['id'] ?>" class="btn btn-xs" style="background: #3b82f6; color: #fff; border: none;">
                            查看详情
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

