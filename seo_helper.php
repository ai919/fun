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
 * 构建面包屑导航结构化数据
 */
function build_breadcrumb_structured_data(array $items): array
{
    $baseUrl = get_base_url();
    $breadcrumbList = [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => [],
    ];

    $position = 1;
    foreach ($items as $item) {
        $breadcrumbList['itemListElement'][] = [
            '@type' => 'ListItem',
            'position' => $position,
            'name' => $item['name'] ?? '',
            'item' => isset($item['url']) ? ($item['url'][0] === '/' ? $baseUrl . $item['url'] : $item['url']) : '',
        ];
        $position++;
    }

    return $breadcrumbList;
}

/**
 * 构建 FAQPage 结构化数据
 */
function build_faq_structured_data(array $faqs): array
{
    $faqPage = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => [],
    ];

    foreach ($faqs as $faq) {
        $faqPage['mainEntity'][] = [
            '@type' => 'Question',
            'name' => $faq['question'] ?? '',
            'acceptedAnswer' => [
                '@type' => 'Answer',
                'text' => $faq['answer'] ?? '',
            ],
        ];
    }

    return $faqPage;
}

/**
 * 构建 Quiz/Assessment 结构化数据
 */
function build_quiz_structured_data(array $test, $questions = []): array
{
    $baseUrl = get_base_url();
    $slug = $test['slug'] ?? '';
    $quiz = [
        '@context' => 'https://schema.org',
        '@type' => 'Quiz',
        'name' => $test['title'] ?? '',
        'description' => $test['description'] ?? '',
        'url' => build_canonical_url('/test.php?slug=' . urlencode($slug)),
    ];

    if (!empty($test['cover_image'])) {
        $quiz['image'] = $test['cover_image'];
    }

    // 只使用题目数量，避免传递完整数组
    if (!empty($questions)) {
        $quiz['numberOfQuestions'] = is_array($questions) ? count($questions) : (int)$questions;
    }

    return $quiz;
}

/**
 * 为不同页面类型构建 SEO 数据
 */
