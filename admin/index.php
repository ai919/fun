<?php
require __DIR__ . '/auth.php';
require_admin_login();

$pageTitle    = '控制台';
$pageHeading  = '控制台';
$pageSubtitle = '欢迎回来，快速进入你需要的管理入口。';
$activeMenu   = 'dashboard';
$contentFile  = __DIR__ . '/partials/dashboard_content.php';

require __DIR__ . '/layout.php';
require __DIR__ . '/layout_footer.php';
