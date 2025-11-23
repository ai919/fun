<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/CacheHelper.php';

$pageTitle = 'URL 重定向管理';
$pageSubtitle = '查看和管理 301 永久重定向规则';
$activeMenu = 'system';

// 获取所有测验的 ID 和 slug 映射
$tests = $pdo->query("
    SELECT id, title, slug, status, created_at
    FROM tests
    ORDER BY created_at DESC
")->fetchAll(PDO::FETCH_ASSOC);

// 统计信息
$totalTests = count($tests);
$publishedTests = count(array_filter($tests, fn($t) => $t['status'] === 'published'));
$testsWithSlug = count(array_filter($tests, fn($t) => !empty($t['slug'])));

// 检查缓存状态
$redirectCacheStats = [];
foreach ($tests as $test) {
    if (!empty($test['slug'])) {
        $cacheKey = 'test_id_slug_' . $test['id'];
        $cached = CacheHelper::get($cacheKey, 0); // 不刷新，只检查是否存在
        $redirectCacheStats[$test['id']] = $cached !== null;
    }
}

$cachedCount = count(array_filter($redirectCacheStats));

ob_start();
?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <span class="admin-table__muted">管理 URL 重定向规则，确保旧链接正确跳转到新 URL。</span>
    </div>
</div>

<!-- 重定向统计 -->
<div class="admin-card" style="margin-bottom: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">重定向统计</h2>
    <table class="admin-table admin-table--kpi">
        <tbody>
        <tr>
            <td>
                <div class="admin-kpi-number"><?= $totalTests ?></div>
                <div class="admin-kpi-label">测验总数</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: #34d399;"><?= $testsWithSlug ?></div>
                <div class="admin-kpi-label">有 Slug 的测验</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: #60a5fa;"><?= $publishedTests ?></div>
                <div class="admin-kpi-label">已发布</div>
            </td>
            <td>
                <div class="admin-kpi-number" style="color: <?= $cachedCount > 0 ? '#34d399' : '#9ca3af' ?>;">
                    <?= $cachedCount ?>
                </div>
                <div class="admin-kpi-label">已缓存映射</div>
            </td>
        </tr>
        </tbody>
    </table>
</div>

<!-- 重定向规则说明 -->
<div class="admin-card" style="margin-bottom: 16px; background: #1f2937; border-left: 4px solid #3b82f6;">
    <h3 style="font-size: 14px; font-weight: 600; color: #e5e7eb; margin-bottom: 8px;">重定向规则</h3>
    <div style="font-size: 13px; color: #d1d5db; line-height: 1.6;">
        <p style="margin-bottom: 8px;">
            <strong>旧 URL 格式：</strong>
        </p>
        <ul style="margin-left: 20px; margin-bottom: 12px;">
            <li><code style="background: #111827; padding: 2px 6px; border-radius: 3px;">test.php?id=123</code> → <code style="background: #111827; padding: 2px 6px; border-radius: 3px;">/slug</code></li>
            <li><code style="background: #111827; padding: 2px 6px; border-radius: 3px;">quiz.php?id=123</code> → <code style="background: #111827; padding: 2px 6px; border-radius: 3px;">/slug</code></li>
        </ul>
        <p style="margin-bottom: 8px;">
            <strong>重定向类型：</strong> 301 永久重定向（有利于 SEO）
        </p>
        <p style="margin-bottom: 0;">
            <strong>缓存策略：</strong> Slug 映射缓存 1 小时，减少数据库查询
        </p>
    </div>
</div>

<!-- 测验重定向列表 -->
<div class="admin-card">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">测验 URL 映射</h2>
    
    <?php if (empty($tests)): ?>
        <div style="padding: 40px; text-align: center; color: #9ca3af;">
            <div style="font-size: 48px; margin-bottom: 16px;">🔗</div>
            <div>暂无测验数据</div>
        </div>
    <?php else: ?>
        <table class="admin-table">
            <thead>
            <tr>
                <th style="width: 60px;">ID</th>
                <th style="width: 30%;">测验标题</th>
                <th style="width: 20%;">旧 URL</th>
                <th style="width: 20%;">新 URL (Slug)</th>
                <th style="width: 100px;">状态</th>
                <th style="width: 100px;">缓存</th>
                <th style="width: 120px;">操作</th>
            </tr>
            </thead>
            <tbody>
            <?php foreach ($tests as $test): 
                $oldUrl1 = 'test.php?id=' . $test['id'];
                $oldUrl2 = 'quiz.php?id=' . $test['id'];
                $newUrl = !empty($test['slug']) ? '/' . $test['slug'] : null;
                $isCached = $redirectCacheStats[$test['id']] ?? false;
            ?>
                <tr>
                    <td>
                        <code class="code-badge"><?= $test['id'] ?></code>
                    </td>
                    <td>
                        <div style="font-weight: 500; color: #e5e7eb; margin-bottom: 4px;">
                            <?= htmlspecialchars($test['title']) ?>
                        </div>
                    </td>
                    <td>
                        <div style="font-size: 12px; color: #9ca3af; margin-bottom: 4px;">
                            <code style="background: #111827; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                <?= htmlspecialchars($oldUrl1) ?>
                            </code>
                        </div>
                        <div style="font-size: 12px; color: #9ca3af;">
                            <code style="background: #111827; padding: 2px 6px; border-radius: 3px; font-size: 11px;">
                                <?= htmlspecialchars($oldUrl2) ?>
                            </code>
                        </div>
                    </td>
                    <td>
                        <?php if ($newUrl): ?>
                            <code class="code-badge" style="color: #34d399; font-size: 11px;">
                                <?= htmlspecialchars($newUrl) ?>
                            </code>
                        <?php else: ?>
                            <span style="color: #f59e0b; font-size: 12px;">⚠ 无 Slug</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span style="color: <?= $test['status'] === 'published' ? '#34d399' : '#9ca3af' ?>;">
                            <?= $test['status'] === 'published' ? '已发布' : '草稿' ?>
                        </span>
                    </td>
                    <td>
                        <?php if ($newUrl): ?>
                            <?php if ($isCached): ?>
                                <span style="color: #34d399; font-size: 12px;">✓ 已缓存</span>
                            <?php else: ?>
                                <span style="color: #9ca3af; font-size: 12px;">未缓存</span>
                            <?php endif; ?>
                        <?php else: ?>
                            <span style="color: #9ca3af; font-size: 12px;">-</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($newUrl): ?>
                            <a href="<?= htmlspecialchars($newUrl) ?>" 
                               target="_blank"
                               class="btn btn-xs" 
                               style="background: #3b82f6; color: #fff; border: none;">
                                测试
                            </a>
                        <?php else: ?>
                            <a href="edit_test.php?id=<?= $test['id'] ?>" 
                               class="btn btn-xs" 
                               style="background: #f59e0b; color: #fff; border: none;">
                                设置 Slug
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>

<!-- 测试重定向 -->
<div class="admin-card" style="margin-top: 16px;">
    <h2 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">测试重定向</h2>
    <div style="background: #1f2937; padding: 16px; border-radius: 6px;">
        <div style="font-size: 13px; color: #d1d5db; margin-bottom: 12px;">
            输入测验 ID 测试重定向是否正常工作：
        </div>
        <form method="GET" action="../" target="_blank" style="display: flex; gap: 8px;">
            <input type="text" 
                   name="id" 
                   placeholder="输入测验 ID"
                   style="flex: 1; background: #111827; border: 1px solid #374151; color: #e5e7eb; padding: 8px 12px; border-radius: 4px; font-size: 13px;"
                   required>
            <select name="type" 
                    style="background: #111827; border: 1px solid #374151; color: #e5e7eb; padding: 8px 12px; border-radius: 4px; font-size: 13px;">
                <option value="test.php">test.php</option>
                <option value="quiz.php">quiz.php</option>
            </select>
            <button type="submit" 
                    class="btn btn-primary"
                    style="background: #3b82f6; color: #fff; border: none; padding: 8px 16px; border-radius: 4px; cursor: pointer;">
                测试重定向
            </button>
        </form>
        <div style="font-size: 11px; color: #6b7280; margin-top: 8px;">
            💡 提示：测试将在新标签页打开，检查是否成功重定向到新 URL。实际测试请使用：<code style="background: #111827; padding: 2px 6px; border-radius: 3px;">test.php?id=123</code>
        </div>
        <div style="font-size: 11px; color: #6b7280; margin-top: 8px;">
            💡 提示：测试将在新标签页打开，检查是否成功重定向到新 URL
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

