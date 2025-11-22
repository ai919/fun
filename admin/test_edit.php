<?php
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/csrf.php';
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
        }
        header('Location: test_edit.php?id=' . $testId . '&section=results');
        exit;
    }
}

include __DIR__ . '/layout.php';
