# 统一响应格式使用文档

## 概述

`Response` 类提供了统一的 API 响应格式，支持 JSON 和 HTML 两种模式，确保所有 API 接口返回格式一致。

## 基本用法

### 成功响应

```php
require_once __DIR__ . '/lib/Response.php';

// 简单成功响应
Response::success();

// 带数据的成功响应
Response::success(['id' => 123, 'name' => '测试'], '创建成功');

// 带自定义状态码
Response::success($data, '操作成功', 201);
```

### 错误响应

```php
// 简单错误响应
Response::error('参数错误', 400);

// 带详细错误信息
Response::error('验证失败', 422, [
    'email' => ['邮箱格式不正确'],
    'password' => ['密码长度至少8位'],
]);
```

### JSON 响应

```php
// 自定义 JSON 响应
Response::json([
    'success' => true,
    'data' => $data,
    'meta' => $meta,
], 200);
```

### 分页响应

```php
Response::paginated(
    $items,      // 数据列表
    $total,      // 总记录数
    $page,       // 当前页码
    $perPage,    // 每页数量
    '获取成功'   // 消息
);
```

### 重定向

```php
// 临时重定向（302）
Response::redirect('/login.php');

// 永久重定向（301）
Response::redirect('/new-url', 301);
```

### HTML 响应

```php
Response::html('<html>...</html>', 200);
```

## 检测 API 请求

```php
if (Response::isApiRequest()) {
    // 返回 JSON
    Response::success($data);
} else {
    // 返回 HTML 页面
    include 'view.php';
}
```

## 使用示例

### 在 API 接口中使用

```php
<?php
require_once __DIR__ . '/lib/Response.php';
require_once __DIR__ . '/lib/db_connect.php';

// 验证请求方法
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    Response::error('仅支持 POST 请求', 405);
}

// 验证参数
$testId = $_POST['test_id'] ?? null;
if (!$testId) {
    Response::error('缺少 test_id 参数', 400);
}

// 处理业务逻辑
try {
    $stmt = $pdo->prepare("SELECT * FROM tests WHERE id = ?");
    $stmt->execute([$testId]);
    $test = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$test) {
        Response::error('测验不存在', 404);
    }
    
    Response::success($test, '获取成功');
} catch (Exception $e) {
    Response::error('服务器错误', 500);
}
```

### 在表单提交中使用

```php
<?php
require_once __DIR__ . '/lib/Response.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // 验证表单
    $errors = [];
    if (empty($_POST['email'])) {
        $errors['email'] = ['邮箱不能为空'];
    }
    
    if (!empty($errors)) {
        Response::error('表单验证失败', 422, $errors);
    }
    
    // 处理提交
    Response::success(null, '提交成功');
}
```

## 响应格式

### 成功响应格式

```json
{
    "success": true,
    "message": "操作成功",
    "data": {
        "id": 123,
        "name": "测试"
    }
}
```

### 错误响应格式

```json
{
    "success": false,
    "message": "验证失败",
    "errors": {
        "email": ["邮箱格式不正确"],
        "password": ["密码长度至少8位"]
    }
}
```

### 分页响应格式

```json
{
    "success": true,
    "message": "获取成功",
    "data": [...],
    "pagination": {
        "total": 100,
        "page": 1,
        "per_page": 20,
        "total_pages": 5
    }
}
```

