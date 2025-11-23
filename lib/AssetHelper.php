<?php
/**
 * 资源版本控制和 CDN 集成
 * 
 * 提供静态资源的版本控制和 CDN 支持
 */
class AssetHelper
{
    private static string $versionFile = __DIR__ . '/../cache/asset_version.json';
    private static ?string $cdnBaseUrl = null;
    private static ?string $version = null;
    private static bool $useCdn = false;
    
    /**
     * 初始化
     */
    public static function init(): void
    {
        // 从环境变量或配置读取 CDN 设置
        self::$cdnBaseUrl = $_ENV['CDN_BASE_URL'] ?? null;
        self::$useCdn = !empty(self::$cdnBaseUrl);
        
        // 加载版本号
        self::loadVersion();
    }
    
    /**
     * 加载资源版本号
     */
    private static function loadVersion(): void
    {
        if (file_exists(self::$versionFile)) {
            $data = @json_decode(file_get_contents(self::$versionFile), true);
            if (is_array($data) && isset($data['version'])) {
                self::$version = $data['version'];
            }
        }
        
        // 如果没有版本号，生成一个
        if (self::$version === null) {
            self::$version = date('YmdHis');
            self::saveVersion();
        }
    }
    
    /**
     * 保存版本号
     */
    private static function saveVersion(): void
    {
        $dir = dirname(self::$versionFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        @file_put_contents(
            self::$versionFile,
            json_encode(['version' => self::$version], JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
    
    /**
     * 获取资源 URL
     * 
     * @param string $path 资源路径（相对于 assets 目录）
     * @param bool $useVersion 是否添加版本号
     * @return string 完整的资源 URL
     */
    public static function url(string $path, bool $useVersion = true): string
    {
        // 确保路径以 / 开头
        if (substr($path, 0, 1) !== '/') {
            $path = '/' . $path;
        }
        
        // 如果是 assets 路径，确保正确
        if (substr($path, 0, 8) !== '/assets/') {
            $path = '/assets' . $path;
        }
        
        // 构建基础 URL
        $baseUrl = self::$useCdn ? rtrim(self::$cdnBaseUrl, '/') : '';
        $url = $baseUrl . $path;
        
        // 添加版本号
        if ($useVersion) {
            $separator = strpos($url, '?') !== false ? '&' : '?';
            $url .= $separator . 'v=' . self::$version;
        }
        
        return $url;
    }
    
    /**
     * 生成 CSS 链接标签
     * 
     * @param string $path CSS 文件路径
     * @param array $attributes 额外的属性
     * @return string HTML 标签
     */
    public static function css(string $path, array $attributes = []): string
    {
        $url = self::url($path);
        $attrs = [];
        
        foreach ($attributes as $key => $value) {
            if (is_numeric($key)) {
                $attrs[] = htmlspecialchars($value);
            } else {
                $attrs[] = htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
            }
        }
        
        $attrString = $attrs ? ' ' . implode(' ', $attrs) : '';
        return '<link rel="stylesheet" href="' . htmlspecialchars($url) . '"' . $attrString . '>';
    }
    
    /**
     * 生成 JavaScript 脚本标签
     * 
     * @param string $path JS 文件路径
     * @param array $attributes 额外的属性
     * @return string HTML 标签
     */
    public static function js(string $path, array $attributes = []): string
    {
        $url = self::url($path);
        $attrs = [];
        
        foreach ($attributes as $key => $value) {
            if (is_numeric($key)) {
                $attrs[] = htmlspecialchars($value);
            } else {
                $attrs[] = htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
            }
        }
        
        $attrString = $attrs ? ' ' . implode(' ', $attrs) : '';
        return '<script src="' . htmlspecialchars($url) . '"' . $attrString . '></script>';
    }
    
    /**
     * 生成图片标签
     * 
     * @param string $path 图片路径
     * @param string $alt 替代文本
     * @param array $attributes 额外的属性
     * @return string HTML 标签
     */
    public static function img(string $path, string $alt = '', array $attributes = []): string
    {
        $url = self::url($path);
        $attrs = ['alt' => htmlspecialchars($alt)];
        
        foreach ($attributes as $key => $value) {
            $attrs[$key] = $value;
        }
        
        $attrStrings = [];
        foreach ($attrs as $key => $value) {
            if (is_numeric($key)) {
                $attrStrings[] = htmlspecialchars($value);
            } else {
                $attrStrings[] = htmlspecialchars($key) . '="' . htmlspecialchars($value) . '"';
            }
        }
        
        return '<img src="' . htmlspecialchars($url) . '" ' . implode(' ', $attrStrings) . '>';
    }
    
    /**
     * 更新版本号（用于部署时清除缓存）
     * 
     * @param string|null $newVersion 新版本号，null 则自动生成
     * @return string 新版本号
     */
    public static function updateVersion(?string $newVersion = null): string
    {
        self::$version = $newVersion ?: date('YmdHis');
        self::saveVersion();
        return self::$version;
    }
    
    /**
     * 获取当前版本号
     * 
     * @return string
     */
    public static function getVersion(): string
    {
        if (self::$version === null) {
            self::init();
        }
        return self::$version;
    }
    
    /**
     * 设置 CDN 基础 URL
     * 
     * @param string|null $url CDN URL，null 则禁用 CDN
     */
    public static function setCdnBaseUrl(?string $url): void
    {
        self::$cdnBaseUrl = $url;
        self::$useCdn = !empty($url);
    }
    
    /**
     * 检查是否使用 CDN
     * 
     * @return bool
     */
    public static function isUsingCdn(): bool
    {
        return self::$useCdn;
    }
}

// 自动初始化
AssetHelper::init();

