<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/motivational_quotes.php';

$pageTitle = '心理激励名言管理';
$pageSubtitle = '管理测验页面顶部显示的心理激励名言';
$activeMenu = 'quotes';

$message = '';
$messageType = '';

// 处理表单提交
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'add':
                if (!empty($_POST['quote_text'])) {
                    $sortOrder = (int)($_POST['sort_order'] ?? 0);
                    $isActive = isset($_POST['is_active']) ? 1 : 0;
                    $id = MotivationalQuotes::addQuote($_POST['quote_text'], $isActive === 1, $sortOrder);
                    if ($id) {
                        $message = '名言添加成功！';
                        $messageType = 'success';
                    } else {
                        $message = '名言添加失败，请重试。';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'update':
                if (isset($_POST['id']) && !empty($_POST['quote_text'])) {
                    $id = (int)$_POST['id'];
                    $sortOrder = (int)($_POST['sort_order'] ?? 0);
                    $isActive = isset($_POST['is_active']) ? 1 : 0;
                    if (MotivationalQuotes::updateQuote($id, $_POST['quote_text'], $isActive === 1, $sortOrder)) {
                        $message = '名言更新成功！';
                        $messageType = 'success';
                    } else {
                        $message = '名言更新失败，请重试。';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'delete':
                if (isset($_POST['id'])) {
                    $id = (int)$_POST['id'];
                    if (MotivationalQuotes::deleteQuote($id)) {
                        $message = '名言删除成功！';
                        $messageType = 'success';
                    } else {
                        $message = '名言删除失败，请重试。';
                        $messageType = 'error';
                    }
                }
                break;
                
            case 'toggle':
                if (isset($_POST['id'])) {
                    $id = (int)$_POST['id'];
                    if (MotivationalQuotes::toggleActive($id)) {
                        $message = '状态更新成功！';
                        $messageType = 'success';
                    } else {
                        $message = '状态更新失败，请重试。';
                        $messageType = 'error';
                    }
                }
                break;
        }
    }
}

// 获取所有名言
$quotes = MotivationalQuotes::getAllQuotes();

ob_start();
?>

<?php if ($message): ?>
<div class="admin-alert admin-alert--<?= $messageType === 'success' ? 'success' : 'error' ?>" style="margin-bottom: 16px;">
    <?= htmlspecialchars($message) ?>
</div>
<?php endif; ?>

<div class="admin-toolbar">
    <div class="admin-toolbar__left">
        <span class="admin-table__muted">共 <?= count($quotes) ?> 条名言，其中 <?= count(array_filter($quotes, fn($q) => $q['is_active'] == 1)) ?> 条已启用</span>
    </div>
    <div class="admin-toolbar__right">
        <button type="button" class="btn btn-primary" onclick="document.getElementById('add-quote-form').style.display = 'block'; this.style.display = 'none';">
            + 添加名言
        </button>
    </div>
</div>

<!-- 添加名言表单 -->
<div id="add-quote-form" class="admin-card" style="display: none; margin-bottom: 16px;">
    <h3 class="admin-page-title" style="font-size: 15px; margin-bottom: 12px;">添加新名言</h3>
    <form method="POST" action="">
        <input type="hidden" name="action" value="add">
        <div style="margin-bottom: 12px;">
            <label style="display: block; margin-bottom: 6px; font-size: 13px; color: #9ca3af;">名言内容 *</label>
            <textarea name="quote_text" required style="width: 100%; min-height: 80px; padding: 8px; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; background: #020617; color: #e5e7eb; font-size: 14px; font-family: inherit;" placeholder="请输入心理激励名言..."></textarea>
        </div>
        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
            <div>
                <label style="display: block; margin-bottom: 6px; font-size: 13px; color: #9ca3af;">排序顺序</label>
                <input type="number" name="sort_order" value="0" style="width: 100%; padding: 8px; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; background: #020617; color: #e5e7eb; font-size: 14px;">
            </div>
            <div style="display: flex; align-items: flex-end;">
                <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #9ca3af;">
                    <input type="checkbox" name="is_active" value="1" checked style="width: 16px; height: 16px;">
                    <span>启用</span>
                </label>
            </div>
        </div>
        <div style="display: flex; gap: 8px;">
            <button type="submit" class="btn btn-primary">保存</button>
            <button type="button" class="btn" onclick="document.getElementById('add-quote-form').style.display = 'none'; document.querySelector('.admin-toolbar__right button').style.display = 'block'; this.closest('form').reset();">取消</button>
        </div>
    </form>
</div>

<!-- 名言列表 -->
<div class="admin-card">
    <table class="admin-table">
        <thead>
            <tr>
                <th style="width: 5%;">ID</th>
                <th style="width: 60%;">名言内容</th>
                <th style="width: 10%;">排序</th>
                <th style="width: 10%;">状态</th>
                <th style="width: 15%;">操作</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($quotes)): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px; color: #9ca3af;">
                        暂无名言，点击上方"添加名言"按钮开始添加
                    </td>
                </tr>
            <?php else: ?>
                <?php foreach ($quotes as $quote): ?>
                    <tr>
                        <td><?= htmlspecialchars($quote['id']) ?></td>
                        <td>
                            <div style="max-width: 500px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="<?= htmlspecialchars($quote['quote_text']) ?>">
                                <?= htmlspecialchars($quote['quote_text']) ?>
                            </div>
                        </td>
                        <td><?= htmlspecialchars($quote['sort_order']) ?></td>
                        <td>
                            <?php if ($quote['is_active'] == 1): ?>
                                <span style="color: #10b981; font-size: 12px;">✓ 启用</span>
                            <?php else: ?>
                                <span style="color: #9ca3af; font-size: 12px;">✗ 禁用</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <div style="display: flex; gap: 6px; flex-wrap: wrap;">
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('确定要<?= $quote['is_active'] == 1 ? '禁用' : '启用' ?>这条名言吗？');">
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= $quote['id'] ?>">
                                    <button type="submit" class="btn btn-sm" style="font-size: 12px; padding: 4px 8px;">
                                        <?= $quote['is_active'] == 1 ? '禁用' : '启用' ?>
                                    </button>
                                </form>
                                <button type="button" class="btn btn-sm" onclick="editQuote(<?= htmlspecialchars(json_encode($quote)) ?>)" style="font-size: 12px; padding: 4px 8px;">
                                    编辑
                                </button>
                                <form method="POST" action="" style="display: inline;" onsubmit="return confirm('确定要删除这条名言吗？此操作不可恢复！');">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= $quote['id'] ?>">
                                    <button type="submit" class="btn btn-sm" style="font-size: 12px; padding: 4px 8px; color: #ef4444;">
                                        删除
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- 编辑名言模态框 -->
<div id="edit-modal" style="display: none; position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.7); z-index: 2000; align-items: center; justify-content: center;">
    <div style="background: #1f2937; border: 1px solid rgba(55,65,81,0.85); border-radius: 10px; padding: 20px; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto;">
        <h3 class="admin-page-title" style="font-size: 15px; margin-bottom: 16px;">编辑名言</h3>
        <form method="POST" action="" id="edit-form">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="id" id="edit-id">
            <div style="margin-bottom: 12px;">
                <label style="display: block; margin-bottom: 6px; font-size: 13px; color: #9ca3af;">名言内容 *</label>
                <textarea name="quote_text" id="edit-quote-text" required style="width: 100%; min-height: 80px; padding: 8px; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; background: #020617; color: #e5e7eb; font-size: 14px; font-family: inherit;"></textarea>
            </div>
            <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-bottom: 12px;">
                <div>
                    <label style="display: block; margin-bottom: 6px; font-size: 13px; color: #9ca3af;">排序顺序</label>
                    <input type="number" name="sort_order" id="edit-sort-order" value="0" style="width: 100%; padding: 8px; border: 1px solid rgba(55,65,81,0.85); border-radius: 6px; background: #020617; color: #e5e7eb; font-size: 14px;">
                </div>
                <div style="display: flex; align-items: flex-end;">
                    <label style="display: flex; align-items: center; gap: 6px; font-size: 13px; color: #9ca3af;">
                        <input type="checkbox" name="is_active" id="edit-is-active" value="1" style="width: 16px; height: 16px;">
                        <span>启用</span>
                    </label>
                </div>
            </div>
            <div style="display: flex; gap: 8px;">
                <button type="submit" class="btn btn-primary">保存</button>
                <button type="button" class="btn" onclick="document.getElementById('edit-modal').style.display = 'none';">取消</button>
            </div>
        </form>
    </div>
</div>

<script>
function editQuote(quote) {
    document.getElementById('edit-id').value = quote.id;
    document.getElementById('edit-quote-text').value = quote.quote_text;
    document.getElementById('edit-sort-order').value = quote.sort_order;
    document.getElementById('edit-is-active').checked = quote.is_active == 1;
    document.getElementById('edit-modal').style.display = 'flex';
}

// 点击模态框外部关闭
document.getElementById('edit-modal').addEventListener('click', function(e) {
    if (e.target === this) {
        this.style.display = 'none';
    }
});
</script>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>

