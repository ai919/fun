<?php
/**
 * 日志分析器
 * 
 * 分析结构化日志，提供错误追踪、统计和告警功能
 */

class LogAnalyzer
{
    private $logDir;
    private $config;

    public function __construct(?string $logDir = null)
    {
        $configFile = __DIR__ . '/../config/app.php';
        if (file_exists($configFile)) {
            $this->config = require $configFile;
        } else {
            $this->config = [];
        }

        $this->logDir = $logDir ?? ($this->config['log']['dir'] ?? __DIR__ . '/../logs');
    }

    /**
     * 分析指定时间范围内的日志
     * 
     * @param string $level 日志级别（error, warning, info, debug）
     * @param int $hours 时间范围（小时）
     * @return array
     */
    public function analyze(string $level = 'error', int $hours = 24): array
    {
        $logFile = $this->logDir . '/' . strtolower($level) . '.json.log';
        $since = time() - ($hours * 3600);
        
        $stats = [
            'total' => 0,
            'by_message' => [],
            'by_hour' => [],
            'errors' => [],
            'unique_ips' => [],
            'unique_user_agents' => [],
        ];

        if (!file_exists($logFile) || !is_readable($logFile)) {
            return $stats;
        }

        // 读取日志文件（逐行解析，避免内存问题）
        $handle = fopen($logFile, 'r');
        if (!$handle) {
            return $stats;
        }

        while (($line = fgets($handle)) !== false) {
            $logEntry = json_decode(trim($line), true);
            if (!$logEntry) {
                continue;
            }

            // 检查时间范围
            $timestamp = strtotime($logEntry['timestamp'] ?? '');
            if ($timestamp < $since) {
                continue;
            }

            $stats['total']++;

            // 按消息分组
            $message = $logEntry['message'] ?? 'Unknown';
            if (!isset($stats['by_message'][$message])) {
                $stats['by_message'][$message] = 0;
            }
            $stats['by_message'][$message]++;

            // 按小时分组
            $hour = date('Y-m-d H:00:00', $timestamp);
            if (!isset($stats['by_hour'][$hour])) {
                $stats['by_hour'][$hour] = 0;
            }
            $stats['by_hour'][$hour]++;

            // 收集错误详情
            if ($level === 'error') {
                $stats['errors'][] = [
                    'timestamp' => $logEntry['timestamp'],
                    'message' => $message,
                    'context' => $logEntry['context'] ?? [],
                    'server' => $logEntry['server'] ?? [],
                ];
            }

            // 统计唯一 IP
            $ip = $logEntry['server']['remote_addr'] ?? '';
            if ($ip && !in_array($ip, $stats['unique_ips'])) {
                $stats['unique_ips'][] = $ip;
            }

            // 统计唯一 User-Agent
            $ua = $logEntry['server']['user_agent'] ?? '';
            if ($ua && !in_array($ua, $stats['unique_user_agents'])) {
                $stats['unique_user_agents'][] = $ua;
            }
        }

        fclose($handle);

        // 排序
        arsort($stats['by_message']);
        ksort($stats['by_hour']);

        return $stats;
    }

    /**
     * 获取错误趋势
     */
    public function getErrorTrend(int $hours = 24): array
    {
        $stats = $this->analyze('error', $hours);
        return $stats['by_hour'];
    }

    /**
     * 获取最常见的错误
     */
    public function getTopErrors(int $limit = 10): array
    {
        $stats = $this->analyze('error', 24);
        return array_slice($stats['by_message'], 0, $limit, true);
    }

    /**
     * 检查是否需要告警
     * 
     * @param int $errorThreshold 错误阈值（每小时）
     * @return array 告警信息
     */
    public function checkAlerts(int $errorThreshold = 10): array
    {
        $alerts = [];
        
        // 检查最近 1 小时的错误数
        $stats = $this->analyze('error', 1);
        $errorCount = $stats['total'];
        
        if ($errorCount >= $errorThreshold) {
            $alerts[] = [
                'level' => 'high',
                'message' => "High error rate detected: {$errorCount} errors in the last hour",
                'count' => $errorCount,
                'threshold' => $errorThreshold,
            ];
        }

        // 检查是否有重复错误（同一错误在短时间内多次出现）
        $topErrors = $this->getTopErrors(5);
        foreach ($topErrors as $message => $count) {
            if ($count >= 5) {
                $alerts[] = [
                    'level' => 'medium',
                    'message' => "Repeated error: {$message} (occurred {$count} times)",
                    'error' => $message,
                    'count' => $count,
                ];
            }
        }

        return $alerts;
    }

