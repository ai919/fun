<?php
/**
 * 迁移: 添加 play_count_beautified 字段到 tests 表
 * 创建时间: 2024_12_19_143000
 * 
 * 功能: 为 tests 表添加 play_count_beautified 字段，用于存储美化后的播放次数
 */
class AddPlayCountBeautifiedToTests
{
    private $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 执行迁移
     */
    public function up()
    {
        // 检查字段是否已存在
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `tests` LIKE 'play_count_beautified'");
        if ($stmt->rowCount() > 0) {
            // 字段已存在，跳过
            return;
        }

        // 添加字段
        $this->pdo->exec("
            ALTER TABLE `tests`
            ADD COLUMN `play_count_beautified` INT UNSIGNED NULL DEFAULT NULL AFTER `display_mode`
        ");
    }

    /**
     * 回滚迁移
     */
    public function down()
    {
        // 检查字段是否存在
        $stmt = $this->pdo->query("SHOW COLUMNS FROM `tests` LIKE 'play_count_beautified'");
        if ($stmt->rowCount() > 0) {
            // 删除字段
            $this->pdo->exec("ALTER TABLE `tests` DROP COLUMN `play_count_beautified`");
        }
    }
}

