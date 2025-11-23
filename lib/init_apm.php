<?php
/**
 * APM 初始化文件
 * 
 * 在应用启动时调用此文件以启用性能监控
 * 在 index.php 或入口文件的最开始处引入：require_once __DIR__ . '/lib/init_apm.php';
 */

require_once __DIR__ . '/APM.php';

// 启动 APM 监控
APM::start();

// 注册关闭函数，在请求结束时记录指标
register_shutdown_function(function() {
    APM::end();
});

