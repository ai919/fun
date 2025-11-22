<?php
/**
 * 文件缓存工具类
 * 用于缓存不经常变化的数据，减少数据库查询
 */
class CacheHelper
{
    private static string $cacheDir = __DIR__ . '/../cache';
    private static int $defaultTtl = 300; // 默认缓存时间 5 分钟

    /**
     * 初始化缓存目录
     */
    private static function initCacheDir(): void
    {
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
    }

    /**
     * 获取缓存文件路径
     */
    private static function getCachePath(string $key): string
    {
        self::initCacheDir();
        $safeKey = preg_replace('/[^a-zA-Z0-9_-]/', '_', $key);
        return self::$cacheDir . '/' . $safeKey . '.cache';
    }

    /**
     * 获取缓存
     * @param string $key 缓存键
     * @param int|null $ttl 缓存有效期（秒），null 使用默认值
     * @return mixed|null 缓存数据，不存在或过期返回 null
     */
    public static function get(string $key, ?int $ttl = null): mixed
    {
        $path = self::getCachePath($key);
        
        if (!file_exists($path)) {
            return null;
        }

        $ttl = $ttl ?? self::$defaultTtl;
        $fileTime = filemtime($path);
        
        // 检查是否过期
        if (time() - $fileTime > $ttl) {
            @unlink($path);
            return null;
        }

        $content = @file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $data = @unserialize($content);
        if ($data === false) {
            @unlink($path);
            return null;
        }

        return $data;
    }

    /**
     * 设置缓存
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @return bool 是否成功
     */
    public static function set(string $key, mixed $value): bool
    {
        $path = self::getCachePath($key);
        self::initCacheDir();
        
        $content = serialize($value);
        return @file_put_contents($path, $content, LOCK_EX) !== false;
    }

    /**
     * 删除缓存
     * @param string $key 缓存键
     * @return bool 是否成功
     */
    public static function delete(string $key): bool
    {
        $path = self::getCachePath($key);
        if (file_exists($path)) {
            return @unlink($path);
        }
        return true;
    }

    /**
     * 清空所有缓存
     * @return bool 是否成功
     */
    public static function clear(): bool
    {
        self::initCacheDir();
        $files = glob(self::$cacheDir . '/*.cache');
        $success = true;
        foreach ($files as $file) {
            if (!@unlink($file)) {
                $success = false;
            }
        }
        return $success;
    }

    /**
     * 删除与测验相关的缓存
     * @param int|null $testId 测验ID，null 则删除所有测验相关缓存
     */
    public static function clearTestCache(?int $testId = null): void
    {
        if ($testId !== null) {
            // 删除特定测验的缓存
            self::delete("test_{$testId}");
            self::delete("test_slug_{$testId}");
        }
        // 删除测验列表缓存
        self::delete('published_tests_list');
        self::delete('test_slug_map');
    }

    /**
     * 检查缓存是否存在且未过期
     * @param string $key 缓存键
     * @param int|null $ttl 缓存有效期（秒）
     * @return bool
     */
    public static function exists(string $key, ?int $ttl = null): bool
    {
        $path = self::getCachePath($key);
        
        if (!file_exists($path)) {
            return false;
        }

        $ttl = $ttl ?? self::$defaultTtl;
        $fileTime = filemtime($path);
        
        return (time() - $fileTime) <= $ttl;
    }
}

