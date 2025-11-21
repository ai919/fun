<?php
require_once __DIR__ . '/lib/user_auth.php';

UserAuth::logout();
header('Location: /');
exit;
