<?php
/**
 * 组件使用示例
 * 
 * 本文件展示了如何使用新实现的各个组件
 */

require_once __DIR__ . '/../lib/Cache.php';
require_once __DIR__ . '/../lib/Pagination.php';
require_once __DIR__ . '/../lib/Validator.php';
require_once __DIR__ . '/../lib/FileUpload.php';
require_once __DIR__ . '/../lib/ImageHelper.php';
require_once __DIR__ . '/../lib/View.php';
require_once __DIR__ . '/../lib/AssetHelper.php';

// ============================================
// 1. 缓存系统示例
// ============================================

echo "=== 缓存系统示例 ===\n";

// 设置缓存（带标签）
$testData = ['id' => 1, 'title' => '测试测验'];
Cache::set('test_1', $testData, 1800, ['test', 'test_1']);

// 获取缓存
$cached = Cache::get('test_1');
var_dump($cached);

// 根据标签批量删除
Cache::deleteByTag('test');

// 获取统计信息
$stats = Cache::getStats();
echo "APCu 启用: " . ($stats['apcu_enabled'] ? '是' : '否') . "\n";
echo "Redis 启用: " . ($stats['redis_enabled'] ? '是' : '否') . "\n";
echo "文件缓存数量: " . $stats['file_count'] . "\n";

echo "\n";

// ============================================
// 2. 分页组件示例
// ============================================

echo "=== 分页组件示例 ===\n";

$pagination = new Pagination(
    currentPage: 3,
    totalItems: 150,
    perPage: 20,
    baseUrl: '/admin/tests.php',
    queryParams: ['q' => 'test', 'status' => 'published']
);

echo "当前页: " . $pagination->getCurrentPage() . "\n";
echo "总页数: " . $pagination->getTotalPages() . "\n";
echo "偏移量: " . $pagination->getOffset() . "\n";
echo "上一页 URL: " . $pagination->getPageUrl($pagination->getPreviousPage()) . "\n";
echo "下一页 URL: " . $pagination->getPageUrl($pagination->getNextPage()) . "\n";

// 渲染 HTML（在实际页面中使用）
// echo $pagination->render();

echo "\n";

// ============================================
// 3. 验证器示例
// ============================================

echo "=== 验证器示例 ===\n";

// 单个验证
$result = Validator::required('test', '用户名');
echo "验证结果: " . ($result['valid'] ? '通过' : '失败') . "\n";

$result = Validator::email('invalid-email', '邮箱');
echo "邮箱验证: " . ($result['valid'] ? '通过' : $result['message']) . "\n";

// 批量验证
$data = [
    'username' => 'testuser',
    'email' => 'test@example.com',
    'password' => '123456',
];

$rules = [
    'username' => [
        ['method' => 'required', 'fieldName' => '用户名'],
        ['method' => 'username'],
    ],
    'email' => [
        ['method' => 'required', 'fieldName' => '邮箱'],
        ['method' => 'email'],
    ],
    'password' => [
        ['method' => 'required', 'fieldName' => '密码'],
        ['method' => 'password'],
    ],
];

$result = Validator::validate($data, $rules);
if (!$result['valid']) {
    foreach ($result['errors'] as $field => $message) {
        echo "$field: $message\n";
    }
} else {
    echo "所有验证通过\n";
}

echo "\n";

// ============================================
// 4. 文件上传示例
// ============================================

echo "=== 文件上传示例 ===\n";

$uploader = new FileUpload(
    uploadDir: __DIR__ . '/../uploads/images',
    allowedTypes: ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    maxSize: 5242880, // 5MB
    createSubdirs: true
);

// 注意：实际使用时需要 $_FILES 数据
// if (isset($_FILES['image'])) {
//     $result = $uploader->upload($_FILES['image'], 'my_image');
//     if ($result['success']) {
//         echo "上传成功: " . $result['path'] . "\n";
//     }
// }

echo "文件上传器已初始化\n";

echo "\n";

// ============================================
// 5. 图片处理示例
// ============================================

echo "=== 图片处理示例 ===\n";

// 注意：需要实际的图片文件
// ImageHelper::resize(
//     '/path/to/source.jpg',
//     '/path/to/resized.jpg',
//     800, 600, true, 85
// );

// ImageHelper::createThumbnail(
//     '/path/to/source.jpg',
//     '/path/to/thumb.jpg',
//     200, 85
// );

echo "图片处理功能可用\n";

echo "\n";

// ============================================
// 6. 模板系统示例
// ============================================

echo "=== 模板系统示例 ===\n";

$view = View::getInstance();
$view->setTemplateDir(__DIR__ . '/../templates');

$view->assign([
    'title' => '示例页面',
    'user' => ['name' => '测试用户', 'email' => 'test@example.com'],
]);

// 注意：需要创建对应的模板文件
// $html = $view->render('example');
// echo $html;

echo "模板系统已初始化\n";

echo "\n";

// ============================================
// 7. 资源版本控制示例
// ============================================

echo "=== 资源版本控制示例 ===\n";

// 生成资源 URL
$cssUrl = AssetHelper::url('css/style.css');
echo "CSS URL: $cssUrl\n";

$jsUrl = AssetHelper::url('js/app.js');
echo "JS URL: $jsUrl\n";

// 生成 HTML 标签
echo AssetHelper::css('css/style.css') . "\n";
echo AssetHelper::js('js/app.js', ['defer' => true]) . "\n";

// 获取当前版本号
echo "当前版本: " . AssetHelper::getVersion() . "\n";

// 使用 CDN（如果配置）
if (AssetHelper::isUsingCdn()) {
    echo "正在使用 CDN\n";
} else {
    echo "未使用 CDN（使用本地资源）\n";
}

echo "\n";

echo "=== 所有示例完成 ===\n";

