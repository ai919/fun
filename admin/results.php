<?php
require_once __DIR__ . '/auth.php';
require_admin_login();
require_once __DIR__ . '/../lib/db_connect.php';

$testId = isset($_GET['test_id']) ? (int)$_GET['test_id'] : null;
$slug   = isset($_GET['slug']) ? trim((string)$_GET['slug']) : '';

if (!$testId && $slug !== '') {
    $stmt = $pdo->prepare("SELECT id FROM tests WHERE slug = :slug LIMIT 1");
    $stmt->execute([':slug' => $slug]);
    if ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $testId = (int)$row['id'];
    }
}

if ($testId) {
    header('Location: test_edit.php?id=' . $testId . '#results');
    exit;
}

header('Location: tests.php');
exit;
