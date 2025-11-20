<?php
$query = '';
if (isset($_GET['id']) && ctype_digit((string)$_GET['id'])) {
    $query = '?id=' . (int)$_GET['id'];
}
header('Location: /admin/test_edit.php' . $query);
exit;
