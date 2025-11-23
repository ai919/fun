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
     * 根据模式删除缓存
     * @param string $pattern 模式，支持通配符 * 和 ?
     * @return int 删除的缓存数量
     */
    public static function deletePattern(string $pattern): int
    {
        self::initCacheDir();
        $files = glob(self::$cacheDir . '/*.cache');
        if ($files === false) {
            return 0;
        }
        
        $count = 0;
        
        // 将模式转换为安全键模式（特殊字符会被替换为下划线，但保留 * 和 ?）
        $safePattern = preg_replace('/[^a-zA-Z0-9_*-?]/', '_', $pattern);
        
        // 将模式转换为正则表达式
        // 先将 * 和 ? 替换为占位符，转义其他字符，再替换回来
        $tempPattern = str_replace(['*', '?'], ['__WILDCARD__', '__SINGLE__'], $safePattern);
        $escapedPattern = preg_quote($tempPattern, '/');
        $regex = '/^' . str_replace(
            ['__WILDCARD__', '__SINGLE__'],
            ['.*', '.'],
            $escapedPattern
        ) . '$/';
        
        foreach ($files as $file) {
            // 从文件路径中提取缓存文件名（不含扩展名）
            $filename = basename($file, '.cache');
            // 匹配文件名
            if (preg_match($regex, $filename)) {
                if (@unlink($file)) {
                    $count++;
                }
            }
        }
        
        return $count;
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
     * @param string|null $slug 测验的 slug（可选），如果提供则精确删除 slug 相关缓存
     */
    public static function clearTestCache(?int $testId = null, ?string $slug = null): void
    {
        if ($testId !== null) {
            // 删除特定测验的缓存
            self::delete("test_{$testId}");
            self::delete("test_slug_{$testId}");
            // 删除前台 test.php 使用的完整测验数据缓存（包含题目和选项）
            self::delete("test_full_{$testId}");
            // 删除测验人数缓存
            self::delete("test_play_count_{$testId}");
            
            // 删除 slug 到 id 的映射缓存（test.php 中使用）
            if ($slug !== null && $slug !== '') {
                // 如果提供了 slug，精确删除该 slug 的缓存
                self::delete('test_slug_' . md5($slug));
                self::delete('test_slug_id_' . md5($slug));
            } else {
                // 如果没有提供 slug，删除所有 test_slug_id_* 缓存（影响较小，因为缓存时间只有5分钟）
                self::deletePattern("test_slug_id_*");
            }
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

