<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/csrf.php';
require_once __DIR__ . '/../lib/CacheHelper.php';
require_once __DIR__ . '/../lib/Constants.php';
require_admin_login();

$isEditing   = isset($_GET['id']) && ctype_digit((string)$_GET['id']);
$pageTitle   = $isEditing ? '编辑测验' : '新建测验';
$pageHeading = $pageTitle;
$activeMenu  = 'tests';
$contentFile = __DIR__ . '/partials/test_edit_content.php';
$section = isset($_GET['section']) ? trim((string)$_GET['section']) : 'basic';
if (!in_array($section, ['basic', 'questions', 'results'], true)) {
    $section = 'basic';
}

$testId = isset($_GET['id']) && ctype_digit((string)$_GET['id']) ? (int)$_GET['id'] : 0;

// 处理基本信息保存的 POST 请求（在包含 layout 之前处理，避免 headers already sent 错误）
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_basic') {
    if (!CSRF::validateToken()) {
        http_response_code(403);
        die('CSRF token 验证失败，请刷新页面后重试');
    }
    
    // 检查列是否存在
    function admin_column_exists(PDO $pdo, string $table, string $column): bool {
        static $cache = [];
        $dbName = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        $key = "{$dbName}.{$table}.{$column}";
        if (array_key_exists($key, $cache)) {
            return $cache[$key];
        }
        $stmt = $pdo->prepare("
            SELECT 1
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ?
            LIMIT 1
        ");
        $stmt->execute([$dbName, $table, $column]);
        $cache[$key] = (bool)$stmt->fetchColumn();
        return $cache[$key];
    }
    
    $hasEmojiCol = admin_column_exists($pdo, 'tests', 'emoji');
    $hasTitleColorCol = admin_column_exists($pdo, 'tests', 'title_color');
    $statuses = Constants::getTestStatusLabels();
    $scoringModes = Constants::getScoringModeLabels();
    $errors = [];
    
    // 获取现有数据（如果是编辑）
    $existingTest = null;
    if ($testId) {
        $stmt = $pdo->prepare('SELECT * FROM tests WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $testId]);
        $existingTest = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existingTest) {
            http_response_code(404);
            die('未找到对应的测验');
        }
    }
    
    // 处理表单数据
    $formData = [
        'title'          => trim($_POST['title'] ?? ''),
        'slug'           => strtolower(trim($_POST['slug'] ?? '')),
        'subtitle'       => trim($_POST['subtitle'] ?? ''),
        'description'    => trim($_POST['description'] ?? ''),
        'tags'           => trim($_POST['tags'] ?? ''),
        'status'         => $_POST['status'] ?? Constants::TEST_STATUS_DRAFT,
        'sort_order'     => (int)($_POST['sort_order'] ?? 0),
        'scoring_mode'   => $_POST['scoring_mode'] ?? Constants::SCORING_MODE_SIMPLE,
        'scoring_config' => trim($_POST['scoring_config'] ?? ''),
        'display_mode'   => ($_POST['display_mode'] ?? Constants::DISPLAY_MODE_SINGLE_PAGE) === Constants::DISPLAY_MODE_STEP_BY_STEP ? Constants::DISPLAY_MODE_STEP_BY_STEP : Constants::DISPLAY_MODE_SINGLE_PAGE,
    ];
    
    $titleColorClear = ($_POST['title_color_clear'] ?? '0') === '1';
    $formData['title_color'] = $titleColorClear ? '' : trim($_POST['title_color'] ?? '');
    $selectedEmoji = trim($_POST['emoji'] ?? '');
    $customEmoji = trim($_POST['emoji_custom'] ?? '');
    $formData['emoji'] = $customEmoji !== '' ? $customEmoji : $selectedEmoji;
    
    // 验证
    if ($formData['title'] === '') {
        $errors[] = '测验标题不能为空。';
    } elseif (mb_strlen($formData['title']) > 255) {
        $errors[] = '测验标题最长支持 255 个字符。';
    }
    if ($formData['slug'] === '') {
        $errors[] = 'Slug 不能为空。';
    } elseif (!preg_match('/^[a-z0-9_-]+$/', $formData['slug'])) {
        $errors[] = 'Slug 只能包含小写字母、数字、短横线和下划线。';
    } elseif (mb_strlen($formData['slug']) > 100) {
        $errors[] = 'Slug 最长支持 100 个字符。';
    }
    if (mb_strlen($formData['subtitle']) > 255) {
        $errors[] = '副标题最长支持 255 个字符。';
    }
    if (!isset($statuses[$formData['status']])) {
        $errors[] = '请选择有效的状态。';
    }
    if (!array_key_exists($formData['scoring_mode'], $scoringModes)) {
        $errors[] = '请选择有效的评分模式。';
    }
    if ($hasTitleColorCol && $formData['title_color'] !== '' && !preg_match('/^#[0-9a-fA-F]{6}$/', $formData['title_color'])) {
        $errors[] = '请输入合法的颜色值，例如 #6366F1。';
    }
    if (mb_strlen($formData['emoji']) > 16) {
        $errors[] = 'Emoji 最长支持 16 个字符。';
    }
    
    $tagsNormalized = '';
    if ($formData['tags'] !== '') {
        $tagPieces = array_unique(array_filter(array_map('trim', explode(',', $formData['tags']))));
        $tagsNormalized = implode(', ', $tagPieces);
        if (mb_strlen($tagsNormalized) > 255) {
            $errors[] = '标签总长度最长支持 255 个字符。';
        }
    }
    $formData['tags'] = $tagsNormalized;
    
    if ($formData['scoring_config'] !== '') {
        json_decode($formData['scoring_config'], true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            $errors[] = '评分配置不是合法的 JSON。';
        }
    }
    
    // 检查 slug 唯一性
    if ($formData['slug'] !== '') {
        if ($testId) {
            $slugStmt = $pdo->prepare('SELECT COUNT(*) FROM tests WHERE slug = :slug AND id != :id');
            $slugStmt->execute([':slug' => $formData['slug'], ':id' => $testId]);
        } else {
            $slugStmt = $pdo->prepare('SELECT COUNT(*) FROM tests WHERE slug = :slug');
            $slugStmt->execute([':slug' => $formData['slug']]);
        }
        if ((int)$slugStmt->fetchColumn() > 0) {
            $errors[] = '该 slug 已存在，请换一个。';
        }
    }
    
    // 如果没有错误，保存数据
    if (!$errors) {
        $payload = [
            ':title'          => $formData['title'],
            ':slug'           => $formData['slug'],
            ':subtitle'       => $formData['subtitle'] !== '' ? $formData['subtitle'] : null,
            ':description'    => $formData['description'] !== '' ? $formData['description'] : null,
            ':tags'           => $formData['tags'] !== '' ? $formData['tags'] : null,
            ':status'         => $formData['status'],
            ':sort_order'     => $formData['sort_order'],
            ':scoring_mode'   => $formData['scoring_mode'],
            ':scoring_config' => $formData['scoring_config'] !== '' ? $formData['scoring_config'] : null,
            ':display_mode'   => $formData['display_mode'],
        ];
        if ($hasEmojiCol) {
            $payload[':emoji'] = $formData['emoji'] !== '' ? $formData['emoji'] : null;
        }
        if ($hasTitleColorCol) {
            $payload[':title_color'] = $formData['title_color'] !== '' ? $formData['title_color'] : null;
        }
        
        if ($testId) {
            $payload[':id'] = $testId;
            $setParts = [
                'title = :title',
                'slug = :slug',
                'subtitle = :subtitle',
                'description = :description',
                'tags = :tags',
                'status = :status',
                'sort_order = :sort_order',
                'scoring_mode = :scoring_mode',
                'scoring_config = :scoring_config',
                'display_mode = :display_mode',
                'updated_at = NOW()',
            ];
            if ($hasEmojiCol) {
                $setParts[] = 'emoji = :emoji';
            }
            if ($hasTitleColorCol) {
                $setParts[] = 'title_color = :title_color';
            }
            $updateSql = "UPDATE tests SET " . implode(",\n                ", $setParts) . " WHERE id = :id";
            $updateStmt = $pdo->prepare($updateSql);
            $updateStmt->execute($payload);
        } else {
            $columns = ['title', 'slug', 'subtitle', 'description', 'tags', 'status', 'sort_order', 'scoring_mode', 'scoring_config', 'display_mode'];
            $placeholders = [':title', ':slug', ':subtitle', ':description', ':tags', ':status', ':sort_order', ':scoring_mode', ':scoring_config', ':display_mode'];
            if ($hasEmojiCol) {
                $columns[] = 'emoji';
                $placeholders[] = ':emoji';
            }
            if ($hasTitleColorCol) {
                $columns[] = 'title_color';
                $placeholders[] = ':title_color';
            }
            $insertSql = "INSERT INTO tests (" . implode(', ', $columns) . ")
                VALUES (" . implode(', ', $placeholders) . ")";
            $insertStmt = $pdo->prepare($insertSql);
            $insertStmt->execute($payload);
            $testId = (int)$pdo->lastInsertId();
        }
        
        // 清除相关缓存（传入新的 slug 以精确清除）
        CacheHelper::clearTestCache($testId, $formData['slug'] ?? null);
        // 如果是在编辑模式下且 slug 改变了，也需要清除旧的 slug 缓存
        if ($testId && $existingTest && isset($existingTest['slug']) && isset($formData['slug']) && $existingTest['slug'] !== $formData['slug']) {
            // 清除旧 slug 的缓存
            CacheHelper::delete('test_slug_' . md5($existingTest['slug']));
            CacheHelper::delete('test_slug_id_' . md5($existingTest['slug']));
        }
        
        header('Location: /admin/tests.php?msg=saved');
        exit;
    } else {
        // 如果有错误，将错误信息存储到 session 中，然后重定向回编辑页面
        $_SESSION['test_edit_errors'] = $errors;
        $_SESSION['test_edit_form_data'] = $formData;
        if ($testId) {
            header('Location: test_edit.php?id=' . $testId . '&section=basic');
        } else {
            header('Location: test_edit.php?section=basic');
        }
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['edit_type'])) {
    if (!CSRF::validateToken()) {
        http_response_code(403);
        die('CSRF token 验证失败，请刷新页面后重试');
    }
    $editType = $_POST['edit_type'];

    if ($editType === 'question_copy') {
        $questionId   = isset($_POST['question_id']) ? (int)$_POST['question_id'] : 0;
        $questionText = trim($_POST['question_text'] ?? '');
        $options      = $_POST['option_text'] ?? [];

        if ($testId > 0 && $questionId > 0 && $questionText !== '') {
            $stmt = $pdo->prepare("
                UPDATE questions
                SET question_text = :qt
                WHERE id = :id AND test_id = :test_id
            ");
            $stmt->execute([
                ':qt'      => $questionText,
                ':id'      => $questionId,
                ':test_id' => $testId,
            ]);

            if (is_array($options)) {
                $stmtOpt = $pdo->prepare("
                    UPDATE question_options
                    SET option_text = :ot
                    WHERE id = :oid AND question_id = :qid
                ");
                foreach ($options as $optId => $text) {
                    $optId = (int)$optId;
                    $text  = trim((string)$text);
                    if ($optId > 0 && $text !== '') {
                        $stmtOpt->execute([
                            ':ot'  => $text,
                            ':oid' => $optId,
                            ':qid' => $questionId,
                        ]);
                    }
                }
            }
            
            // 清除测验缓存
            CacheHelper::clearTestCache($testId);
        }
        header('Location: test_edit.php?id=' . $testId . '&section=questions');
        exit;
    }

    if ($editType === 'result_copy') {
        $resultId    = isset($_POST['result_id']) ? (int)$_POST['result_id'] : 0;
        $title       = trim($_POST['title'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $imageUrl    = trim($_POST['image_url'] ?? '');

        if ($testId > 0 && $resultId > 0 && $title !== '') {
            $stmt = $pdo->prepare("
                UPDATE results
                SET title = :title,
                    description = :description,
                    image_url = :image_url
                WHERE id = :id AND test_id = :test_id
            ");
            $stmt->execute([
                ':title'       => $title,
                ':description' => $description,
                ':image_url'   => $imageUrl,
                ':id'          => $resultId,
                ':test_id'     => $testId,
            ]);
            
            // 清除测验缓存
            CacheHelper::clearTestCache($testId);
        }
        header('Location: test_edit.php?id=' . $testId . '&section=results');
        exit;
    }
}

include __DIR__ . '/layout.php';
