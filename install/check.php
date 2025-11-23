<?php
/**
 * 环境检查 API
 */

header('Content-Type: application/json');

$results = [
    'php_version' => version_compare(PHP_VERSION, '7.4.0', '>='),
    'pdo_mysql' => extension_loaded('pdo_mysql'),
    'json' => extension_loaded('json'),
    'config_writable' => is_writable(__DIR__ . '/../config') || is_writable(__DIR__ . '/..'),
    'cache_writable' => is_writable(__DIR__ . '/../cache') || (is_dir(__DIR__ . '/../cache') && is_writable(__DIR__ . '/../cache')),
];

echo json_encode($results);

