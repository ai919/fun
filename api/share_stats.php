<?php
/**
 * 分享统计API
 */
require_once __DIR__ . '/../lib/db_connect.php';
require_once __DIR__ . '/../lib/ShareStats.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$data = json_decode(file_get_contents('php://input'), true);
$shareToken = $data['share_token'] ?? null;
$platform = $data['platform'] ?? null;

if (!$shareToken) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Missing share_token']);
    exit;
}

$success = ShareStats::recordShare($shareToken, $platform);
echo json_encode(['success' => $success]);

