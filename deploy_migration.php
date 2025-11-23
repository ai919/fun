<?php
/**
 * 线上迁移部署脚本
 * 
 * 使用方法：
 * 1. 预览模式：php deploy_migration.php --dry-run
 * 2. 执行迁移：php deploy_migration.php
 * 
 * 安全提示：
 * - 执行前请先备份数据库
 * - 建议先在测试环境验证
 */
require_once __DIR__ . '/lib/db_connect.php';
require_once __DIR__ . '/lib/Migration.php';

$dryRun = in_array('--dry-run', $argv) || in_array('-n', $argv);

echo "========================================\n";
echo "  数据库迁移部署工具\n";
echo "========================================\n\n";

if ($dryRun) {
    echo "⚠️  预览模式（不会实际执行）\n\n";
} else {
    echo "⚠️  警告：即将执行数据库迁移！\n";
    echo "请确认：\n";
    echo "  1. 已备份数据库\n";
    echo "  2. 已在测试环境验证\n";
    echo "  3. 当前为业务低峰期\n\n";
    echo "5秒后开始执行... (按 Ctrl+C 取消)\n";
    sleep(5);
    echo "\n";
}

try {
    $migration = new Migration($pdo);
    
    // 检查迁移状态
    echo "📋 检查迁移状态...\n";
    $status = $migration->status();
    
    $pendingCount = 0;
    foreach ($status as $item) {
        if ($item['status'] === 'pending') {
            $pendingCount++;
            echo "  ⏳ {$item['migration']}\n";
        } else {
            echo "  ✅ {$item['migration']}\n";
        }
    }
    
    if ($pendingCount === 0) {
        echo "\n✅ 没有待执行的迁移\n";
        exit(0);
    }
    
    echo "\n📊 待执行迁移: {$pendingCount} 个\n\n";
    
    // 执行迁移
    echo "🚀 " . ($dryRun ? "预览" : "执行") . "迁移...\n";
    $results = $migration->migrate($dryRun);
    
    // 显示结果
    if (!empty($results['executed'])) {
        echo "\n✅ 成功 " . ($dryRun ? "预览" : "执行") . " " . count($results['executed']) . " 个迁移:\n";
        foreach ($results['executed'] as $migrationName) {
            echo "  ✓ {$migrationName}\n";
        }
    }
    
    if (!empty($results['failed'])) {
        echo "\n❌ 失败的迁移:\n";
        foreach ($results['failed'] as $failed) {
            echo "  ✗ {$failed['migration']}: {$failed['error']}\n";
        }
        exit(1);
    }
    
    if ($dryRun) {
        echo "\n💡 提示：这是预览模式，未实际执行。\n";
        echo "   要实际执行，请运行: php deploy_migration.php\n";
    } else {
        echo "\n✅ 迁移执行完成！\n";
        echo "   请验证功能是否正常。\n";
    }
    
} catch (Exception $e) {
    echo "\n❌ 错误: " . $e->getMessage() . "\n";
    echo "   文件: " . $e->getFile() . "\n";
    echo "   行号: " . $e->getLine() . "\n";
    exit(1);
}

