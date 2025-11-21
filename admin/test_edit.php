<?php
require_once __DIR__ . '/auth.php';
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

include __DIR__ . '/layout.php';