function build_seo_meta(string $pageType, array $data = []): array
{
    // 从 settings 表读取 SEO 配置
    require_once __DIR__ . '/lib/SettingsHelper.php';
    
    $siteName = SettingsHelper::get('seo_site_name', 'DoFun心理实验空间');
    $baseUrl  = get_base_url();
    
    // 获取默认图片，如果设置了自定义图片则使用，否则使用默认路径
    $defaultImageSetting = SettingsHelper::get('seo_default_image', '');
    $defaultImage = !empty($defaultImageSetting) 
        ? $defaultImageSetting 
        : ($baseUrl . '/assets/img/dofun-poster-bg.jpg');

    $title = SettingsHelper::get('seo_default_title', $siteName);
    $desc  = SettingsHelper::get('seo_default_description', '心理 性格 性情：更专业的在线测验实验室。');
    $canonical = build_canonical_url();
    $image = $defaultImage;
    $type  = SettingsHelper::get('seo_og_type_default', 'website');
    $robots = SettingsHelper::get('seo_robots_default', 'index,follow');
    $additionalStructuredData = [];

    if ($pageType === 'home') {
        // 使用 settings 中的默认标题和描述，如果没有则使用默认值
        $title = SettingsHelper::get('seo_default_title', $siteName . '｜心理 性格 性情：更专业的在线测验实验室');
        $desc  = SettingsHelper::get('seo_default_description', 'DoFun心理实验空间，是一个轻量、有趣的在线测验实验室，提供人格、情感、社交、生活方式等多个方向的心理小测试，帮你以更轻松的方式认识自己。');
        $canonical = build_canonical_url('/');
        $type = SettingsHelper::get('seo_og_type_default', 'website');
    } elseif ($pageType === 'test') {
        $test = $data['test'] ?? [];
        $slug = $test['slug'] ?? '';
        $tTitle = $test['title'] ?? '';
        $subtitle = $test['subtitle'] ?? '';
        $descText = $test['description'] ?? '';
        $questions = $data['questions'] ?? [];

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

        // 添加 Quiz 结构化数据
        // 只传递题目数量或空数组，避免内存问题
        if (!empty($test)) {
            $questionCount = is_numeric($questions) ? (int)$questions : (is_array($questions) ? count($questions) : 0);
            $additionalStructuredData[] = build_quiz_structured_data($test, $questionCount > 0 ? $questionCount : []);
        }

        // 添加面包屑导航
        $breadcrumbs = [
            ['name' => '首页', 'url' => '/'],
            ['name' => '测验列表', 'url' => '/index.php'],
            ['name' => $tTitle ?: '测验', 'url' => '/test.php?slug=' . urlencode($slug)],
        ];
        $additionalStructuredData[] = build_breadcrumb_structured_data($breadcrumbs);
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

        // 添加面包屑导航
        $breadcrumbs = [
            ['name' => '首页', 'url' => '/'],
            ['name' => '测验列表', 'url' => '/index.php'],
            ['name' => $tTitle ?: '测验', 'url' => '/test.php?slug=' . urlencode($slug)],
            ['name' => $rTitle ?: '结果', 'url' => '/result.php?slug=' . urlencode($slug) . '&result=' . urlencode($code)],
        ];
        $additionalStructuredData[] = build_breadcrumb_structured_data($breadcrumbs);
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

        // 如果提供了面包屑数据，添加面包屑导航
        if (!empty($data['breadcrumbs']) && is_array($data['breadcrumbs'])) {
            $additionalStructuredData[] = build_breadcrumb_structured_data($data['breadcrumbs']);
        }
    }

    // 如果提供了 FAQ 数据，添加 FAQPage 结构化数据
    if (!empty($data['faqs']) && is_array($data['faqs'])) {
        $additionalStructuredData[] = build_faq_structured_data($data['faqs']);
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

    // 合并所有结构化数据
    $allStructuredData = [$jsonLdData];
    $allStructuredData = array_merge($allStructuredData, $additionalStructuredData);

    // 获取默认关键词
    $keywords = SettingsHelper::get('seo_default_keywords', '');
    
    return [
        'title'       => $title,
        'description' => $desc,
        'canonical'   => $canonical,
        'image'       => $image,
        'type'        => $type,
        'robots'      => $robots,
        'keywords'    => $keywords,
        'json_ld'     => json_encode($jsonLdData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
        'json_ld_all' => array_map(function($data) {
            return json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }, $allStructuredData),
    ];
}

/**
 * 在 <head> 中输出 SEO / OG / Twitter meta 标签
 */
function render_seo_head(array $seo): void
{
    // 从 settings 表读取 SEO 配置
    require_once __DIR__ . '/lib/SettingsHelper.php';
    
    $title       = htmlspecialchars($seo['title'] ?? '', ENT_QUOTES, 'UTF-8');
    $description = htmlspecialchars($seo['description'] ?? '', ENT_QUOTES, 'UTF-8');
    $canonical   = htmlspecialchars($seo['canonical'] ?? '', ENT_QUOTES, 'UTF-8');
    $image       = htmlspecialchars($seo['image'] ?? '', ENT_QUOTES, 'UTF-8');
    $type        = htmlspecialchars($seo['type'] ?? SettingsHelper::get('seo_og_type_default', 'website'), ENT_QUOTES, 'UTF-8');
    $robots      = htmlspecialchars($seo['robots'] ?? SettingsHelper::get('seo_robots_default', 'index,follow'), ENT_QUOTES, 'UTF-8');
    $jsonLd      = $seo['json_ld'] ?? '';
    $jsonLdAll   = $seo['json_ld_all'] ?? [];
    $siteName    = SettingsHelper::get('seo_site_name', 'DoFun心理实验空间');
    $siteNameEsc = htmlspecialchars($siteName, ENT_QUOTES, 'UTF-8');

    echo "<title>{$title}</title>\n";
    echo "<meta charset=\"UTF-8\">\n";
    echo "<meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n";
    echo "<meta name=\"description\" content=\"{$description}\">\n";
    
    // 输出 keywords meta 标签（如果 SEO 数据中有提供，否则使用默认值）
    $keywords = $seo['keywords'] ?? SettingsHelper::get('seo_default_keywords', '');
    if (!empty($keywords)) {
        echo "<meta name=\"keywords\" content=\"" . htmlspecialchars($keywords, ENT_QUOTES, 'UTF-8') . "\">\n";
    }
    
    echo "<meta name=\"robots\" content=\"{$robots}\">\n";
    echo "<link rel=\"canonical\" href=\"{$canonical}\">\n";

    echo "<meta property=\"og:title\" content=\"{$title}\">\n";
    echo "<meta property=\"og:description\" content=\"{$description}\">\n";
    echo "<meta property=\"og:type\" content=\"{$type}\">\n";
    echo "<meta property=\"og:url\" content=\"{$canonical}\">\n";
    echo "<meta property=\"og:image\" content=\"{$image}\">\n";
    echo "<meta property=\"og:site_name\" content=\"{$siteNameEsc}\">\n";

    $twitterCard = SettingsHelper::get('seo_twitter_card', 'summary_large_image');
    echo "<meta name=\"twitter:card\" content=\"" . htmlspecialchars($twitterCard, ENT_QUOTES, 'UTF-8') . "\">\n";
    echo "<meta name=\"twitter:title\" content=\"{$title}\">\n";
    echo "<meta name=\"twitter:description\" content=\"{$description}\">\n";
    echo "<meta name=\"twitter:image\" content=\"{$image}\">\n";

    // 输出所有结构化数据（包括主 JSON-LD 和额外的结构化数据）
    if (!empty($jsonLdAll) && is_array($jsonLdAll)) {
        foreach ($jsonLdAll as $structuredData) {
            if (!empty($structuredData)) {
                echo "<script type=\"application/ld+json\">{$structuredData}</script>\n";
            }
        }
    } elseif (!empty($jsonLd)) {
        // 向后兼容：如果没有 json_ld_all，使用旧的 json_ld
        echo "<script type=\"application/ld+json\">{$jsonLd}</script>\n";
    }
}
