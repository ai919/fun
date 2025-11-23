<?php
/**
 * 广告位管理辅助类
 * 
 * 用于管理和显示网站广告位
 */
class AdHelper
{
    private static ?PDO $pdo = null;
    private static array $cache = [];
    private static int $cacheTime = 300; // 缓存5分钟

    /**
     * 初始化数据库连接
     */
    private static function initPdo(): void
    {
        if (self::$pdo === null) {
            require_once __DIR__ . '/db_connect.php';
            global $pdo;
            self::$pdo = $pdo;
        }
    }

    /**
     * 获取广告位内容
     * 
     * @param string $positionKey 广告位标识
     * @param string $currentPage 当前页面类型（home, test, result）
     * @return string|null 广告HTML代码，如果没有则返回null
     */
    public static function render(string $positionKey, string $currentPage = 'home'): ?string
    {
        // 检查缓存
        $cacheKey = "ad_{$positionKey}_{$currentPage}";
        if (isset(self::$cache[$cacheKey])) {
            $ad = self::$cache[$cacheKey];
            if ($ad === null || self::isAdValid($ad)) {
                return $ad ? self::formatAd($ad) : null;
            }
        }

        self::initPdo();

        // 查询启用的广告位
        $stmt = self::$pdo->prepare("
            SELECT * FROM ad_positions 
            WHERE position_key = ? 
            AND is_enabled = 1
            AND (display_pages IS NULL OR display_pages = '' OR FIND_IN_SET(?, display_pages) > 0)
            AND (start_date IS NULL OR start_date <= NOW())
            AND (end_date IS NULL OR end_date >= NOW())
            ORDER BY priority DESC, id DESC
            LIMIT 1
        ");
        $stmt->execute([$positionKey, $currentPage]);
        $ad = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$ad) {
            self::$cache[$cacheKey] = null;
            return null;
        }

        // 检查显示次数限制
        if ($ad['max_display_count'] > 0) {
            // 这里可以添加显示次数统计逻辑
            // 暂时跳过此检查
        }

        self::$cache[$cacheKey] = $ad;
        return self::formatAd($ad);
    }

    /**
     * 检查广告是否有效
     */
    private static function isAdValid(array $ad): bool
    {
        // 检查日期范围
        $now = date('Y-m-d H:i:s');
        if ($ad['start_date'] && $ad['start_date'] > $now) {
            return false;
        }
        if ($ad['end_date'] && $ad['end_date'] < $now) {
            return false;
        }
        return true;
    }

    /**
     * 格式化广告输出
     */
    private static function formatAd(array $ad): string
    {
        $html = '<div class="ad-container" data-ad-position="' . htmlspecialchars($ad['position_key'], ENT_QUOTES, 'UTF-8') . '">';

        switch ($ad['ad_type']) {
            case 'code':
                // 直接输出广告代码
                $html .= $ad['ad_code'] ?? '';
                break;

            case 'image':
                // 图片广告
                $link = $ad['link_url'] ?? '';
                $imageUrl = htmlspecialchars($ad['image_url'] ?? '', ENT_QUOTES, 'UTF-8');
                $altText = htmlspecialchars($ad['alt_text'] ?? '广告', ENT_QUOTES, 'UTF-8');
                
                if ($link) {
                    $html .= '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="nofollow noopener">';
                }
                $html .= '<img src="' . $imageUrl . '" alt="' . $altText . '" class="ad-image" />';
                if ($link) {
                    $html .= '</a>';
                }
                break;

            case 'text':
                // 文字广告
                $link = $ad['link_url'] ?? '';
                $text = htmlspecialchars($ad['ad_code'] ?? '', ENT_QUOTES, 'UTF-8');
                
                if ($link) {
                    $html .= '<a href="' . htmlspecialchars($link, ENT_QUOTES, 'UTF-8') . '" target="_blank" rel="nofollow noopener" class="ad-text-link">';
                }
                $html .= '<span class="ad-text">' . $text . '</span>';
                if ($link) {
                    $html .= '</a>';
                }
                break;
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * 获取所有广告位列表
     * 
     * @return array
     */
    public static function getAllPositions(): array
    {
        self::initPdo();
        $stmt = self::$pdo->query("
            SELECT * FROM ad_positions 
            ORDER BY priority DESC, position_key ASC
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * 获取单个广告位配置
     * 
     * @param int $id 广告位ID
     * @return array|null
     */
    public static function getPosition(int $id): ?array
    {
        self::initPdo();
        $stmt = self::$pdo->prepare("SELECT * FROM ad_positions WHERE id = ? LIMIT 1");
        $stmt->execute([$id]);
        return $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
    }

    /**
     * 保存广告位配置
     * 
     * @param array $data 广告位数据
     * @return bool
     */
    public static function savePosition(array $data): bool
    {
        self::initPdo();
        
        $id = $data['id'] ?? null;
        unset($data['id']);

        if ($id) {
            // 更新
            $fields = [];
            $values = [];
            foreach ($data as $key => $value) {
                $fields[] = "`{$key}` = ?";
                $values[] = $value;
            }
            $values[] = $id;
            $sql = "UPDATE ad_positions SET " . implode(', ', $fields) . " WHERE id = ?";
            $stmt = self::$pdo->prepare($sql);
            return $stmt->execute($values);
        } else {
            // 插入
            $fields = array_keys($data);
            $placeholders = array_fill(0, count($fields), '?');
            $sql = "INSERT INTO ad_positions (" . implode(', ', $fields) . ") VALUES (" . implode(', ', $placeholders) . ")";
            $stmt = self::$pdo->prepare($sql);
            return $stmt->execute(array_values($data));
        }
    }

    /**
     * 删除广告位
     * 
     * @param int $id 广告位ID
     * @return bool
     */
    public static function deletePosition(int $id): bool
    {
        self::initPdo();
        $stmt = self::$pdo->prepare("DELETE FROM ad_positions WHERE id = ?");
        return $stmt->execute([$id]);
    }

    /**
     * 清除缓存
     */
    public static function clearCache(): void
    {
        self::$cache = [];
    }
}

