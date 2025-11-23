<?php
/**
 * 应用性能监控（APM）
 * 
 * 监控慢查询、API 响应时间、错误率等关键指标
 */

class APM
{
    private static $startTime = null;
    private static $metrics = [];
    private static $enabled = true;

    /**
     * 开始监控请求
     */
    public static function start()
    {
        if (!self::$enabled) {
            return;
        }

        self::$startTime = microtime(true);
        self::$metrics = [
            'request_id' => uniqid('req_', true),
            'start_time' => self::$startTime,
            'queries' => [],
            'errors' => [],
            'memory_peak' => 0,
        ];
    }

    /**
     * 记录数据库查询
     */
    public static function recordQuery(string $sql, float $duration)
    {
        if (!self::$enabled || !self::$startTime) {
            return;
        }

        self::$metrics['queries'][] = [
            'sql' => $sql,
            'duration' => $duration,
            'timestamp' => microtime(true),
        ];

        // 记录慢查询到日志
        if ($duration > 1.0) {
            require_once __DIR__ . '/StructuredLogger.php';
            StructuredLogger::query($sql, $duration);
        }
    }

    /**
     * 记录错误
     */
    public static function recordError(string $message, array $context = [])
    {
        if (!self::$enabled || !self::$startTime) {
            return;
        }

        self::$metrics['errors'][] = [
            'message' => $message,
            'context' => $context,
            'timestamp' => microtime(true),
        ];
    }

    /**
     * 结束监控并记录指标
     */
    public static function end()
    {
        if (!self::$enabled || !self::$startTime) {
            return;
        }

        $endTime = microtime(true);
        $totalDuration = $endTime - self::$startTime;
        
        self::$metrics['end_time'] = $endTime;
        self::$metrics['total_duration'] = $totalDuration;
        self::$metrics['memory_peak'] = memory_get_peak_usage(true);
        self::$metrics['memory_usage'] = memory_get_usage(true);
        self::$metrics['query_count'] = count(self::$metrics['queries']);
        self::$metrics['error_count'] = count(self::$metrics['errors']);
        
        // 计算查询总时间
        $queryTotalTime = 0;
        foreach (self::$metrics['queries'] as $query) {
            $queryTotalTime += $query['duration'];
        }
        self::$metrics['query_total_time'] = $queryTotalTime;

        // 记录到日志
        self::logMetrics();

        // 如果响应时间过长或错误过多，记录警告
        if ($totalDuration > 3.0 || self::$metrics['error_count'] > 0) {
            require_once __DIR__ . '/StructuredLogger.php';
            StructuredLogger::warning('Slow request or errors detected', [
                'duration' => $totalDuration,
                'error_count' => self::$metrics['error_count'],
                'query_count' => self::$metrics['query_count'],
            ]);
        }
    }

    /**
     * 记录指标到日志
     */
    private static function logMetrics()
    {
        require_once __DIR__ . '/StructuredLogger.php';
        
        StructuredLogger::info('Request completed', [
            'type' => 'apm_metrics',
            'metrics' => self::$metrics,
        ]);
    }

    /**
     * 获取当前指标
     */
    public static function getMetrics(): array
    {
        return self::$metrics;
    }

    /**
     * 启用/禁用 APM
     */
    public static function setEnabled(bool $enabled)
    {
        self::$enabled = $enabled;
    }

    /**
     * 获取慢查询列表
     */
    public static function getSlowQueries(float $threshold = 1.0): array
    {
        $slowQueries = [];
        foreach (self::$metrics['queries'] ?? [] as $query) {
            if ($query['duration'] >= $threshold) {
                $slowQueries[] = $query;
            }
        }
        return $slowQueries;
    }

    /**
     * 获取系统健康度
     */
    public static function getHealth(): array
    {
        $metrics = self::getMetrics();
        
        $score = 100;
        $issues = [];
        
        // 检查响应时间
        $avgResponseTime = $metrics['total_duration'] ?? 0;
        if ($avgResponseTime > 3.0) {
            $score -= 20;
            $issues[] = '响应时间过慢';
        } elseif ($avgResponseTime > 2.0) {
            $score -= 10;
            $issues[] = '响应时间较慢';
        }
        
        // 检查错误数
        $errorCount = $metrics['error_count'] ?? 0;
        if ($errorCount > 0) {
            $score -= $errorCount * 5;
            $issues[] = '存在错误';
        }
        
        // 检查慢查询
        $slowQueries = self::getSlowQueries(1.0);
        $slowQueryCount = count($slowQueries);
        if ($slowQueryCount > 0) {
            $score -= $slowQueryCount * 10;
            $issues[] = '存在慢查询';
        }
        
        // 检查查询数量
        $queryCount = $metrics['query_count'] ?? 0;
        if ($queryCount > 20) {
            $score -= 5;
            $issues[] = '查询数量较多';
        }
        
        $score = max(0, min(100, $score));
        
        return [
            'score' => $score,
            'avg_response_time' => $avgResponseTime,
            'error_count' => $errorCount,
            'slow_queries' => $slowQueryCount,
            'query_count' => $queryCount,
            'issues' => $issues,
        ];
    }

    /**
     * 获取统计信息（从日志分析）
     */
    public static function getStats(): array
    {
        // 尝试从日志分析器获取统计
        try {
            require_once __DIR__ . '/LogAnalyzer.php';
            $analyzer = new LogAnalyzer();
            $perfStats = $analyzer->getPerformanceStats(24);
            
            return [
                'total_requests' => $perfStats['total_requests'] ?? 0,
                'avg_response_time' => $perfStats['avg_duration'] ?? 0,
                'max_response_time' => $perfStats['max_duration'] ?? 0,
                'total_queries' => $perfStats['query_stats']['total'] ?? 0,
                'avg_query_time' => $perfStats['query_stats']['avg_duration'] ?? 0,
                'slow_queries' => $perfStats['query_stats']['slow_queries'] ?? 0,
                'error_count' => 0, // 需要从错误日志统计
            ];
        } catch (Exception $e) {
            return [
                'total_requests' => 0,
                'avg_response_time' => 0,
                'max_response_time' => 0,
                'total_queries' => 0,
                'avg_query_time' => 0,
                'slow_queries' => 0,
                'error_count' => 0,
            ];
        }
    }
}

