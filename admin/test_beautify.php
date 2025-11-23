<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/csrf.php';

$pageTitle = '数据美化设置';
$pageSubtitle = '为每个测验单独设置美化后的播放次数（仅用于前台显示）';
$activeMenu = 'test_beautify';

// 处理保存
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!CSRF::validateToken()) {
        http_response_code(403);
        die('CSRF token 验证失败，请刷新页面后重试');
    }

    $action = $_POST['action'] ?? '';
    
    if ($action === 'save') {
        $testId = (int)($_POST['test_id'] ?? 0);
        $beautifiedValue = trim($_POST['play_count_beautified'] ?? '');
        
        if ($testId <= 0) {
            $message = '无效的测验ID';
            $messageType = 'error';
        } else {
            // 验证输入：必须是正整数或空字符串
            if ($beautifiedValue !== '' && (!is_numeric($beautifiedValue) || (int)$beautifiedValue <= 0)) {
                $message = '美化数据必须是正整数，或留空以使用真实数据。';
                $messageType = 'error';
            } else {
                // 更新测验的美化数据
                $beautifiedInt = $beautifiedValue === '' ? null : (int)$beautifiedValue;
                $stmt = $pdo->prepare("UPDATE tests SET play_count_beautified = ? WHERE id = ?");
                $success = $stmt->execute([$beautifiedInt, $testId]);
                
                if ($success) {
                    $message = '设置已保存成功！';
                    $messageType = 'success';
                } else {
                    $message = '保存失败，请重试。';
                    $messageType = 'error';
                }
            }
        }
    } elseif ($action === 'clear') {
        $testId = (int)($_POST['test_id'] ?? 0);
        if ($testId > 0) {
            $stmt = $pdo->prepare("UPDATE tests SET play_count_beautified = NULL WHERE id = ?");
            $stmt->execute([$testId]);
            $message = '美化数据已清除！';
            $messageType = 'success';
        }
    }
}

