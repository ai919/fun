-- ============================================
-- 字段类型优化：支持小数分数（问题 12）
-- 执行前请先备份数据库！
-- ============================================
-- 
-- 问题描述：
-- - test_runs.total_score 使用 int(11)，但 ScoreEngine 返回 float
-- - test_run_scores.score_value 也是 int(11)，但维度分数未来可能是小数
-- - results.min_score/max_score 也是 int(11)，需要与 total_score 类型一致
--
-- 优化原因：
-- - 当前权重都是整数（+2, +1, -1, -2），暂时不会出错
-- - 但未来可能使用：
--   * 题目权重系数（如 0.5×）
--   * Z-Score 标准化
--   * 维度百分比保留两位小数
--   * 多重矩阵映射（如 Big Five 机制）
-- - 如果使用 int，小数会被截断，导致隐性 bug
--
-- 解决方案：
-- 将所有分数相关字段改为 DECIMAL(10,2)，支持两位小数
-- ============================================

-- ============================================
-- 使用存储过程检查字段类型，避免重复执行
-- ============================================
DELIMITER $$

DROP PROCEDURE IF EXISTS modify_column_if_needed$$
CREATE PROCEDURE modify_column_if_needed(
    IN p_table_name VARCHAR(64),
    IN p_column_name VARCHAR(64),
    IN p_new_definition TEXT
)
BEGIN
    DECLARE v_current_type VARCHAR(255) DEFAULT '';
    DECLARE v_is_nullable VARCHAR(3) DEFAULT '';
    DECLARE v_column_default TEXT DEFAULT NULL;
    
    -- 获取当前字段信息
    SELECT 
        COLUMN_TYPE,
        IS_NULLABLE,
        COLUMN_DEFAULT
    INTO 
        v_current_type,
        v_is_nullable,
        v_column_default
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
      AND table_name = p_table_name
      AND column_name = p_column_name;
    
    -- 检查是否需要修改（如果已经是 DECIMAL(10,2) 则跳过）
    IF v_current_type NOT LIKE 'decimal(10,2)' THEN
        SET @sql = CONCAT('ALTER TABLE `', p_table_name, '` MODIFY COLUMN `', p_column_name, '` ', p_new_definition);
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✓ 字段 ', p_table_name, '.', p_column_name, ' 已修改为 ', p_new_definition) AS message;
    ELSE
        SELECT CONCAT('○ 字段 ', p_table_name, '.', p_column_name, ' 已经是 DECIMAL(10,2)，跳过') AS message;
    END IF;
END$$

DELIMITER ;

-- ============================================
-- 执行字段类型修改
-- ============================================

-- 1. test_runs.total_score: int(11) -> DECIMAL(10,2) NULL
-- 注意：原schema中 total_score 是 DEFAULT NULL，保持这个特性
CALL modify_column_if_needed('test_runs', 'total_score', 'DECIMAL(10,2) DEFAULT NULL');

-- 2. test_run_scores.score_value: int(11) -> DECIMAL(10,2) NOT NULL DEFAULT 0
CALL modify_column_if_needed('test_run_scores', 'score_value', 'DECIMAL(10,2) NOT NULL DEFAULT 0');

-- 3. results.min_score: int(11) -> DECIMAL(10,2) NULL
-- 保持可为空，因为某些结果可能不需要分数范围
CALL modify_column_if_needed('results', 'min_score', 'DECIMAL(10,2) DEFAULT NULL');

-- 4. results.max_score: int(11) -> DECIMAL(10,2) NULL
CALL modify_column_if_needed('results', 'max_score', 'DECIMAL(10,2) DEFAULT NULL');

-- ============================================
-- 清理临时存储过程
-- ============================================
DROP PROCEDURE IF EXISTS modify_column_if_needed;

-- ============================================
-- 验证字段类型是否修改成功
-- ============================================
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE table_schema = DATABASE()
  AND (
    (TABLE_NAME = 'test_runs' AND COLUMN_NAME = 'total_score')
    OR (TABLE_NAME = 'test_run_scores' AND COLUMN_NAME = 'score_value')
    OR (TABLE_NAME = 'results' AND COLUMN_NAME IN ('min_score', 'max_score'))
  )
ORDER BY TABLE_NAME, COLUMN_NAME;

-- ============================================
-- 注意事项：
-- 1. 执行前请备份数据库
-- 2. 如果表中已有大量数据，ALTER 操作可能需要一些时间
-- 3. 修改后，现有整数数据会自动转换为 DECIMAL（如 100 -> 100.00）
-- 4. 代码中已经使用 (float) 转换，所以不需要修改 PHP 代码
-- ============================================

