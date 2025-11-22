<?php
/**
 * Constants Class
 * 
 * 定义系统中使用的常量值，避免硬编码魔法值
 */
class Constants
{
    // ==================== 测试状态 ====================
    
    /** 草稿状态 */
    const TEST_STATUS_DRAFT = 'draft';
    
    /** 已发布状态 */
    const TEST_STATUS_PUBLISHED = 'published';
    
    /** 已归档状态 */
    const TEST_STATUS_ARCHIVED = 'archived';
    
    /**
     * 获取所有测试状态列表
     * @return array
     */
    public static function getTestStatuses(): array
    {
        return [
            self::TEST_STATUS_DRAFT,
            self::TEST_STATUS_PUBLISHED,
            self::TEST_STATUS_ARCHIVED,
        ];
    }
    
    /**
     * 获取测试状态的中文标签
     * @return array
     */
    public static function getTestStatusLabels(): array
    {
        return [
            self::TEST_STATUS_DRAFT     => '草稿',
            self::TEST_STATUS_PUBLISHED  => '已发布',
            self::TEST_STATUS_ARCHIVED   => '已归档',
        ];
    }
    
    /**
     * 将数字状态值转换为字符串状态值（兼容旧数据）
     * @param string|int $statusValue
     * @return string
     */
    public static function normalizeTestStatus($statusValue): string
    {
        if (is_numeric($statusValue)) {
            $map = [
                '0' => self::TEST_STATUS_DRAFT,
                '1' => self::TEST_STATUS_PUBLISHED,
                '2' => self::TEST_STATUS_ARCHIVED,
            ];
            return $map[(string)$statusValue] ?? self::TEST_STATUS_DRAFT;
        }
        return (string)$statusValue;
    }
    
    // ==================== 评分模式 ====================
    
    /** 简单评分模式（单结果） */
    const SCORING_MODE_SIMPLE = 'simple';
    
    /** 维度评分模式（维度组合） */
    const SCORING_MODE_DIMENSIONS = 'dimensions';
    
    /** 区间评分模式 */
    const SCORING_MODE_RANGE = 'range';
    
    /** 自定义评分模式 */
    const SCORING_MODE_CUSTOM = 'custom';
    
    /**
     * 获取所有评分模式列表
     * @return array
     */
    public static function getScoringModes(): array
    {
        return [
            self::SCORING_MODE_SIMPLE,
            self::SCORING_MODE_DIMENSIONS,
            self::SCORING_MODE_RANGE,
            self::SCORING_MODE_CUSTOM,
        ];
    }
    
    /**
     * 获取评分模式的中文标签
     * @return array
     */
    public static function getScoringModeLabels(): array
    {
        return [
            self::SCORING_MODE_SIMPLE     => 'Simple（单结果）',
            self::SCORING_MODE_DIMENSIONS => 'Dimensions（维度组合）',
            self::SCORING_MODE_RANGE      => 'Range（区间）',
            self::SCORING_MODE_CUSTOM     => 'Custom（自定义）',
        ];
    }
    
    // ==================== 显示模式 ====================
    
    /** 单页显示模式 */
    const DISPLAY_MODE_SINGLE_PAGE = 'single_page';
    
    /** 分步显示模式 */
    const DISPLAY_MODE_STEP_BY_STEP = 'step_by_step';
    
    /**
     * 获取所有显示模式列表
     * @return array
     */
    public static function getDisplayModes(): array
    {
        return [
            self::DISPLAY_MODE_SINGLE_PAGE,
            self::DISPLAY_MODE_STEP_BY_STEP,
        ];
    }
    
    // ==================== Token 生成 ====================
    
    /** 分享 Token 的字节长度（生成后为 32 字符的十六进制字符串） */
    const SHARE_TOKEN_BYTES = 16;
    
    /** CSRF Token 的字节长度（生成后为 64 字符的十六进制字符串） */
    const CSRF_TOKEN_BYTES = 32;
    
    /** Token 生成最大重试次数 */
    const TOKEN_GENERATION_MAX_RETRIES = 5;
    
    // ==================== 备份状态 ====================
    
    /** 备份成功状态 */
    const BACKUP_STATUS_SUCCESS = 'success';
    
    /** 备份失败状态 */
    const BACKUP_STATUS_FAILED = 'failed';
    
    /**
     * 获取所有备份状态列表
     * @return array
     */
    public static function getBackupStatuses(): array
    {
        return [
            self::BACKUP_STATUS_SUCCESS,
            self::BACKUP_STATUS_FAILED,
        ];
    }
}

