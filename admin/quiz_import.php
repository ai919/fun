<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/QuizImporter.php';

$pageTitle = '测验 JSON 导入';
$pageSubtitle = '上传符合 schema 的 JSON，一键创建或覆盖测验、题目、选项与结果';
$activeMenu = 'quiz_import';

$alert = null;
$resultDetails = null;
$payloadJson = '';
$dryRunChecked = true;
$overwriteChecked = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $dryRunChecked = isset($_POST['dry_run']);
    $overwriteChecked = isset($_POST['overwrite']);

    $payloadSource = '';
    if (
        isset($_FILES['payload_file']) &&
        is_array($_FILES['payload_file']) &&
        $_FILES['payload_file']['error'] !== UPLOAD_ERR_NO_FILE
    ) {
        if ($_FILES['payload_file']['error'] !== UPLOAD_ERR_OK) {
            $alert = [
                'type' => 'error',
                'message' => '文件上传失败，请重试或改用“粘贴 JSON 内容”。',
            ];
        } else {
            $payloadSource = file_get_contents($_FILES['payload_file']['tmp_name']) ?: '';
        }
    } else {
        $payloadSource = (string)($_POST['payload_json'] ?? '');
    }

    $payloadJson = trim($payloadSource);

    if (!$alert) {
        if ($payloadJson === '') {
            $alert = ['type' => 'error', 'message' => '请上传 JSON 文件或粘贴 JSON 文本。'];
        } else {
            $payload = json_decode($payloadJson, true);
            if (json_last_error() !== JSON_ERROR_NONE) {
                $alert = ['type' => 'error', 'message' => 'JSON 解析失败：' . json_last_error_msg()];
            } else {
                try {
                    $importer = new QuizImporter($pdo);
                    $resultDetails = $importer->import($payload, $overwriteChecked, $dryRunChecked);
                    $resultDetails['dry_run'] = $dryRunChecked;
                    $resultDetails['overwrite'] = $overwriteChecked;

                    if ($dryRunChecked) {
                        $alert = ['type' => 'success', 'message' => 'Dry run 成功：未对数据库进行修改。'];
                    } else {
                        $operation = $resultDetails['action'] === 'update' ? '覆盖' : '创建';
                        $alert = [
                            'type' => 'success',
                            'message' => sprintf('测验 "%s" 已成功 %s。', $resultDetails['test_slug'], $operation),
                        ];
                        // 成功导入后清空文本框，避免重复导入
                        $payloadJson = '';
                    }
                } catch (Throwable $e) {
                    $alert = ['type' => 'error', 'message' => $e->getMessage()];
                }
            }
        }
    }
}

ob_start();
?>

<?php if ($alert): ?>
    <div class="admin-alert admin-alert--<?= $alert['type'] === 'success' ? 'success' : 'error' ?>" style="margin-bottom: 16px;">
        <?= htmlspecialchars($alert['message']) ?>
    </div>
<?php endif; ?>

