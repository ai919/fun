<?php
require_once __DIR__ . '/auth.php';
require_admin_login();

$isEditing   = isset($_GET['id']) && ctype_digit((string)$_GET['id']);
$pageTitle   = $isEditing ? '编辑测试' : '新建测试';
$pageHeading = $pageTitle;
$activeMenu  = 'tests';
$contentFile = __DIR__ . '/partials/test_edit_content.php';

include __DIR__ . '/layout.php';
