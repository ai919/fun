<?php
/**
 * 应用配置文件
 * 
 * 包含应用级别的配置，如调试模式、错误处理等
 */
return [
    // 调试模式：开发环境设为 true，生产环境设为 false
    'debug' => false,
    
    // 应用环境：'development' | 'production' | 'testing'
    'environment' => 'production',
    
    // 日志配置
    'log' => [
        // 日志目录（相对于项目根目录）
        'dir' => __DIR__ . '/../logs',
        
        // 是否启用日志
        'enabled' => true,
        
        // 日志级别：'DEBUG' | 'INFO' | 'WARNING' | 'ERROR'
        'level' => 'INFO',
        
        // 是否记录到文件
        'file' => true,
        
        // 是否同时记录到系统日志
        'system' => true,
    ],
    
    // 错误处理配置
    'error' => [
        // 是否显示详细错误信息（仅在 debug 模式下生效）
        'display_details' => true,
        
        // 是否记录错误堆栈
        'log_stack_trace' => true,
        
        // 错误页面模板路径（可选）
        'error_page' => null,
    ],
    
    // 时区
    'timezone' => 'Asia/Shanghai',
];

