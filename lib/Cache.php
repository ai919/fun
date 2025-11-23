<?php
/**
 * 多层级缓存系统
 * 
 * 支持三级缓存：
 * - L1: APCu (内存缓存，最快)
 * - L2: 文件缓存 (持久化)
 * - L3: Redis (可选，分布式缓存)
 * 
 * 支持标签化缓存失效策略
 */
class Cache
{
    private static ?Cache $instance = null;
    private static bool $apcuEnabled = false;
    private static bool $redisEnabled = false;
    private static ?Redis $redis = null;
    private static string $cacheDir = __DIR__ . '/../cache';
    private static int $defaultTtl = 300; // 默认 5 分钟
    
    // 标签映射：标签 => [缓存键数组]
    private static array $tagMap = [];
    private static string $tagMapFile = '';
    
    private function __construct()
    {
        // 检查 APCu 是否可用
        self::$apcuEnabled = function_exists('apcu_fetch') && ini_get('apc.enabled');
        
        // 检查 Redis 是否可用
        if (class_exists('Redis')) {
            try {
                self::$redis = new Redis();
                // 尝试连接 Redis（默认配置）
                $redisHost = $_ENV['REDIS_HOST'] ?? '127.0.0.1';
                $redisPort = (int)($_ENV['REDIS_PORT'] ?? 6379);
                $redisPassword = $_ENV['REDIS_PASSWORD'] ?? null;
                
                if (self::$redis->connect($redisHost, $redisPort, 1)) {
                    if ($redisPassword !== null) {
                        self::$redis->auth($redisPassword);
                    }
                    self::$redisEnabled = true;
                } else {
                    self::$redis = null;
                }
            } catch (Exception $e) {
                self::$redis = null;
                self::$redisEnabled = false;
            }
        }
        
        // 初始化缓存目录
        if (!is_dir(self::$cacheDir)) {
            mkdir(self::$cacheDir, 0755, true);
        }
        
        // 标签映射文件
        self::$tagMapFile = self::$cacheDir . '/tag_map.json';
        self::loadTagMap();
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 加载标签映射
     */
    private static function loadTagMap(): void
    {
        if (file_exists(self::$tagMapFile)) {
            $content = @file_get_contents(self::$tagMapFile);
            if ($content !== false) {
                $data = @json_decode($content, true);
                if (is_array($data)) {
                    self::$tagMap = $data;
                }
            }
        }
    }
    
    /**
     * 保存标签映射
     */
    private static function saveTagMap(): void
    {
        @file_put_contents(
            self::$tagMapFile,
            json_encode(self::$tagMap, JSON_UNESCAPED_UNICODE),
            LOCK_EX
        );
    }
    
    /**
     * 添加键到标签
     */
    private static function addKeyToTag(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            if (!isset(self::$tagMap[$tag])) {
                self::$tagMap[$tag] = [];
            }
            if (!in_array($key, self::$tagMap[$tag], true)) {
                self::$tagMap[$tag][] = $key;
            }
        }
        self::saveTagMap();
    }
    
    /**
     * 从标签移除键
     */
    private static function removeKeyFromTag(string $key, array $tags): void
    {
        foreach ($tags as $tag) {
            if (isset(self::$tagMap[$tag])) {
                self::$tagMap[$tag] = array_values(
                    array_filter(self::$tagMap[$tag], fn($k) => $k !== $key)
                );
                if (empty(self::$tagMap[$tag])) {
                    unset(self::$tagMap[$tag]);
                }
            }
        }
        self::saveTagMap();
    }
    
    /**
     * 获取缓存（多层级查找）
     * 
     * @param string $key 缓存键
     * @param int|null $ttl 缓存有效期（秒），null 使用默认值
     * @return mixed|null 缓存数据，不存在或过期返回 null
     */
    public static function get(string $key, ?int $ttl = null): mixed
    {
        $instance = self::getInstance();
        $ttl = $ttl ?? self::$defaultTtl;
        
        // L1: 尝试从 APCu 获取
        if (self::$apcuEnabled) {
            $value = @apcu_fetch($key);
            if ($value !== false) {
                return $value;
            }
        }
        
        // L2: 尝试从文件缓存获取
        $filePath = self::$cacheDir . '/' . md5($key) . '.cache';
        if (file_exists($filePath)) {
            $fileTime = filemtime($filePath);
            if (time() - $fileTime <= $ttl) {
                $content = @file_get_contents($filePath);
                if ($content !== false) {
                    $data = @unserialize($content);
                    if ($data !== false) {
                        // 回写到 L1
                        if (self::$apcuEnabled) {
                            @apcu_store($key, $data, $ttl);
                        }
                        return $data;
                    }
                }
            } else {
                @unlink($filePath);
            }
        }
        
        // L3: 尝试从 Redis 获取
        if (self::$redisEnabled && self::$redis !== null) {
            try {
                $value = self::$redis->get($key);
                if ($value !== false) {
                    $data = @unserialize($value);
                    if ($data !== false) {
                        // 回写到 L1 和 L2
                        if (self::$apcuEnabled) {
                            @apcu_store($key, $data, $ttl);
                        }
                        @file_put_contents($filePath, serialize($data), LOCK_EX);
                        return $data;
                    }
                }
            } catch (Exception $e) {
                // Redis 错误，忽略
            }
        }
        
        return null;
    }
    
