-- ============================================
-- 字段长度优化（问题 14）
-- 执行前请先备份数据库！
-- ============================================
-- 
-- 问题描述：
-- 1. users.email 使用 VARCHAR(255)，但实际作为用户名使用，规则是 3-25 位，过长
-- 2. test_runs.share_token 使用 CHAR(16)，但代码生成的是 16 字符（8字节的十六进制）
--    为了更好的安全性和唯一性，建议使用 32 字符（16字节的十六进制）
--
-- 优化原因：
-- 1. users.email：虽然字段名叫 email，但实际用作用户名（见 lib/user_auth.php:16-20）
--    - 用户名规则：3-25 位英文和数字组合
--    - VARCHAR(255) 过长，浪费存储空间和索引空间
--    - 建议改为 VARCHAR(50)，足够容纳用户名并留有余量
--
-- 2. test_runs.share_token：当前代码生成 16 字符（bin2hex(random_bytes(8))）
--    - 16 字符的 token 在大量数据时碰撞概率较高
--    - 建议改为 32 字符（bin2hex(random_bytes(16))），提高安全性和唯一性
--    - 注意：修改后需要同步更新代码中的生成逻辑
--
-- 注意事项：
-- - 修改字段长度前需要确保现有数据符合新长度要求
-- - share_token 改为 32 字符后，需要更新代码生成逻辑
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
    DECLARE v_new_type VARCHAR(255) DEFAULT '';
    
    -- 获取当前字段类型
    SELECT COLUMN_TYPE INTO v_current_type
    FROM information_schema.COLUMNS
    WHERE table_schema = DATABASE()
      AND table_name = p_table_name
      AND column_name = p_column_name;
    
    -- 从新定义中提取类型（简单提取，假设格式为 "类型(长度)"）
    SET v_new_type = SUBSTRING_INDEX(p_new_definition, ' ', 1);
    
    -- 如果类型不同，执行修改
    IF v_current_type != v_new_type THEN
        SET @sql = CONCAT(
            'ALTER TABLE `', p_table_name, '` ',
            'MODIFY COLUMN `', p_column_name, '` ', p_new_definition
        );
        PREPARE stmt FROM @sql;
        EXECUTE stmt;
        DEALLOCATE PREPARE stmt;
        SELECT CONCAT('✓ 字段 ', p_table_name, '.', p_column_name, ' 已修改为 ', v_new_type) AS message;
    ELSE
        SELECT CONCAT('○ 字段 ', p_table_name, '.', p_column_name, ' 已经是 ', v_current_type, '，跳过') AS message;
    END IF;
END$$

DELIMITER ;

-- ============================================
-- 执行字段长度优化
-- ============================================

-- 1. 优化 users.email：从 VARCHAR(255) 改为 VARCHAR(50)
-- 用户名规则是 3-25 位，VARCHAR(50) 足够并留有余量
CALL modify_column_if_needed(
    'users',
    'email',
    'VARCHAR(50) COLLATE utf8mb4_unicode_ci NOT NULL'
);

-- 2. 优化 test_runs.share_token：从 CHAR(16) 改为 CHAR(32)
-- 当前代码生成 16 字符，但为了更好的安全性和唯一性，建议使用 32 字符
-- 注意：修改后需要更新代码中的生成逻辑为 bin2hex(random_bytes(16))
CALL modify_column_if_needed(
    'test_runs',
    'share_token',
    'CHAR(32) COLLATE utf8mb4_unicode_ci DEFAULT NULL'
);

-- ============================================
-- 清理临时存储过程
-- ============================================
DROP PROCEDURE IF EXISTS modify_column_if_needed;

-- ============================================
-- 验证字段长度是否修改成功
-- ============================================
SELECT 
    TABLE_NAME,
    COLUMN_NAME,
    COLUMN_TYPE,
    CHARACTER_MAXIMUM_LENGTH,
    IS_NULLABLE,
    COLUMN_DEFAULT
FROM information_schema.COLUMNS
WHERE table_schema = DATABASE()
  AND (
    (TABLE_NAME = 'users' AND COLUMN_NAME = 'email')
    OR (TABLE_NAME = 'test_runs' AND COLUMN_NAME = 'share_token')
  )
ORDER BY TABLE_NAME, COLUMN_NAME;

-- ============================================
-- 检查现有数据是否符合新长度要求
-- ============================================

-- 检查 users.email 是否有超过 50 字符的数据
SELECT 
    COUNT(*) AS over_length_count,
    MAX(CHAR_LENGTH(email)) AS max_length
FROM users
WHERE CHAR_LENGTH(email) > 50;

-- 检查 test_runs.share_token 是否有超过 32 字符的数据
SELECT 
    COUNT(*) AS over_length_count,
    MAX(CHAR_LENGTH(share_token)) AS max_length
FROM test_runs
WHERE share_token IS NOT NULL AND CHAR_LENGTH(share_token) > 32;

-- ============================================
-- 注意事项：
-- 1. 执行前请备份数据库
-- 2. 如果检查发现超过新长度的数据，需要先清理或修复
-- 3. share_token 改为 32 字符后，需要更新代码：
--    - submit.php:121 改为 bin2hex(random_bytes(16))
--    - 或者保持 16 字符，但需要确保代码和数据库一致
-- 4. 如果选择保持 16 字符，可以只优化 users.email
-- ============================================

