<?php
/**
 * 安装处理脚本
 */

header('Content-Type: application/json');

/**
 * 解析 SQL 语句，正确处理字符串中的分号
 */
function parseSqlStatements($sql) {
    $statements = [];
    $current = '';
    $inString = false;
    $stringChar = '';
    $inComment = false;
    $commentType = ''; // '--' or '/*'
    
    $len = strlen($sql);
    for ($i = 0; $i < $len; $i++) {
        $char = $sql[$i];
        $nextChar = ($i + 1 < $len) ? $sql[$i + 1] : '';
        
        // 处理注释
        if (!$inString && !$inComment) {
            // 单行注释 --
            if ($char === '-' && $nextChar === '-') {
                // 跳过到行尾
                while ($i < $len && $sql[$i] !== "\n") {
                    $i++;
                }
                continue;
            }
            // 多行注释 /* */
            if ($char === '/' && $nextChar === '*') {
                $inComment = true;
                $commentType = '/*';
                $i++; // 跳过 *
                continue;
            }
        }
        
        if ($inComment) {
            if ($commentType === '/*' && $char === '*' && $nextChar === '/') {
                $inComment = false;
                $commentType = '';
                $i++; // 跳过 /
                continue;
            }
            continue;
        }
        
        // 处理字符串
        if (!$inString && ($char === '"' || $char === "'" || $char === '`')) {
            $inString = true;
            $stringChar = $char;
            $current .= $char;
        } elseif ($inString && $char === $stringChar) {
            // 检查是否是转义的引号
            if ($i > 0 && $sql[$i-1] === '\\') {
                $current .= $char;
            } else {
                $inString = false;
                $stringChar = '';
                $current .= $char;
            }
        } elseif (!$inString && $char === ';') {
            $stmt = trim($current);
            if (!empty($stmt) && 
                !preg_match('/^(SET|CREATE DATABASE|USE|START TRANSACTION|COMMIT)/i', $stmt) &&
                !preg_match('/^\/\*/', $stmt)) {
                $statements[] = $stmt;
            }
            $current = '';
        } else {
            $current .= $char;
        }
    }
    
    // 处理最后一个语句（如果没有分号结尾）
    $stmt = trim($current);
    if (!empty($stmt) && 
        !preg_match('/^(SET|CREATE DATABASE|USE|START TRANSACTION|COMMIT)/i', $stmt) &&
        !preg_match('/^\/\*/', $stmt)) {
        $statements[] = $stmt;
    }
    
    return $statements;
}

$action = $_GET['action'] ?? '';

if ($action === 'test_connection') {
    // 测试数据库连接
    $host = $_POST['db_host'] ?? '127.0.0.1';
    $dbname = $_POST['db_name'] ?? 'fun_quiz';
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';

    try {
        // 先尝试连接 MySQL（不指定数据库）
        $dsn = "mysql:host={$host};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ]);

        // 检查数据库是否存在
        $stmt = $pdo->query("SHOW DATABASES LIKE '{$dbname}'");
        $dbExists = $stmt->rowCount() > 0;

        echo json_encode([
            'success' => true,
            'db_exists' => $dbExists,
            'message' => '连接成功' . ($dbExists ? '，数据库已存在' : '，将创建新数据库')
        ]);
    } catch (PDOException $e) {
        echo json_encode([
            'success' => false,
            'message' => '连接失败: ' . $e->getMessage()
        ]);
    }
    exit;
}

