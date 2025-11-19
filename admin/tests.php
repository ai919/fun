<?php
require_once __DIR__ . '/auth.php';
require_admin_login();

$pageTitle    = '�����б�';
$pageHeading  = '�����б�';
$pageSubtitle = '������ѯ���ˣ������༭����ɾ������Ŀ��';
$activeMenu   = 'tests';
$contentFile  = __DIR__ . '/partials/tests_content.php';

include __DIR__ . '/layout.php';