    /**
     * 获取性能统计
     */
    public function getPerformanceStats(int $hours = 24): array
    {
        $logFile = $this->logDir . '/info.json.log';
        $since = time() - ($hours * 3600);
        
        $stats = [
            'total_requests' => 0,
            'avg_duration' => 0,
            'max_duration' => 0,
            'min_duration' => PHP_FLOAT_MAX,
            'slow_requests' => 0,
            'query_stats' => [
                'total' => 0,
                'avg_duration' => 0,
                'slow_queries' => 0,
            ],
        ];

        if (!file_exists($logFile) || !is_readable($logFile)) {
            return $stats;
        }

        $durations = [];
        $queryDurations = [];
        $slowRequestThreshold = 3.0;
        $slowQueryThreshold = 1.0;

        $handle = fopen($logFile, 'r');
        if (!$handle) {
            return $stats;
        }

        while (($line = fgets($handle)) !== false) {
            $logEntry = json_decode(trim($line), true);
            if (!$logEntry) {
                continue;
            }

            // 检查时间范围
            $timestamp = strtotime($logEntry['timestamp'] ?? '');
            if ($timestamp < $since) {
                continue;
            }

            // 检查是否是 APM 指标
            $context = $logEntry['context'] ?? [];
            if (isset($context['type']) && $context['type'] === 'apm_metrics') {
                $metrics = $context['metrics'] ?? [];
                $duration = $metrics['total_duration'] ?? 0;
                
                if ($duration > 0) {
                    $stats['total_requests']++;
                    $durations[] = $duration;
                    
                    if ($duration > $stats['max_duration']) {
                        $stats['max_duration'] = $duration;
                    }
                    if ($duration < $stats['min_duration']) {
                        $stats['min_duration'] = $duration;
                    }
                    if ($duration > $slowRequestThreshold) {
                        $stats['slow_requests']++;
                    }
                }

                // 查询统计
                $queries = $metrics['queries'] ?? [];
                foreach ($queries as $query) {
                    $queryDuration = $query['duration'] ?? 0;
                    if ($queryDuration > 0) {
                        $stats['query_stats']['total']++;
                        $queryDurations[] = $queryDuration;
                        if ($queryDuration > $slowQueryThreshold) {
                            $stats['query_stats']['slow_queries']++;
                        }
                    }
                }
            }
        }

        fclose($handle);

        // 计算平均值
        if (!empty($durations)) {
            $stats['avg_duration'] = array_sum($durations) / count($durations);
        }
        if (!empty($queryDurations)) {
            $stats['query_stats']['avg_duration'] = array_sum($queryDurations) / count($queryDurations);
        }
        if ($stats['min_duration'] === PHP_FLOAT_MAX) {
            $stats['min_duration'] = 0;
        }

        return $stats;
    }

    /**
     * 获取最近的错误
     */
    public function getRecentErrors(int $hours = 24): array
    {
        $stats = $this->analyze('error', $hours);
        return $stats['errors'] ?? [];
    }

    /**
     * 获取告警信息
     */
    public function getAlerts(int $hours = 24): array
    {
        $alerts = [];
        
        // 检查错误率
        $stats = $this->analyze('error', $hours);
        $errorCount = $stats['total'];
        
        if ($errorCount > 50) {
            $alerts[] = [
                'level' => 'error',
                'message' => "错误日志过多：{$errorCount} 个错误（{$hours}小时）",
                'details' => "建议立即检查错误日志并解决问题",
            ];
        } elseif ($errorCount > 20) {
            $alerts[] = [
                'level' => 'warning',
                'message' => "错误日志较多：{$errorCount} 个错误（{$hours}小时）",
                'details' => "建议检查错误日志",
            ];
        }
        
        // 检查重复错误
        $topErrors = $this->getTopErrors(5);
        foreach ($topErrors as $message => $count) {
            if ($count >= 10) {
                $alerts[] = [
                    'level' => 'error',
                    'message' => "重复错误：{$message}",
                    'details' => "该错误已出现 {$count} 次，建议立即修复",
                ];
            } elseif ($count >= 5) {
                $alerts[] = [
                    'level' => 'warning',
                    'message' => "重复错误：{$message}",
                    'details' => "该错误已出现 {$count} 次",
                ];
            }
        }
        
        return $alerts;
    }
}