if ($action === 'install') {
    // 执行安装
    $host = $_POST['db_host'] ?? '127.0.0.1';
    $dbname = $_POST['db_name'] ?? 'fun_quiz';
    $user = $_POST['db_user'] ?? 'root';
    $pass = $_POST['db_pass'] ?? '';
    $adminUsername = $_POST['admin_username'] ?? 'admin';
    $adminPassword = $_POST['admin_password'] ?? '';
    $adminDisplayName = $_POST['admin_display_name'] ?? '管理员';

    try {
        // 连接 MySQL（不指定数据库）
        $dsn = "mysql:host={$host};charset=utf8mb4";
        $pdo = new PDO($dsn, $user, $pass, [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 10,
        ]);

        // 创建数据库（如果不存在）
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbname}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        $pdo->exec("USE `{$dbname}`");

        $sqlDir = __DIR__ . '/../database/';
        $fullSqlFile = $sqlDir . 'fun_quiz.sql';
        
        // 优先使用完整的数据库文件 fun_quiz.sql
        if (file_exists($fullSqlFile)) {
            // 使用完整的数据库文件
            $sql = file_get_contents($fullSqlFile);
            
            // 移除 phpMyAdmin 导出的特殊指令（在行首）
            $sql = preg_replace('/^SET SQL_MODE[^;]*;/mi', '', $sql);
            $sql = preg_replace('/^START TRANSACTION;/mi', '', $sql);
            $sql = preg_replace('/^SET time_zone[^;]*;/mi', '', $sql);
            $sql = preg_replace('/^\/\*!40101 SET[^;]*;\s*\*\/;/mi', '', $sql);
            $sql = preg_replace('/^\/\*!40101 SET[^;]*;\s*\*\/\s*/mi', '', $sql);
            $sql = preg_replace('/^COMMIT;/mi', '', $sql);
            $sql = preg_replace('/^\/\*!40101 SET CHARACTER_SET[^;]*;\s*\*\/\s*/mi', '', $sql);
            
            // 移除默认管理员插入（我们稍后会创建）
            // 匹配多行的 INSERT INTO admin_users 语句
            $sql = preg_replace('/INSERT INTO\s+`?admin_users`?\s*\([^)]+\)\s*VALUES[^;]+;/is', '', $sql);
            
            // 移除注释行（以 -- 开头的整行注释）
            $sql = preg_replace('/^--[^\r\n]*[\r\n]*/m', '', $sql);
            
            // 移除多余的空行（保留单个空行用于分隔）
            $sql = preg_replace('/\n\s*\n\s*\n+/', "\n\n", $sql);
            
            // 分割并执行 SQL 语句
            $statements = parseSqlStatements($sql);
            
            foreach ($statements as $stmt) {
                try {
                    $pdo->exec($stmt);
                } catch (PDOException $e) {
                    // 忽略某些错误（如表已存在、索引已存在等）
                    if (strpos($e->getMessage(), 'already exists') === false &&
                        strpos($e->getMessage(), 'Duplicate key') === false &&
                        strpos($e->getMessage(), 'Duplicate entry') === false) {
                        throw $e;
                    }
                }
            }
        } else {
            // 回退到使用迁移文件列表
            $sqlFiles = [
                '001_init_schema.sql',
                '002_seed_basic_data.sql',
                '004_create_backup_logs_table.sql',
                '005_add_display_mode_to_tests.sql',
                '006_create_users_and_link_runs.sql',
                '007_optimize_schema.sql',
                '011_create_share_stats.sql',
                '012_create_user_favorites.sql',
                '013_create_settings_table.sql',
                '014_create_motivational_quotes_table.sql',
                '015_add_seo_settings.sql',
                '016_create_ad_positions_table.sql',
                '017_add_play_count_beautified_to_tests.sql',
                '018_add_user_profile_fields.sql',
                '019_create_notifications_table.sql',
            ];

            foreach ($sqlFiles as $file) {
                $filePath = $sqlDir . $file;
                if (!file_exists($filePath)) {
                    continue;
                }

                $sql = file_get_contents($filePath);
                
                // 移除默认管理员插入（我们稍后会创建）
                if ($file === '001_init_schema.sql') {
                    $sql = preg_replace('/INSERT INTO admin_users[^;]+;/i', '', $sql);
                }

                // 处理包含 DELIMITER 的 SQL（如存储过程）
                if (preg_match('/DELIMITER\s+\$\$/i', $sql)) {
                    // 对于包含存储过程的 SQL，使用更智能的分割
                    // 移除 DELIMITER 指令
                    $sql = preg_replace('/DELIMITER\s+\$\$/i', '', $sql);
                    $sql = preg_replace('/\$\$/i', ';', $sql);
                }

                // 移除注释和空行
                $sql = preg_replace('/^--.*$/m', '', $sql);
                $sql = preg_replace('/^\s*$/m', '', $sql);

                // 分割 SQL 语句
                $statements = parseSqlStatements($sql);
                
                // 执行所有语句
                foreach ($statements as $stmt) {
                    try {
                        $pdo->exec($stmt);
                    } catch (PDOException $e) {
                        // 忽略某些错误（如表已存在、索引已存在等）
                        if (strpos($e->getMessage(), 'already exists') === false &&
                            strpos($e->getMessage(), 'Duplicate key') === false) {
                            throw $e;
                        }
                    }
                }
            }
        }

        // 创建管理员账户
        $passwordHash = password_hash($adminPassword, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("
            INSERT INTO admin_users (username, password_hash, display_name, is_active)
            VALUES (?, ?, ?, 1)
        ");
        $stmt->execute([$adminUsername, $passwordHash, $adminDisplayName]);

        // 创建 .env 文件
        // 转义密码中的特殊字符
        $escapedPass = addcslashes($pass, '"\\$');
        $envContent = "# 数据库配置\n";
        $envContent .= "DB_HOST=" . $host . "\n";
        $envContent .= "DB_DATABASE=" . $dbname . "\n";
        $envContent .= "DB_USERNAME=" . $user . "\n";
        $envContent .= "DB_PASSWORD=\"" . $escapedPass . "\"\n";
        $envContent .= "DB_CHARSET=utf8mb4\n";
        $envContent .= "\n";
        $envContent .= "# 应用配置\n";
        $envContent .= "APP_ENV=production\n";
        $envContent .= "APP_DEBUG=false\n";
        
        $envFile = __DIR__ . '/../.env';
        if (!file_put_contents($envFile, $envContent)) {
            throw new Exception('无法创建 .env 文件，请检查 config 目录权限');
        }

        // 创建安装锁定文件
        file_put_contents(__DIR__ . '/../.installed', date('Y-m-d H:i:s'));

        echo json_encode([
            'success' => true,
            'message' => '安装成功！'
        ]);
    } catch (Exception $e) {
        echo json_encode([
            'success' => false,
            'message' => '安装失败: ' . $e->getMessage()
        ]);
    }
    exit;
}

echo json_encode([
    'success' => false,
    'message' => '无效的操作'
]);

