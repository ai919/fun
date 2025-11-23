<?php
/**
 * SEO 内容优化器
 * 
 * 提供内容优化建议和工具
 */

class SEOContentOptimizer
{
    /**
     * 优化标题
     * 
     * @param string $title 原始标题
     * @param int $maxLength 最大长度（默认 60）
     * @return array ['optimized' => string, 'suggestions' => array]
     */
    public static function optimizeTitle(string $title, int $maxLength = 60): array
    {
        $original = $title;
        $suggestions = [];

        // 移除多余空格
        $title = trim(preg_replace('/\s+/', ' ', $title));

        // 检查长度
        $length = mb_strlen($title);
        if ($length > $maxLength) {
            $title = mb_substr($title, 0, $maxLength - 3) . '...';
            $suggestions[] = "标题过长（{$length} 字符），建议控制在 {$maxLength} 字符以内";
        } elseif ($length < 30) {
            $suggestions[] = "标题较短（{$length} 字符），建议至少 30 字符以包含更多关键词";
        }

        // 检查是否包含关键词
        if (mb_strlen($title) < 10) {
            $suggestions[] = "标题过短，建议包含更多描述性关键词";
        }

        return [
            'optimized' => $title,
            'original' => $original,
            'length' => mb_strlen($title),
            'suggestions' => $suggestions,
        ];
    }

    /**
     * 优化描述
     * 
     * @param string $description 原始描述
     * @param int $maxLength 最大长度（默认 160）
     * @return array
     */
    public static function optimizeDescription(string $description, int $maxLength = 160): array
    {
        $original = $description;
        $suggestions = [];

        // 移除 HTML 标签
        $description = strip_tags($description);
        // 移除多余空格和换行
        $description = trim(preg_replace('/\s+/', ' ', $description));

        // 检查长度
        $length = mb_strlen($description);
        if ($length > $maxLength) {
            $description = mb_substr($description, 0, $maxLength - 3) . '...';
            $suggestions[] = "描述过长（{$length} 字符），建议控制在 {$maxLength} 字符以内";
        } elseif ($length < 120) {
            $suggestions[] = "描述较短（{$length} 字符），建议至少 120 字符以提供更多信息";
        }

        // 检查是否包含关键词
        if (mb_strlen($description) < 50) {
            $suggestions[] = "描述过短，建议包含更多描述性内容";
        }

        return [
            'optimized' => $description,
            'original' => $original,
            'length' => mb_strlen($description),
            'suggestions' => $suggestions,
        ];
    }

    /**
     * 生成内部链接建议
     * 
     * @param array $currentTest 当前测验数据
     * @param array $allTests 所有测验数据
     * @param int $limit 建议数量
     * @return array
     */
    public static function suggestInternalLinks(array $currentTest, array $allTests, int $limit = 5): array
    {
        $suggestions = [];
        $currentTitle = $currentTest['title'] ?? '';
        $currentDescription = $currentTest['description'] ?? '';
        $currentId = $currentTest['id'] ?? 0;

        // 提取关键词
        $keywords = self::extractKeywords($currentTitle . ' ' . $currentDescription);

        foreach ($allTests as $test) {
            if ($test['id'] == $currentId) {
                continue;
            }

            $testTitle = $test['title'] ?? '';
            $testDescription = $test['description'] ?? '';
            $testText = $testTitle . ' ' . $testDescription;

            // 计算相关性分数
            $score = 0;
            foreach ($keywords as $keyword) {
                if (stripos($testText, $keyword) !== false) {
                    $score += mb_strlen($keyword);
                }
            }

            if ($score > 0) {
                $suggestions[] = [
                    'test' => $test,
                    'score' => $score,
                    'relevance' => min(100, ($score / 10) * 100),
                ];
            }
        }

        // 按分数排序
        usort($suggestions, function($a, $b) {
            return $b['score'] - $a['score'];
        });

        return array_slice($suggestions, 0, $limit);
    }

    /**
     * 提取关键词
     */
    private static function extractKeywords(string $text): array
    {
        // 移除标点符号
        $text = preg_replace('/[^\p{L}\p{N}\s]/u', ' ', $text);
        // 转换为小写
        $text = mb_strtolower($text);
        // 分词（简单实现，按空格分割）
        $words = preg_split('/\s+/', $text);
        
        // 过滤停用词和短词
        $stopWords = ['的', '了', '在', '是', '我', '有', '和', '就', '不', '人', '都', '一', '一个', '上', '也', '很', '到', '说', '要', '去', '你', '会', '着', '没有', '看', '好', '自己', '这'];
        $keywords = [];
        
        foreach ($words as $word) {
            $word = trim($word);
            if (mb_strlen($word) >= 2 && !in_array($word, $stopWords)) {
                $keywords[] = $word;
            }
        }

        // 返回最常见的 10 个关键词
        $wordCounts = array_count_values($keywords);
        arsort($wordCounts);
        return array_slice(array_keys($wordCounts), 0, 10);
    }

    /**
     * 生成内容优化报告
     */
    public static function generateReport(array $test): array
    {
        $report = [
            'title' => self::optimizeTitle($test['title'] ?? ''),
            'description' => self::optimizeDescription($test['description'] ?? ''),
            'score' => 0,
            'issues' => [],
            'suggestions' => [],
        ];

        // 计算 SEO 分数
        $score = 100;

        // 标题检查
        $titleLength = mb_strlen($test['title'] ?? '');
        if ($titleLength < 30 || $titleLength > 60) {
            $score -= 20;
            $report['issues'][] = '标题长度不在推荐范围内（30-60 字符）';
        }

        // 描述检查
        $descLength = mb_strlen(strip_tags($test['description'] ?? ''));
        if ($descLength < 120 || $descLength > 160) {
            $score -= 20;
            $report['issues'][] = '描述长度不在推荐范围内（120-160 字符）';
        }

        // 检查是否有封面图片
        if (empty($test['cover_image'])) {
            $score -= 10;
            $report['issues'][] = '缺少封面图片（影响社交媒体分享）';
        }

        // 检查 slug
        if (empty($test['slug'])) {
            $score -= 15;
            $report['issues'][] = '缺少 URL slug（影响 SEO）';
        }

        // 检查关键词密度
        $title = $test['title'] ?? '';
        $description = strip_tags($test['description'] ?? '');
        $keywords = self::extractKeywords($title . ' ' . $description);
        if (count($keywords) < 3) {
            $score -= 10;
            $report['issues'][] = '关键词数量不足';
        }

        $report['score'] = max(0, $score);
        $report['suggestions'] = array_merge(
            $report['title']['suggestions'],
            $report['description']['suggestions']
        );

        return $report;
    }
}