// 获取所有测验及其美化数据
$stmt = $pdo->query("
    SELECT 
        t.id,
        t.slug,
        t.title,
        t.subtitle,
        t.play_count_beautified,
        (SELECT COUNT(*) FROM test_runs r WHERE r.test_id = t.id) AS real_play_count
    FROM tests t
    ORDER BY t.sort_order DESC, t.id DESC
");
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

ob_start();
?>

<?php if ($message): ?>
    <div class="admin-message admin-message--<?= $messageType ?>" style="margin-bottom: 16px;">
        <?= htmlspecialchars($message) ?>
    </div>
<?php endif; ?>

<div class="admin-card">
    <div style="margin-bottom: 20px; padding: 12px; background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); border-radius: 6px;">
        <div style="font-size: 12px; color: #93c5fd; line-height: 1.6;">
            <p style="margin: 0 0 8px 0; font-weight: 600;">💡 使用说明</p>
            <ul style="margin: 0; padding-left: 20px;">
                <li style="margin-bottom: 4px;">可以为每个测验单独设置美化后的播放次数</li>
                <li style="margin-bottom: 4px;">美化数据仅用于前台显示，不会影响数据库中的真实数据</li>
                <li style="margin-bottom: 4px;">美化数据作为基础值，真实播放次数会叠加在上面（例如：美化为100，真实数据为1，则显示101）</li>
                <li style="margin-bottom: 4px;">留空或清除表示使用真实数据（不叠加）</li>
            </ul>
        </div>
    </div>

    <table class="admin-table">
        <thead>
        <tr>
            <th>ID</th>
            <th>测验标题</th>
            <th>真实播放次数</th>
            <th>美化数据</th>
            <th>前台显示</th>
            <th>操作</th>
        </tr>
        </thead>
        <tbody>
        <?php foreach ($tests as $test): ?>
            <?php
            $beautifiedValue = $test['play_count_beautified'];
            $realCount = (int)$test['real_play_count'];
            // 美化数据作为基础值，真实数据叠加在上面
            $displayCount = $beautifiedValue !== null && (int)$beautifiedValue > 0 
                ? (int)$beautifiedValue + $realCount
                : $realCount;
            ?>
            <tr>
                <td><?= (int)$test['id'] ?></td>
                <td>
                    <div class="admin-table__title admin-table__title--lg">
                        <?= htmlspecialchars($test['title']) ?>
                    </div>
                    <?php if (!empty($test['subtitle'])): ?>
                        <div class="admin-table__subtitle">
                            <?= htmlspecialchars($test['subtitle']) ?>
                        </div>
                    <?php endif; ?>
                    <div style="margin-top: 4px;">
                        <code class="code-badge code-badge--muted" style="font-size: 11px;">
                            <?= htmlspecialchars($test['slug']) ?>
                        </code>
                    </div>
                </td>
                <td>
                    <span style="font-weight: 600; color: #e5e7eb;">
                        <?= number_format($realCount) ?>
                    </span>
                </td>
                <td>
                    <?php if ($beautifiedValue !== null): ?>
                        <span style="font-weight: 600; color: #60a5fa;">
                            <?= number_format((int)$beautifiedValue) ?>
                        </span>
                    <?php else: ?>
                        <span style="color: #9ca3af; font-size: 12px;">未设置</span>
                    <?php endif; ?>
                </td>
                <td>
                    <span style="font-weight: 600; color: #34d399;">
                        <?= number_format($displayCount) ?>
                    </span>
                </td>
                <td class="admin-table__actions">
                    <button 
                        type="button" 
                        class="btn btn-xs btn-primary"
                        onclick="openEditModal(<?= (int)$test['id'] ?>, '<?= htmlspecialchars(addslashes($test['title'])) ?>', <?= $realCount ?>, <?= $beautifiedValue !== null ? (int)$beautifiedValue : 'null' ?>)">
                        设置
                    </button>
                    <?php if ($beautifiedValue !== null): ?>
                        <form method="POST" action="" style="display: inline-block; margin-left: 4px;" onsubmit="return confirm('确定要清除此测验的美化数据吗？');">
                            <?= CSRF::getTokenField() ?>
                            <input type="hidden" name="action" value="clear">
                            <input type="hidden" name="test_id" value="<?= (int)$test['id'] ?>">
                            <button type="submit" class="btn btn-xs btn-ghost">清除</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- 编辑模态框 -->
<div id="editModal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0, 0, 0, 0.7); z-index: 1000; align-items: center; justify-content: center;">
    <div style="background: var(--admin-bg-secondary); border-radius: 12px; padding: 24px; max-width: 500px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h3 style="margin: 0 0 20px 0; font-size: 18px; font-weight: 600; color: var(--admin-text-primary);" id="modalTitle">设置美化数据</h3>
        
        <form method="POST" action="" id="editForm">
            <?= CSRF::getTokenField() ?>
            <input type="hidden" name="action" value="save">
            <input type="hidden" name="test_id" id="modalTestId">
            
            <div style="margin-bottom: 16px;">
                <label style="display: block; margin-bottom: 8px; font-size: 13px; font-weight: 600; color: var(--admin-text-primary);">
                    美化数据
                </label>
                <input 
                    type="number" 
                    name="play_count_beautified" 
                    id="modalBeautifiedValue"
                    min="1"
                    step="1"
                    placeholder="留空则使用真实数据"
                    style="width: 100%; padding: 8px 12px; background: var(--admin-bg-primary); border: 1px solid var(--admin-border-color); border-radius: 6px; color: var(--admin-text-primary); font-size: 14px;"
                >
                <div style="margin-top: 8px; font-size: 12px; color: var(--admin-text-secondary);">
                    <p style="margin: 0 0 4px 0;">真实播放次数：<strong id="modalRealCount">0</strong></p>
                    <p style="margin: 0 0 4px 0;">美化数据作为基础值，真实数据会叠加在上面</p>
                    <p style="margin: 0;">留空表示使用真实数据（不叠加）</p>
                </div>
            </div>
            
            <div style="display: flex; gap: 8px; justify-content: flex-end;">
                <button 
                    type="button" 
                    class="btn btn-ghost"
                    onclick="closeEditModal()">
                    取消
                </button>
                <button type="submit" class="btn btn-primary">保存</button>
            </div>
        </form>
    </div>
</div>

<script>
function openEditModal(testId, testTitle, realCount, beautifiedValue) {
    document.getElementById('modalTitle').textContent = '设置美化数据：' + testTitle;
    document.getElementById('modalTestId').value = testId;
    document.getElementById('modalRealCount').textContent = realCount.toLocaleString();
    document.getElementById('modalBeautifiedValue').value = beautifiedValue !== null ? beautifiedValue : '';
    document.getElementById('editModal').style.display = 'flex';
}

function closeEditModal() {
    document.getElementById('editModal').style.display = 'none';
}

// 点击模态框背景关闭
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});
</script>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

