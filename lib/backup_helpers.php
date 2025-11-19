<?php

function backup_get_expected_token(array $config): string
{
    $token = trim((string)($config['token'] ?? ''));
    if ($token === '' || strtoupper($token) === 'CHANGE_ME_TO_A_LONG_RANDOM_STRING') {
        http_response_code(500);
        echo 'Backup token is not configured. Please set a strong token in backup_config.php.';
        exit;
    }
    return $token;
}

function backup_require_token(array $config, string $providedToken): void
{
    $expected = backup_get_expected_token($config);
    if ($providedToken === '' || !hash_equals($expected, $providedToken)) {
        http_response_code(403);
        echo 'Forbidden';
        exit;
    }
}
