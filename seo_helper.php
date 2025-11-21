<?php
// 统一的 SEO / OG / Twitter / JSON-LD 生成工具

/**
 * 获取当前站点的基础 URL（协议 + 域名）
 */
function get_base_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host   = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return $scheme . '://' . $host;
}

/**
 * 构建当前页面的 canonical URL
 */
function build_canonical_url(string $path = ''): string
{
    $base = rtrim(get_base_url(), '/');
    if ($path === '') {
        $uri = $_SERVER['REQUEST_URI'] ?? '/';
        return $base . strtok($uri, '#');
    }
    if ($path[0] !== '/') {
        $path = '/' . $path;
    }
    return $base . $path;
}

/**
 * 为不同页面类型构建 SEO 数据
 */
function build_seo_meta(string $pageType, array $data = []): array
{
    $siteName = 'DoFun 测验空间';
    $baseUrl  = get_base_url();
    $defaultImage = $baseUrl . '/assets/img/dofun-poster-bg.jpg';

    $title = $siteName;
    $desc  = '在线趣味测验空间，发现更有趣的自己。';
    $canonical = build_canonical_url();
    $image = $defaultImage;
    $type  = 'website';
    $robots = 'index,follow';

    if ($pageType === 'home') {
        $title = $siteName . '｜在线趣味测试更好发现自己';
        $desc  = 'DoFun 是一个轻量、有趣的在线测验空间，提供人格、情感、社交、生活方式等多个方向的心理小测试，帮你以更轻松的方式认识自己。';
        $canonical = build_canonical_url('/');
        $type = 'website';
    } elseif ($pageType === 'test') {
        $test = $data['test'] ?? [];
        $slug = $test['slug'] ?? '';
        $tTitle = $test['title'] ?? '';
        $subtitle = $test['subtitle'] ?? '';
        $descText = $test['description'] ?? '';

        $titleParts = [];
        if ($tTitle !== '') $titleParts[] = $tTitle;
        if ($subtitle !== '') $titleParts[] = $subtitle;
        $titleParts[] = $siteName;
        $title = implode('｜', $titleParts);

        if ($descText !== '') {
            $desc = trim(preg_replace('/\s+/', ' ', strip_tags($descText)));
            if (mb_strlen($desc) > 150) {
                $desc = mb_substr($desc, 0, 150) . '...';
            }
        }

        $canonical = build_canonical_url('/test.php?slug=' . urlencode($slug));
        $image = !empty($test['cover_image'] ?? '') ? $test['cover_image'] : $defaultImage;
        $type  = 'article';
    } elseif ($pageType === 'result') {
        $test   = $data['test'] ?? [];
        $result = $data['result'] ?? [];
        $slug   = $test['slug'] ?? '';
        $code   = $result['code'] ?? '';

        $tTitle = $test['title'] ?? '';
        $rTitle = $result['title'] ?? '';
        $descText = $result['description'] ?? ($test['description'] ?? '');

        $titleParts = [];
        if ($rTitle !== '') $titleParts[] = $rTitle;
        if ($tTitle !== '') $titleParts[] = $tTitle;
        $titleParts[] = $siteName;
        $title = implode('｜', $titleParts);

        if ($descText !== '') {
            $desc = trim(preg_replace('/\s+/', ' ', strip_tags($descText)));
            if (mb_strlen($desc) > 150) {
                $desc = mb_substr($desc, 0, 150) . '...';
            }
        }

        $canonical = build_canonical_url('/result.php?slug=' . urlencode($slug) . '&result=' . urlencode($code));
        $image = !empty($result['image_url'] ?? '') ? $result['image_url']
                 : (!empty($test['cover_image'] ?? '') ? $test['cover_image'] : $defaultImage);
        $type  = 'article';
    } else {
        if (!empty($data['title'])) {
            $title = $data['title'] . '｜' . $siteName;
        }
        if (!empty($data['description'])) {
            $desc = $data['description'];
        }
        if (!empty($data['canonical'])) {
            $canonical = $data['canonical'];
        }
        if (!empty($data['image'])) {
            $image = $data['image'];
        }
        $type = 'website';
    }

    $jsonLdData = [
        '@context' => 'https://schema.org',
        '@type'    => 'WebPage',
        'name'     => $title,
        'url'      => $canonical,
        'description' => $desc,
        'isPartOf' => [
            '@type' => 'WebSite',
            'name'  => $siteName,
            'url'   => $baseUrl,
        ],
    ];

    if ($pageType === 'test') {
        $jsonLdData['about'] = [
            '@type' => 'CreativeWork',
            'name'  => $test['title'] ?? '',
            'description' => $desc,
        ];
    }

    if ($pageType === 'result') {
        $jsonLdData['about'] = [
            '@type' => 'CreativeWork',
            'name'  => $result['title'] ?? '',
            'description' => $desc,
        ];
    }

    return [
        'title'       => $title,
        'description' => $desc,
        'canonical'   => $canonical,
        'image'       => $image,
        'type'        => $type,
        'robots'      => $robots,
        'json_ld'     => json_encode($jsonLdData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
    ];
}

/**
 * 在 <head> 中输出 SEO / OG / Twitter meta 标签
 */
function render_seo_head(array $seo): void
{
    $title       = htmlspecialchars($seo['title'] ?? '', ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($seo['description'] ?? '', ENT_QUOTES, 'UTF-8');
    $canonical   = htmlspecialchars($seo['canonical'] ?? '', ENT_QUOTES, 'UTF-8');
    $image       = htmlspecialchars($seo['image'] ?? '', ENT_QUOTES, 'UTF-8');
    $type        = htmlspecialchars($seo['type'] ?? 'website', ENT_QUOTES, 'UTF-8');
    $robots      = htmlspecialchars($seo['robots'] ?? 'index,follow', ENT_QUOTES, 'UTF-8');
    $jsonLd      = $seo['json_ld'] ?? '';
    $siteName    = 'DoFun 测验空间';
    $siteNameEsc = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');

    echo "<title>{$title}</title>\n";
    echo "<meta charset=\"UTF-8\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo "<meta name=\"description\" content=\"{$description}\">\n";
    echo "<meta name=\"robots\" content=\"{$robots}\">\n";
    echo "<link rel=\"canonical\" href=\"{$canonical}\">\n";

    echo "<meta property=\"og:title\" content=\"{$title}\">\n";
    echo "<meta property=\"og:description\" content=\"{$description}\">\n";
    echo "<meta property=\"og:type\" content=\"{$type}\">\n";
    echo "<meta property=\"og:url\" content=\"{$canonical}\">\n";
    echo "<meta property=\"og:image\" content=\"{$image}\">\n";
    echo "<meta property=\"og:site_name\" content=\"{$siteNameEsc}\">\n";

    echo "<meta name=\"twitter:card\" content=\"summary_large_image\">\n";
    echo "<meta name=\"twitter:title\" content=\"{$title}\">\n";
    echo "<meta name=\"twitter:description\" content=\"{$description}\">\n";
    echo "<meta name=\"twitter:image\" content=\"{$image}\">\n";

    if (!empty($jsonLd)) {
        echo "<script type=\"application/ld+json\">{$jsonLd}</script>\n";
    }
}
