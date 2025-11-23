# 组件使用文档

本文档介绍如何使用新实现的公共组件和功能。

## 1. 多层级缓存系统 (Cache)

### 基本使用

```php
require_once __DIR__ . '/lib/Cache.php';

// 设置缓存（自动写入 L1/L2/L3）
Cache::set('user_123', $userData, 3600, ['user', 'user_123']);

// 获取缓存（自动从 L1 -> L2 -> L3 查找）
$userData = Cache::get('user_123', 3600);

// 删除缓存
Cache::delete('user_123');

// 根据标签批量删除缓存
Cache::deleteByTag('user'); // 删除所有 user 标签的缓存

// 清空所有缓存
Cache::clear();
```

### 标签化缓存失效

```php
// 设置缓存时添加标签
Cache::set('test_1', $testData, 1800, ['test', 'test_1']);
Cache::set('test_2', $testData2, 1800, ['test', 'test_2']);

// 当测验更新时，批量清除相关缓存
Cache::deleteByTag('test'); // 清除所有 test 标签的缓存
```

### 缓存统计

```php
$stats = Cache::getStats();
print_r($stats);
// 输出：
// [
//     'apcu_enabled' => true,
//     'redis_enabled' => false,
//     'file_count' => 150,
//     'tag_count' => 10,
//     'apcu_info' => [...],
// ]
```

## 2. 分页组件 (Pagination)

### 基本使用

```php
require_once __DIR__ . '/lib/Pagination.php';

// 创建分页对象
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;

// 查询总记录数
$totalRows = $pdo->query("SELECT COUNT(*) FROM tests")->fetchColumn();

// 创建分页器
$pagination = new Pagination($page, $totalRows, $perPage, '/admin/tests.php', [
    'q' => $_GET['q'] ?? '',
    'status' => $_GET['status'] ?? '',
]);

// 获取 SQL 偏移量
$offset = $pagination->getOffset();
$stmt = $pdo->prepare("SELECT * FROM tests LIMIT :limit OFFSET :offset");
$stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$stmt->execute();
$tests = $stmt->fetchAll(PDO::FETCH_ASSOC);

// 渲染分页 HTML（简单样式）
echo $pagination->render();

// 或渲染带页码列表的分页
echo $pagination->renderWithPages(2); // 每边显示 2 个页码
```

### 高级用法

```php
// 检查是否有上一页/下一页
if ($pagination->hasPrevious()) {
    $prevUrl = $pagination->getPageUrl($pagination->getPreviousPage());
}

// 获取页码范围
$pages = $pagination->getPageRange(2); // [1, 2, 3, 4, 5]
```

## 3. 数据验证器 (Validator)

### 单个验证

```php
require_once __DIR__ . '/lib/Validator.php';

// 验证必填
$result = Validator::required($_POST['title'], '标题');
if (!$result['valid']) {
    $errors[] = $result['message'];
}

// 验证长度
$result = Validator::length($_POST['title'], 3, 100, '标题');

// 验证邮箱
$result = Validator::email($_POST['email']);

// 验证用户名
$result = Validator::username($_POST['username']);

// 验证整数范围
$result = Validator::integer($_POST['age'], 1, 120, '年龄');
```

### 批量验证

```php
$data = [
    'username' => $_POST['username'] ?? '',
    'email' => $_POST['email'] ?? '',
    'password' => $_POST['password'] ?? '',
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
}
```

## 4. 文件上传 (FileUpload)

### 基本使用

```php
require_once __DIR__ . '/lib/FileUpload.php';

// 创建上传器
$uploader = new FileUpload(
    __DIR__ . '/uploads/images',
    ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
    5242880, // 5MB
    true // 按日期创建子目录
);

// 上传文件
if (isset($_FILES['image'])) {
    $result = $uploader->upload($_FILES['image'], 'custom_filename');
    
    if ($result['success']) {
        echo "上传成功: " . $result['path'];
        // $result['path'] 是相对路径，如: /uploads/images/2024/01/image.jpg
    } else {
        echo "上传失败: " . $result['message'];
    }
}

// 删除文件
$uploader->delete('/uploads/images/2024/01/image.jpg');
```

## 5. 图片处理 (ImageHelper)

### 缩放图片

```php
require_once __DIR__ . '/lib/ImageHelper.php';

// 缩放图片（保持宽高比）
ImageHelper::resize(
    '/path/to/source.jpg',
    '/path/to/resized.jpg',
    800,  // 最大宽度
    600,  // 最大高度
    true, // 保持宽高比
    85    // 质量
);

// 缩放图片（不保持宽高比）
ImageHelper::resize(
    '/path/to/source.jpg',
    '/path/to/thumbnail.jpg',
    200, 200, false, 85
);
```

### 裁剪图片

