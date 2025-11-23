<?php
/**
 * 数据验证器
 * 
 * 提供常用的数据验证方法
 */
class Validator
{
    /**
     * 验证必填字段
     * 
     * @param mixed $value 值
     * @param string $fieldName 字段名（用于错误消息）
     * @return array ['valid' => bool, 'message' => string]
     */
    public static function required(mixed $value, string $fieldName = '字段'): array
    {
        if ($value === null || $value === '' || (is_array($value) && empty($value))) {
            return ['valid' => false, 'message' => $fieldName . '不能为空'];
        }
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 验证字符串长度
     * 
     * @param string $value 值
     * @param int $min 最小长度
     * @param int $max 最大长度
     * @param string $fieldName 字段名
     * @return array
     */
    public static function length(string $value, int $min = 0, int $max = PHP_INT_MAX, string $fieldName = '字段'): array
    {
        $len = mb_strlen($value, 'UTF-8');
        if ($len < $min) {
            return ['valid' => false, 'message' => $fieldName . '长度不能少于 ' . $min . ' 个字符'];
        }
        if ($len > $max) {
            return ['valid' => false, 'message' => $fieldName . '长度不能超过 ' . $max . ' 个字符'];
        }
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 验证邮箱
     * 
     * @param string $value 值
     * @param string $fieldName 字段名
     * @return array
     */
    public static function email(string $value, string $fieldName = '邮箱'): array
    {
        if (!filter_var($value, FILTER_VALIDATE_EMAIL)) {
            return ['valid' => false, 'message' => $fieldName . '格式不正确'];
        }
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 验证 URL
     * 
     * @param string $value 值
     * @param string $fieldName 字段名
     * @return array
     */
    public static function url(string $value, string $fieldName = 'URL'): array
    {
        if (!filter_var($value, FILTER_VALIDATE_URL)) {
            return ['valid' => false, 'message' => $fieldName . '格式不正确'];
        }
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 验证整数
     * 
     * @param mixed $value 值
     * @param int|null $min 最小值
     * @param int|null $max 最大值
     * @param string $fieldName 字段名
     * @return array
     */
    public static function integer(mixed $value, ?int $min = null, ?int $max = null, string $fieldName = '字段'): array
    {
        if (!is_numeric($value) || (int)$value != $value) {
            return ['valid' => false, 'message' => $fieldName . '必须是整数'];
        }
        
        $intValue = (int)$value;
        if ($min !== null && $intValue < $min) {
            return ['valid' => false, 'message' => $fieldName . '不能小于 ' . $min];
        }
        if ($max !== null && $intValue > $max) {
            return ['valid' => false, 'message' => $fieldName . '不能大于 ' . $max];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 验证数字
     * 
     * @param mixed $value 值
     * @param float|null $min 最小值
     * @param float|null $max 最大值
     * @param string $fieldName 字段名
     * @return array
     */
    public static function numeric(mixed $value, ?float $min = null, ?float $max = null, string $fieldName = '字段'): array
    {
        if (!is_numeric($value)) {
            return ['valid' => false, 'message' => $fieldName . '必须是数字'];
        }
        
        $numValue = (float)$value;
        if ($min !== null && $numValue < $min) {
            return ['valid' => false, 'message' => $fieldName . '不能小于 ' . $min];
        }
        if ($max !== null && $numValue > $max) {
            return ['valid' => false, 'message' => $fieldName . '不能大于 ' . $max];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 验证正则表达式
     * 
     * @param string $value 值
     * @param string $pattern 正则表达式
     * @param string $fieldName 字段名
     * @param string $errorMessage 错误消息
     * @return array
     */
    public static function regex(string $value, string $pattern, string $fieldName = '字段', string $errorMessage = ''): array
    {
        if (!preg_match($pattern, $value)) {
            return ['valid' => false, 'message' => $errorMessage ?: $fieldName . '格式不正确'];
        }
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 验证用户名（字母、数字、下划线，3-25个字符）
     * 
     * @param string $value 值
     * @param string $fieldName 字段名
     * @return array
     */
    public static function username(string $value, string $fieldName = '用户名'): array
    {
        $result = self::length($value, 3, 25, $fieldName);
        if (!$result['valid']) {
            return $result;
        }
        
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $value)) {
            return ['valid' => false, 'message' => $fieldName . '只能包含字母、数字和下划线'];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 验证密码（6-20个字符）
     * 
     * @param string $value 值
     * @param string $fieldName 字段名
     * @return array
     */
    public static function password(string $value, string $fieldName = '密码'): array
    {
        return self::length($value, 6, 20, $fieldName);
    }
    
    /**
     * 验证 slug（URL 友好的字符串）
     * 
     * @param string $value 值
     * @param string $fieldName 字段名
     * @return array
     */
    public static function slug(string $value, string $fieldName = 'Slug'): array
    {
        if (!preg_match('/^[a-z0-9-]+$/', $value)) {
            return ['valid' => false, 'message' => $fieldName . '只能包含小写字母、数字和连字符'];
        }
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 验证日期格式
     * 
     * @param string $value 值
     * @param string $format 日期格式（默认 Y-m-d）
     * @param string $fieldName 字段名
     * @return array
     */
    public static function date(string $value, string $format = 'Y-m-d', string $fieldName = '日期'): array
    {
        $date = DateTime::createFromFormat($format, $value);
        if (!$date || $date->format($format) !== $value) {
            return ['valid' => false, 'message' => $fieldName . '格式不正确'];
        }
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 验证文件类型
     * 
     * @param array $file $_FILES 数组项
     * @param array $allowedTypes 允许的 MIME 类型
     * @param string $fieldName 字段名
     * @return array
     */
    public static function fileType(array $file, array $allowedTypes, string $fieldName = '文件'): array
    {
        if (!isset($file['tmp_name']) || !is_uploaded_file($file['tmp_name'])) {
            return ['valid' => false, 'message' => $fieldName . '上传失败'];
        }
        
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes, true)) {
            return ['valid' => false, 'message' => $fieldName . '类型不允许，允许的类型：' . implode(', ', $allowedTypes)];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 验证文件大小
     * 
     * @param array $file $_FILES 数组项
     * @param int $maxSize 最大大小（字节）
     * @param string $fieldName 字段名
     * @return array
     */
    public static function fileSize(array $file, int $maxSize, string $fieldName = '文件'): array
    {
        if (!isset($file['size'])) {
            return ['valid' => false, 'message' => $fieldName . '大小未知'];
        }
        
        if ($file['size'] > $maxSize) {
            $maxSizeMB = round($maxSize / 1024 / 1024, 2);
            return ['valid' => false, 'message' => $fieldName . '大小不能超过 ' . $maxSizeMB . ' MB'];
        }
        
        return ['valid' => true, 'message' => ''];
    }
    
    /**
     * 批量验证
     * 
     * @param array $data 数据数组
     * @param array $rules 规则数组 ['field' => [['method' => 'required'], ['method' => 'length', 'params' => [3, 20]]]]
     * @return array ['valid' => bool, 'errors' => ['field' => 'error message']]
     */
    public static function validate(array $data, array $rules): array
    {
        $errors = [];
        
        foreach ($rules as $field => $fieldRules) {
            $value = $data[$field] ?? null;
            
            foreach ($fieldRules as $rule) {
                $method = $rule['method'] ?? '';
                $params = $rule['params'] ?? [];
                $fieldName = $rule['fieldName'] ?? $field;
                
                if (!method_exists(self::class, $method)) {
                    continue;
                }
                
                // 将字段名和值插入参数
                $params = array_merge([$value, $fieldName], $params);
                
                $result = call_user_func_array([self::class, $method], $params);
                
                if (!$result['valid']) {
                    $errors[$field] = $result['message'];
                    break; // 一个字段只显示第一个错误
                }
            }
        }
        
        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }
}

