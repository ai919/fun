<?php

function df_base_url(): string
{
    $scheme = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on') ? 'https' : 'http';
    return $scheme . '://' . $_SERVER['HTTP_HOST'];
}

function df_current_url(): string
{
    return df_base_url() . ($_SERVER['REQUEST_URI'] ?? '/');
}

function df_seo_for_test(array $test): array
{
    $site  = 'DoFun 性格实验室';
    $title = trim(($test['title'] ?? '') . ' - ' . $site);

    $desc = $test['description'] ?? '关于你的性格与选择的小实验。';
    $desc = mb_substr(trim($desc), 0, 120);

    $slug = $test['slug'] ?? '';
    $path = $slug ? '/' . ltrim($slug, '/') : df_current_url();
    $url  = df_base_url() . $path;

    return [
        'title'       => $title,
        'description' => $desc,
        'url'         => $url,
        'image'       => df_base_url() . '/og.php?scope=test&id=' . urlencode($test['id'] ?? ''),
    ];
}

function df_seo_for_result(array $test, array $result): array
{
    $site  = 'DoFun 性格实验室';
    $title = trim(($result['title'] ?? '') . ' - ' . ($test['title'] ?? '') . ' - ' . $site);

    $desc  = $result['description'] ?? ($test['description'] ?? '关于你的性格与关系的小实验结果。');
    $desc  = mb_substr(trim($desc), 0, 150);

    $url   = df_base_url() . '/result.php?test_id=' . urlencode($test['id'] ?? '') . '&code=' . urlencode($result['code'] ?? '');

    return [
        'title'       => $title,
        'description' => $desc,
        'url'         => $url,
        'image'       => df_base_url() . '/og.php?scope=result&test_id=' . urlencode($test['id'] ?? '') . '&code=' . urlencode($result['code'] ?? ''),
    ];
}
