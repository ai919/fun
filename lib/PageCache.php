<?php
/**
 * 页面缓存
 * 
 * 实现页面级别的缓存，优化首屏加载时间
 */

class PageCache
{
    private static $cacheDir = null;
    private static $enabled = true;
    private static $defaultTtl = 300; // 5 分钟

    /**
     * 初始化
     */
    private static function init()
    {
        if (self::$cacheDir !== null) {
            return;
        }

        $configFile = __DIR__ . '/../config/app.php';
        $config = [];
        if (file_exists($configFile)) {
            $config = require $configFile;
        }

        self::$cacheDir = $config['cache']['page_dir'] ?? __DIR__ . '/../cache/pages';
        self::$enabled = $config['cache']['page_enabled'] ?? true;
        self::$defaultTtl = $config['cache']['page_ttl'] ?? 300;

        // 确保缓存目录存在
        if (!is_dir(self::$cacheDir)) {
            @mkdir(self::$cacheDir, 0755, true);
        }
    }

    /**
     * 生成缓存键
     */
    private static function getCacheKey(string $url, array $params = []): string
    {
        $key = $url;
        if (!empty($params)) {
            ksort($params);
            $key .= '?' . http_build_query($params);
        }
        return md5($key);
    }

    /**
     * 获取缓存
     * 
     * @param string $url 页面 URL
     * @param array $params 查询参数
     * @return string|null 缓存内容，如果不存在或过期则返回 null
     */
    public static function get(string $url, array $params = []): ?string
    {
        if (!self::$enabled) {
            return null;
        }

        self::init();

        $cacheKey = self::getCacheKey($url, $params);
        $cacheFile = self::$cacheDir . '/' . $cacheKey . '.html';
        $metaFile = self::$cacheDir . '/' . $cacheKey . '.meta';

        // 检查缓存文件是否存在
        if (!file_exists($cacheFile) || !file_exists($metaFile)) {
            return null;
        }

        // 读取元数据
        $meta = @json_decode(file_get_contents($metaFile), true);
        if (!$meta) {
            return null;
        }

        // 检查是否过期
        if (time() > $meta['expires_at']) {
            @unlink($cacheFile);
            @unlink($metaFile);
            return null;
        }

        // 返回缓存内容
        return file_get_contents($cacheFile);
    }

    /**
     * 设置缓存
     * 
     * @param string $url 页面 URL
     * @param array $params 查询参数
     * @param string $content 页面内容
     * @param int $ttl 缓存时间（秒）
     */
    public static function set(string $url, array $params, string $content, int $ttl = null)
    {
        if (!self::$enabled) {
            return;
        }

        self::init();

        $ttl = $ttl ?? self::$defaultTtl;
        $cacheKey = self::getCacheKey($url, $params);
        $cacheFile = self::$cacheDir . '/' . $cacheKey . '.html';
        $metaFile = self::$cacheDir . '/' . $cacheKey . '.meta';

        // 保存缓存内容
        @file_put_contents($cacheFile, $content, LOCK_EX);

        // 保存元数据
        $meta = [
            'url' => $url,
            'params' => $params,
            'created_at' => time(),
            'expires_at' => time() + $ttl,
            'ttl' => $ttl,
        ];
        @file_put_contents($metaFile, json_encode($meta), LOCK_EX);
    }

    /**
     * 删除缓存
     */
    public static function delete(string $url, array $params = [])
    {
        self::init();

        $cacheKey = self::getCacheKey($url, $params);
        $cacheFile = self::$cacheDir . '/' . $cacheKey . '.html';
        $metaFile = self::$cacheDir . '/' . $cacheKey . '.meta';

        @unlink($cacheFile);
        @unlink($metaFile);
    }

    /**
     * 清空所有页面缓存
     */
    public static function clear()
    {
        self::init();

        $files = glob(self::$cacheDir . '/*.{html,meta}', GLOB_BRACE);
        foreach ($files as $file) {
            @unlink($file);
        }
    }

    /**
     * 按标签删除缓存
     */
    public static function deleteByTag(string $tag)
    {
        self::init();

        // 查找所有元数据文件
        $metaFiles = glob(self::$cacheDir . '/*.meta');
        foreach ($metaFiles as $metaFile) {
            $meta = @json_decode(file_get_contents($metaFile), true);
            if ($meta && isset($meta['tags']) && in_array($tag, $meta['tags'])) {
                $cacheKey = basename($metaFile, '.meta');
                @unlink(self::$cacheDir . '/' . $cacheKey . '.html');
                @unlink($metaFile);
            }
        }
    }

    /**
     * 按标签清除缓存（别名方法）
     */
    public static function clearByTag(string $tag)
    {
        return self::deleteByTag($tag);
    }

    /**
     * 启用/禁用缓存
     */
    public static function setEnabled(bool $enabled)
    {
        self::$enabled = $enabled;
    }

    /**
     * 获取缓存统计
     */
    public static function getStats(): array
    {
        self::init();

        $stats = [
            'total_files' => 0,
            'total_size' => 0,
            'expired_files' => 0,
            'valid_files' => 0,
        ];

        $metaFiles = glob(self::$cacheDir . '/*.meta');
        foreach ($metaFiles as $metaFile) {
            $meta = @json_decode(file_get_contents($metaFile), true);
            if (!$meta) {
                continue;
            }

            $cacheKey = basename($metaFile, '.meta');
            $cacheFile = self::$cacheDir . '/' . $cacheKey . '.html';

            if (file_exists($cacheFile)) {
                $stats['total_files']++;
                $stats['total_size'] += filesize($cacheFile);

                if (time() > $meta['expires_at']) {
                    $stats['expired_files']++;
                } else {
                    $stats['valid_files']++;
                }
            }
        }

        return $stats;
    }
}

