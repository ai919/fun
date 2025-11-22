<?php
/**
 * 数据库迁移系统
 * 
 * 管理数据库结构变更，支持版本控制和回滚
 */
class Migration
{
    private $pdo;
    private $migrationsTable = 'migrations';

    /**
     * 构造函数
     * 
     * @param PDO $pdo 数据库连接
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
        $this->ensureMigrationsTable();
    }

    /**
     * 确保 migrations 表存在
     */
    private function ensureMigrationsTable()
    {
        $sql = "CREATE TABLE IF NOT EXISTS `{$this->migrationsTable}` (
            `id` INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            `migration` VARCHAR(255) NOT NULL UNIQUE,
            `batch` INT UNSIGNED NOT NULL,
            `executed_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX `idx_batch` (`batch`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->pdo->exec($sql);
    }

    /**
     * 获取迁移文件目录
     * 
     * @return string
     */
    private function getMigrationsPath(): string
    {
        return __DIR__ . '/../database/migrations';
    }

    /**
     * 获取所有迁移文件
     * 
     * @return array
     */
    private function getMigrationFiles(): array
    {
        $path = $this->getMigrationsPath();
        if (!is_dir($path)) {
            return [];
        }

        $files = glob($path . '/*.php');
        $migrations = [];

        foreach ($files as $file) {
            $name = basename($file, '.php');
            if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_(.+)$/', $name, $matches)) {
                $migrations[$matches[1]] = [
                    'timestamp' => $matches[1],
                    'name' => $matches[2],
                    'file' => $file,
                ];
            }
        }

        ksort($migrations);
        return $migrations;
    }

    /**
     * 获取已执行的迁移
     * 
     * @return array
     */
    private function getExecutedMigrations(): array
    {
        $stmt = $this->pdo->query("SELECT migration FROM {$this->migrationsTable} ORDER BY id");
        return $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    /**
     * 获取下一个批次号
     * 
     * @return int
     */
    private function getNextBatch(): int
    {
        $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return (int)($result['max_batch'] ?? 0) + 1;
    }

    /**
     * 执行迁移
     * 
     * @param bool $dryRun 是否只是预览（不实际执行）
     * @return array 执行结果
     */
    public function migrate(bool $dryRun = false): array
    {
        $migrations = $this->getMigrationFiles();
        $executed = $this->getExecutedMigrations();
        $pending = [];
        $results = [
            'executed' => [],
            'failed' => [],
            'skipped' => 0,
        ];

        foreach ($migrations as $timestamp => $migration) {
            $migrationName = $timestamp . '_' . $migration['name'];

            if (in_array($migrationName, $executed)) {
                $results['skipped']++;
                continue;
            }

            $pending[] = $migration;
        }

        if (empty($pending)) {
            return $results;
        }

        $batch = $this->getNextBatch();

        foreach ($pending as $migration) {
            $migrationName = $migration['timestamp'] . '_' . $migration['name'];

            try {
                if (!$dryRun) {
                    $this->pdo->beginTransaction();

                    // 加载迁移类
                    require_once $migration['file'];
                    $className = $this->getMigrationClassName($migration['name']);

                    if (!class_exists($className)) {
                        throw new Exception("迁移类 {$className} 不存在");
                    }

                    $migrationInstance = new $className($this->pdo);
                    $migrationInstance->up();

                    // 记录迁移
                    $stmt = $this->pdo->prepare(
                        "INSERT INTO {$this->migrationsTable} (migration, batch) VALUES (?, ?)"
                    );
                    $stmt->execute([$migrationName, $batch]);

                    $this->pdo->commit();
                }

                $results['executed'][] = $migrationName;
            } catch (Exception $e) {
                if (!$dryRun) {
                    $this->pdo->rollBack();
                }
                $results['failed'][] = [
                    'migration' => $migrationName,
                    'error' => $e->getMessage(),
                ];
                break; // 遇到错误停止执行
            }
        }

        return $results;
    }

    /**
     * 回滚迁移
     * 
     * @param int|null $steps 回滚的批次数量，null 表示回滚最后一个批次
     * @param bool $dryRun 是否只是预览
     * @return array 执行结果
     */
    public function rollback(?int $steps = null, bool $dryRun = false): array
    {
        if ($steps === null) {
            // 回滚最后一个批次
            $stmt = $this->pdo->query("SELECT MAX(batch) as max_batch FROM {$this->migrationsTable}");
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            $targetBatch = (int)($result['max_batch'] ?? 0);
        } else {
            // 回滚指定数量的批次
            $stmt = $this->pdo->query("SELECT DISTINCT batch FROM {$this->migrationsTable} ORDER BY batch DESC LIMIT ?");
            $stmt->bindValue(1, $steps, PDO::PARAM_INT);
            $stmt->execute();
            $batches = $stmt->fetchAll(PDO::FETCH_COLUMN);
            $targetBatch = $batches[0] ?? 0;
        }

        if ($targetBatch === 0) {
            return ['executed' => [], 'failed' => [], 'message' => '没有可回滚的迁移'];
        }

        // 获取需要回滚的迁移
        $stmt = $this->pdo->prepare(
            "SELECT migration FROM {$this->migrationsTable} WHERE batch = ? ORDER BY id DESC"
        );
        $stmt->execute([$targetBatch]);
        $migrations = $stmt->fetchAll(PDO::FETCH_COLUMN);

        $results = [
            'executed' => [],
            'failed' => [],
        ];

        $migrationFiles = $this->getMigrationFiles();

        foreach ($migrations as $migrationName) {
            // 解析迁移名称
            if (preg_match('/^(\d{4}_\d{2}_\d{2}_\d{6})_(.+)$/', $migrationName, $matches)) {
                $timestamp = $matches[1];
                $name = $matches[2];

                if (!isset($migrationFiles[$timestamp])) {
                    $results['failed'][] = [
                        'migration' => $migrationName,
                        'error' => '迁移文件不存在',
                    ];
                    continue;
                }

                $migration = $migrationFiles[$timestamp];

                try {
                    if (!$dryRun) {
                        $this->pdo->beginTransaction();

                        // 加载迁移类
                        require_once $migration['file'];
                        $className = $this->getMigrationClassName($name);

                        if (!class_exists($className)) {
                            throw new Exception("迁移类 {$className} 不存在");
                        }

                        $migrationInstance = new $className($this->pdo);
                        if (method_exists($migrationInstance, 'down')) {
                            $migrationInstance->down();
                        }

                        // 删除迁移记录
                        $stmt = $this->pdo->prepare("DELETE FROM {$this->migrationsTable} WHERE migration = ?");
                        $stmt->execute([$migrationName]);

                        $this->pdo->commit();
                    }

                    $results['executed'][] = $migrationName;
                } catch (Exception $e) {
                    if (!$dryRun) {
                        $this->pdo->rollBack();
                    }
                    $results['failed'][] = [
                        'migration' => $migrationName,
                        'error' => $e->getMessage(),
                    ];
                    break;
                }
            }
        }

        return $results;
    }

    /**
     * 获取迁移状态
     * 
     * @return array
     */
    public function status(): array
    {
        $migrations = $this->getMigrationFiles();
        $executed = $this->getExecutedMigrations();
        $status = [];

        foreach ($migrations as $timestamp => $migration) {
            $migrationName = $timestamp . '_' . $migration['name'];
            $status[] = [
                'migration' => $migrationName,
                'status' => in_array($migrationName, $executed) ? 'executed' : 'pending',
            ];
        }

        return $status;
    }

    /**
     * 获取迁移类名
     * 
     * @param string $name 迁移名称
     * @return string
     */
    private function getMigrationClassName(string $name): string
    {
        // 将下划线命名转换为驼峰命名
        $parts = explode('_', $name);
        $parts = array_map('ucfirst', $parts);
        return implode('', $parts);
    }

    /**
     * 创建迁移文件模板
     * 
     * @param string $name 迁移名称
     * @return string 创建的文件路径
     */
    public function create(string $name): string
    {
        $path = $this->getMigrationsPath();
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        $timestamp = date('Y_m_d_His');
        $filename = $timestamp . '_' . $name . '.php';
        $filepath = $path . '/' . $filename;

        $className = $this->getMigrationClassName($name);

        $template = <<<PHP
<?php
/**
 * 迁移: {$name}
 * 创建时间: {$timestamp}
 */
class {$className}
{
    private \$pdo;

    public function __construct(PDO \$pdo)
    {
        \$this->pdo = \$pdo;
    }

    /**
     * 执行迁移
     */
    public function up()
    {
        // TODO: 实现迁移逻辑
        // 示例:
        // \$this->pdo->exec("CREATE TABLE example (...)");
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        // TODO: 实现回滚逻辑
        // 示例:
        // \$this->pdo->exec("DROP TABLE IF EXISTS example");
    }
}
PHP;

        file_put_contents($filepath, $template);
        return $filepath;
    }
}