<div class="admin-card">
    <h2 style="margin-top: 0; font-size: 18px;">导入配置</h2>
    <form method="POST" enctype="multipart/form-data" style="display: flex; flex-direction: column; gap: 16px;">
        <div>
            <label style="display: block; font-weight: 500; margin-bottom: 6px;">上传 JSON 文件</label>
            <input type="file" name="payload_file" accept=".json,application/json"
                   style="padding: 6px; border: 1px dashed var(--admin-border-color); border-radius: 8px; width: 100%;">
            <p class="admin-table__muted" style="margin-top: 6px;">文件优先于文本框，如两者同时提供则以文件内容为准。</p>
        </div>

        <div>
            <label style="display: block; font-weight: 500; margin-bottom: 6px;">或粘贴 JSON 内容</label>
            <textarea name="payload_json" rows="12" spellcheck="false"
                      style="width: 100%; border-radius: 10px; border: 1px solid var(--admin-border-color); padding: 12px; font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, 'Liberation Mono', monospace; font-size: 12px; background: rgba(15,23,42,0.65); color: #e5e7eb;"><?= htmlspecialchars($payloadJson) ?></textarea>
            <p class="admin-table__muted" style="margin-top: 6px;">可直接从产品策划工具导出的 JSON 粘贴到此处。</p>
        </div>

        <div style="display: flex; gap: 18px; flex-wrap: wrap;">
            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                <input type="checkbox" name="dry_run" value="1" <?= $dryRunChecked ? 'checked' : '' ?>>
                <span>先执行 Dry run（推荐）</span>
            </label>
            <label style="display: flex; align-items: center; gap: 8px; font-size: 13px;">
                <input type="checkbox" name="overwrite" value="1" <?= $overwriteChecked ? 'checked' : '' ?>>
                <span>允许覆盖同 slug 测验</span>
            </label>
        </div>

        <div style="display: flex; gap: 10px; flex-wrap: wrap;">
            <button type="submit" class="btn btn-primary btn-lg">
                <?= $dryRunChecked ? '运行 Dry run' : '执行导入' ?>
            </button>
            <button type="button" class="btn btn-sm" onclick="document.querySelector('textarea[name=payload_json]').value='';">
                清空文本
            </button>
            <a href="../tools/quiz-import/README.md" target="_blank" class="btn btn-sm">查看 schema & CLI 使用说明</a>
        </div>

        <p class="admin-table__muted" style="margin: 0;">
            JSON schema 位于仓库 <code>tools/quiz-import/schema/quiz-import.schema.json</code>，也可在终端通过
            <code>yarn quiz:import payload.json --dry-run</code> 先行验证。
        </p>
    </form>
</div>

<?php if ($resultDetails): ?>
    <div class="admin-card" style="margin-top: 16px;">
        <h3 style="margin-top: 0;">执行结果</h3>
        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px;">
            <div class="stat-card">
                <div class="stat-card__label">操作类型</div>
                <div class="stat-card__value">
                    <?= $resultDetails['action'] === 'update' ? '覆盖现有测验' : '创建新测验' ?>
                    <?php if ($resultDetails['dry_run']): ?>
                        <span class="tag-chip" style="margin-left: 6px; background: rgba(250, 204, 21, 0.2); color: #facc15; border: 1px solid rgba(250, 204, 21, 0.4);">
                            Dry run
                        </span>
                    <?php endif; ?>
                </div>
            </div>
            <div class="stat-card">
                <div class="stat-card__label">测验 slug</div>
                <div class="stat-card__value"><code><?= htmlspecialchars($resultDetails['test_slug']) ?></code></div>
            </div>
            <div class="stat-card">
                <div class="stat-card__label">题目数量</div>
                <div class="stat-card__value"><?= (int)$resultDetails['questions_count'] ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-card__label">结果数量</div>
                <div class="stat-card__value"><?= (int)$resultDetails['results_count'] ?></div>
            </div>
        </div>

        <?php if (!empty($resultDetails['preview'])): ?>
            <div style="margin-top: 16px;">
                <div class="admin-table__muted" style="margin-bottom: 6px;">测验信息预览</div>
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <span><strong>标题：</strong><?= htmlspecialchars($resultDetails['preview']['title']) ?></span>
                    <span><strong>状态：</strong><?= htmlspecialchars($resultDetails['preview']['status']) ?></span>
                    <?php if (!empty($resultDetails['preview']['tags'])): ?>
                        <span><strong>标签：</strong>
                            <?php foreach ($resultDetails['preview']['tags'] as $tag): ?>
                                <span class="tag-chip"><?= htmlspecialchars($tag) ?></span>
                            <?php endforeach; ?>
                        </span>
                    <?php endif; ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if (!$resultDetails['dry_run']): ?>
            <div style="margin-top: 16px;" class="admin-table__muted">
                已写入数据库（test_id = <?= (int)($resultDetails['test_id'] ?? 0) ?>）。
                如需再次导入，请重新上传或粘贴新的 JSON。
            </div>
        <?php else: ?>
            <div style="margin-top: 16px;" class="admin-table__muted">
                Dry run 模式下未执行数据库写入。确认无误后取消“Dry run”复选框即可正式导入。
            </div>
        <?php endif; ?>
    </div>
<?php endif; ?>

<?php
$content = ob_get_clean();
include __DIR__ . '/layout.php';

