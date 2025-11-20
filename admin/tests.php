<?php
require_once __DIR__ . '/auth.php';
require_admin_login();

$pageTitle    = '测试列表';
$pageHeading  = '测试列表';
$pageSubtitle = '在这里可以搜索、筛选、删除测试或跳转至编辑页面。';
$activeMenu   = 'tests';
$contentFile  = __DIR__ . '/partials/tests_content.php';

include __DIR__ . '/layout.php';