```php
// 裁剪图片
ImageHelper::crop(
    '/path/to/source.jpg',
    '/path/to/cropped.jpg',
    100,  // X 坐标
    100,  // Y 坐标
    400,  // 宽度
    300,  // 高度
    85    // 质量
);
```

### 生成缩略图

```php
// 生成正方形缩略图
ImageHelper::createThumbnail(
    '/path/to/source.jpg',
    '/path/to/thumb.jpg',
    200,  // 缩略图尺寸
    85    // 质量
);
```

### 转换为 WebP

```php
// 转换为 WebP 格式
ImageHelper::convertToWebP(
    '/path/to/source.jpg',
    '/path/to/image.webp',
    80 // 质量
);
```

### 获取图片信息

```php
$info = ImageHelper::getInfo('/path/to/image.jpg');
// [
//     'width' => 1920,
//     'height' => 1080,
//     'type' => 'JPEG',
//     'mime' => 'image/jpeg',
//     'size' => 245678
// ]
```

## 6. 模板系统 (View)

### 基本使用

```php
require_once __DIR__ . '/lib/View.php';

$view = View::getInstance();

// 设置变量
$view->assign('title', '我的页面');
$view->assign('user', $user);

// 或批量设置
$view->assign([
    'title' => '我的页面',
    'user' => $user,
]);

// 渲染模板
$html = $view->render('user/profile', ['extra' => 'data']);

// 或直接输出
$view->display('user/profile');
```

### 模板文件示例 (templates/user/profile.php)

```php
<!DOCTYPE html>
<html>
<head>
    <title><?= $this->escape($title) ?></title>
</head>
<body>
    <h1><?= $this->escape($user['name']) ?></h1>
    
    <?php if (!empty($user['email'])): ?>
        <p>邮箱: <?= $this->escape($user['email']) ?></p>
    <?php endif; ?>
    
    <p>注册时间: <?= $this->date($user['created_at']) ?></p>
    
    <?php $this->include('partials/footer') ?>
</body>
</html>
```

### 静态方法快速渲染

```php
$html = View::make('user/profile', [
    'title' => '我的页面',
    'user' => $user,
]);
```

## 7. 资源版本控制和 CDN (AssetHelper)

### 基本使用

```php
require_once __DIR__ . '/lib/AssetHelper.php';

// 生成 CSS 链接
echo AssetHelper::css('css/style.css');
// <link rel="stylesheet" href="/assets/css/style.css?v=20241120120000">

// 生成 JS 脚本
echo AssetHelper::js('js/app.js');
// <script src="/assets/js/app.js?v=20241120120000"></script>

// 生成图片标签
echo AssetHelper::img('images/logo.png', 'Logo');
// <img src="/assets/images/logo.png?v=20241120120000" alt="Logo">
```

### 使用 CDN

```php
// 在配置或环境变量中设置
AssetHelper::setCdnBaseUrl('https://cdn.example.com');

// 现在所有资源都会使用 CDN
echo AssetHelper::css('css/style.css');
// <link rel="stylesheet" href="https://cdn.example.com/assets/css/style.css?v=20241120120000">
```

### 更新版本号

```php
// 部署时更新版本号（清除浏览器缓存）
AssetHelper::updateVersion();
// 或指定版本号
AssetHelper::updateVersion('v2.0.0');
```

### 在模板中使用

```php
// 在模板文件中
<?php require_once __DIR__ . '/../lib/AssetHelper.php'; ?>

<!DOCTYPE html>
<html>
<head>
    <?= AssetHelper::css('css/style.css') ?>
    <?= AssetHelper::js('js/app.js', ['defer' => true]) ?>
</head>
<body>
    <?= AssetHelper::img('images/logo.png', 'Logo', ['class' => 'logo']) ?>
</body>
</html>
```

## 迁移指南

### 从 CacheHelper 迁移到 Cache

```php
// 旧代码
CacheHelper::set('key', $value);
$value = CacheHelper::get('key');

// 新代码
Cache::set('key', $value);
$value = Cache::get('key');

// 新功能：标签化缓存
Cache::set('test_1', $data, 1800, ['test', 'test_1']);
Cache::deleteByTag('test'); // 批量删除
```

### 更新分页代码

```php
// 旧代码（手动计算）
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 20;
$totalPages = ceil($totalRows / $perPage);
$offset = ($page - 1) * $perPage;
// ... 手动生成分页 HTML

// 新代码
$pagination = new Pagination($page, $totalRows, $perPage);
$offset = $pagination->getOffset();
echo $pagination->render();
```

### 更新资源引用

```php
// 旧代码
<link rel="stylesheet" href="/assets/css/style.css">

// 新代码
<?= AssetHelper::css('css/style.css') ?>
```

