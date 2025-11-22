<?php
/**
 * 统一响应处理类
 * 
 * 提供统一的 API 响应格式，支持 JSON 和 HTML 两种模式
 */
class Response
{
    /**
     * 成功响应（JSON）
     * 
     * @param mixed $data 响应数据
     * @param string $message 成功消息
     * @param int $code HTTP 状态码
     * @return void
     */
    public static function success($data = null, string $message = '操作成功', int $code = 200)
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
        ], $code);
    }

    /**
     * 错误响应（JSON）
     * 
     * @param string $message 错误消息
     * @param int $code HTTP 状态码
     * @param mixed $errors 详细错误信息（可选）
     * @return void
     */
    public static function error(string $message, int $code = 400, $errors = null)
    {
        $response = [
            'success' => false,
            'message' => $message,
        ];

        if ($errors !== null) {
            $response['errors'] = $errors;
        }

        self::json($response, $code);
    }

    /**
     * 返回 JSON 响应
     * 
     * @param array $data 响应数据
     * @param int $code HTTP 状态码
     * @return void
     */
    public static function json(array $data, int $code = 200)
    {
        http_response_code($code);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /**
     * 返回 HTML 响应
     * 
     * @param string $html HTML 内容
     * @param int $code HTTP 状态码
     * @return void
     */
    public static function html(string $html, int $code = 200)
    {
        http_response_code($code);
        header('Content-Type: text/html; charset=utf-8');
        echo $html;
        exit;
    }

    /**
     * 重定向响应
     * 
     * @param string $url 目标 URL
     * @param int $code HTTP 状态码（301 永久重定向，302 临时重定向）
     * @return void
     */
    public static function redirect(string $url, int $code = 302)
    {
        http_response_code($code);
        header('Location: ' . $url);
        exit;
    }

    /**
     * 返回分页数据（JSON）
     * 
     * @param array $data 数据列表
     * @param int $total 总记录数
     * @param int $page 当前页码
     * @param int $perPage 每页数量
     * @param string $message 消息
     * @return void
     */
    public static function paginated(array $data, int $total, int $page, int $perPage, string $message = '获取成功')
    {
        self::json([
            'success' => true,
            'message' => $message,
            'data' => $data,
            'pagination' => [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'total_pages' => ceil($total / $perPage),
            ],
        ]);
    }

    /**
     * 验证是否为 API 请求
     * 
     * @return bool
     */
    public static function isApiRequest(): bool
    {
        // 检查 Accept 头
        $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
        if (strpos($accept, 'application/json') !== false) {
            return true;
        }

        // 检查 Content-Type 头
        $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
        if (strpos($contentType, 'application/json') !== false) {
            return true;
        }

        // 检查请求路径
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH);
        if ($path && (strpos($path, '/api/') === 0 || strpos($path, '.json') !== false)) {
            return true;
        }

        return false;
    }
}

