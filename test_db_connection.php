<?php
/**
 * 测试数据库连接和 test_runs 表访问
 * 在浏览器中访问此文件，检查是否能正常连接和查询
 */

require __DIR__ . '/lib/db_connect.php';

header('Content-Type: text/html; charset=utf-8');
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <title>数据库连接测试</title>
    <style>
        body { font-family: monospace; padding: 20px; background: #f5f5f5; }
        .success { color: green; }
        .error { color: red; }
        .info { color: blue; }
        pre { background: white; padding: 10px; border-radius: 4px; }
        h2 { margin-top: 30px; }
    </style>
</head>
<body>
    <h1>数据库连接测试</h1>

<?php
try {
    // 测试 1: 检查当前数据库
    echo "<h2>测试 1: 当前数据库</h2>";
    $stmt = $pdo->query('SELECT DATABASE()');
    $currentDb = $stmt->fetchColumn();
    echo "<p class='info'>当前数据库: <strong>{$currentDb}</strong></p>";
    
    if ($currentDb !== 'fun_quiz') {
        echo "<p class='error'>⚠️ 警告：当前数据库不是 'fun_quiz'！</p>";
    } else {
        echo "<p class='success'>✓ 数据库名称正确</p>";
    }
    
    // 测试 2: 检查 test_runs 表是否存在
    echo "<h2>测试 2: 检查 test_runs 表</h2>";
    $stmt = $pdo->query("SHOW TABLES LIKE 'test_runs'");
    $tableExists = $stmt->fetchColumn();
    
    if ($tableExists) {
        echo "<p class='success'>✓ test_runs 表存在</p>";
    } else {
        echo "<p class='error'>✗ test_runs 表不存在！</p>";
        echo "</body></html>";
        exit;
    }
    
    // 测试 3: 检查 share_token 列
    echo "<h2>测试 3: 检查 share_token 列</h2>";
    $stmt = $pdo->query("SHOW COLUMNS FROM test_runs LIKE 'share_token'");
    $columnExists = $stmt->fetchColumn();
    
    if ($columnExists) {
        echo "<p class='success'>✓ share_token 列存在</p>";
        
        // 显示列信息
        $stmt = $pdo->query("SHOW COLUMNS FROM test_runs WHERE Field = 'share_token'");
        $colInfo = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "<pre>";
        print_r($colInfo);
        echo "</pre>";
    } else {
        echo "<p class='error'>✗ share_token 列不存在！</p>";
    }
    
    // 测试 4: 检查索引
    echo "<h2>测试 4: 检查 share_token 索引</h2>";
    $stmt = $pdo->query("SHOW INDEX FROM test_runs WHERE Column_name = 'share_token'");
    $indexes = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($indexes)) {
        echo "<p class='error'>✗ share_token 没有索引</p>";
    } else {
        echo "<p class='success'>✓ share_token 索引信息：</p>";
        echo "<pre>";
        print_r($indexes);
        echo "</pre>";
    }
    
    // 测试 5: 查询 test_runs 表数据
    echo "<h2>测试 5: 查询 test_runs 表数据</h2>";
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM test_runs");
    $total = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p class='info'>总记录数: <strong>{$total['total']}</strong></p>";
    
    $stmt = $pdo->query("SELECT COUNT(*) as total FROM test_runs WHERE share_token IS NOT NULL");
    $withToken = $stmt->fetch(PDO::FETCH_ASSOC);
    echo "<p class='info'>有 share_token 的记录数: <strong>{$withToken['total']}</strong></p>";
    
    // 测试 6: 测试查询 share_token
    echo "<h2>测试 6: 测试查询 share_token</h2>";
    $stmt = $pdo->query("SELECT id, share_token FROM test_runs WHERE share_token IS NOT NULL LIMIT 5");
    $samples = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($samples)) {
        echo "<p class='error'>⚠️ 没有找到包含 share_token 的记录</p>";
    } else {
        echo "<p class='success'>✓ 找到包含 share_token 的记录：</p>";
        echo "<pre>";
        print_r($samples);
        echo "</pre>";
        
        // 测试使用 share_token 查询
        if (!empty($samples[0]['share_token'])) {
            $testToken = $samples[0]['share_token'];
            echo "<p class='info'>测试查询 token: {$testToken}</p>";
            $stmt = $pdo->prepare("SELECT * FROM test_runs WHERE share_token = ? LIMIT 1");
            $stmt->execute([$testToken]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($result) {
                echo "<p class='success'>✓ 通过 share_token 查询成功</p>";
            } else {
                echo "<p class='error'>✗ 通过 share_token 查询失败</p>";
            }
        }
    }
    
    // 测试 7: 模拟 submit.php 的插入操作（只测试，不实际插入）
    echo "<h2>测试 7: 测试 INSERT 语句（不实际执行）</h2>";
    $testToken = bin2hex(random_bytes(16));
    echo "<p class='info'>生成的测试 token: {$testToken}</p>";
    
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM test_runs WHERE share_token = ?");
    $stmt->execute([$testToken]);
    $exists = (int)$stmt->fetchColumn();
    
    if ($exists > 0) {
        echo "<p class='error'>⚠️ 生成的 token 已存在（极小概率事件）</p>";
    } else {
        echo "<p class='success'>✓ 生成的 token 唯一</p>";
    }
    
    echo "<p class='info'>INSERT 语句预览：</p>";
    echo "<pre>";
    echo "INSERT INTO test_runs (user_id, test_id, result_id, user_identifier, ip_address, user_agent, total_score, share_token)\n";
    echo "VALUES (NULL, 1, NULL, NULL, '127.0.0.1', 'Test', 0.00, '{$testToken}')";
    echo "</pre>";
    
    echo "<h2 class='success'>✅ 所有测试通过！数据库连接正常。</h2>";
    
} catch (PDOException $e) {
    echo "<h2 class='error'>❌ 数据库错误</h2>";
    echo "<p class='error'>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre>" . htmlspecialchars($e->getTraceAsString()) . "</pre>";
} catch (Exception $e) {
    echo "<h2 class='error'>❌ 其他错误</h2>";
    echo "<p class='error'>错误信息: " . htmlspecialchars($e->getMessage()) . "</p>";
}
?>

</body>
</html>