    /**
     * 设置缓存（写入所有可用层级）
     * 
     * @param string $key 缓存键
     * @param mixed $value 缓存值
     * @param int|null $ttl 缓存有效期（秒），null 使用默认值
     * @param array $tags 标签数组，用于批量失效
     * @return bool 是否成功
     */
    public static function set(string $key, mixed $value, ?int $ttl = null, array $tags = []): bool
    {
        $instance = self::getInstance();
        $ttl = $ttl ?? self::$defaultTtl;
        $success = true;
        
        // L1: 写入 APCu
        if (self::$apcuEnabled) {
            if (!@apcu_store($key, $value, $ttl)) {
                $success = false;
            }
        }
        
        // L2: 写入文件缓存
        $filePath = self::$cacheDir . '/' . md5($key) . '.cache';
        if (@file_put_contents($filePath, serialize($value), LOCK_EX) === false) {
            $success = false;
        }
        
        // L3: 写入 Redis
        if (self::$redisEnabled && self::$redis !== null) {
            try {
                if (!self::$redis->setex($key, $ttl, serialize($value))) {
                    $success = false;
                }
            } catch (Exception $e) {
                $success = false;
            }
        }
        
        // 记录标签
        if (!empty($tags)) {
            self::addKeyToTag($key, $tags);
        }
        
        return $success;
    }
    
    /**
     * 删除缓存（从所有层级删除）
     * 
     * @param string $key 缓存键
     * @return bool 是否成功
     */
    public static function delete(string $key): bool
    {
        $instance = self::getInstance();
        $success = true;
        
        // L1: 从 APCu 删除
        if (self::$apcuEnabled) {
            @apcu_delete($key);
        }
        
        // L2: 从文件缓存删除
        $filePath = self::$cacheDir . '/' . md5($key) . '.cache';
        if (file_exists($filePath)) {
            if (!@unlink($filePath)) {
                $success = false;
            }
        }
        
        // L3: 从 Redis 删除
        if (self::$redisEnabled && self::$redis !== null) {
            try {
                self::$redis->del($key);
            } catch (Exception $e) {
                $success = false;
            }
        }
        
        // 从标签映射中移除
        foreach (self::$tagMap as $tag => $keys) {
            if (in_array($key, $keys, true)) {
                self::removeKeyFromTag($key, [$tag]);
            }
        }
        
        return $success;
    }
    
    /**
     * 根据标签批量删除缓存
     * 
     * @param array|string $tags 标签或标签数组
     * @return int 删除的缓存数量
     */
    public static function deleteByTag(array|string $tags): int
    {
        $instance = self::getInstance();
        $tags = is_array($tags) ? $tags : [$tags];
        $count = 0;
        
        foreach ($tags as $tag) {
            if (isset(self::$tagMap[$tag])) {
                foreach (self::$tagMap[$tag] as $key) {
                    if (self::delete($key)) {
                        $count++;
                    }
                }
                unset(self::$tagMap[$tag]);
            }
        }
        
        self::saveTagMap();
        return $count;
    }
    
    /**
     * 清空所有缓存
     * 
     * @return bool 是否成功
     */
    public static function clear(): bool
    {
        $instance = self::getInstance();
        $success = true;
        
        // L1: 清空 APCu
        if (self::$apcuEnabled) {
            @apcu_clear_cache();
        }
        
        // L2: 清空文件缓存
        $files = glob(self::$cacheDir . '/*.cache');
        foreach ($files as $file) {
            if (!@unlink($file)) {
                $success = false;
            }
        }
        
        // L3: 清空 Redis
        if (self::$redisEnabled && self::$redis !== null) {
            try {
                self::$redis->flushDB();
            } catch (Exception $e) {
                $success = false;
            }
        }
        
        // 清空标签映射
        self::$tagMap = [];
        self::saveTagMap();
        
        return $success;
    }
    
    /**
     * 检查缓存是否存在
     * 
     * @param string $key 缓存键
     * @param int|null $ttl 缓存有效期（秒）
     * @return bool
     */
    public static function exists(string $key, ?int $ttl = null): bool
    {
        return self::get($key, $ttl) !== null;
    }
    
    /**
     * 获取缓存统计信息
     * 
     * @return array
     */
    public static function getStats(): array
    {
        $instance = self::getInstance();
        $stats = [
            'apcu_enabled' => self::$apcuEnabled,
            'redis_enabled' => self::$redisEnabled,
            'file_count' => 0,
            'tag_count' => count(self::$tagMap),
        ];
        
        // 统计文件缓存数量
        $files = glob(self::$cacheDir . '/*.cache');
        if ($files !== false) {
            $stats['file_count'] = count($files);
        }
        
        // APCu 统计
        if (self::$apcuEnabled) {
            $stats['apcu_info'] = @apcu_cache_info();
        }
        
        // Redis 统计
        if (self::$redisEnabled && self::$redis !== null) {
            try {
                $stats['redis_info'] = self::$redis->info();
            } catch (Exception $e) {
                // 忽略错误
            }
        }
        
        return $stats;
    }
}
