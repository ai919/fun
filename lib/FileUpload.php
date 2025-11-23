<?php
/**
 * 文件上传处理类
 * 
 * 提供安全的文件上传功能
 */
class FileUpload
{
    private string $uploadDir;
    private array $allowedTypes;
    private int $maxSize;
    private bool $createSubdirs;
    
    /**
     * 构造函数
     * 
     * @param string $uploadDir 上传目录
     * @param array $allowedTypes 允许的 MIME 类型
     * @param int $maxSize 最大文件大小（字节）
     * @param bool $createSubdirs 是否按日期创建子目录
     */
    public function __construct(
        string $uploadDir,
        array $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'],
        int $maxSize = 5242880, // 5MB
        bool $createSubdirs = true
    ) {
        $this->uploadDir = rtrim($uploadDir, '/');
        $this->allowedTypes = $allowedTypes;
        $this->maxSize = $maxSize;
        $this->createSubdirs = $createSubdirs;
        
        // 确保上传目录存在
        if (!is_dir($this->uploadDir)) {
            mkdir($this->uploadDir, 0755, true);
        }
    }
    
    /**
     * 上传文件
     * 
     * @param array $file $_FILES 数组项
     * @param string|null $filename 自定义文件名（不含扩展名）
     * @return array ['success' => bool, 'path' => string, 'message' => string]
     */
    public function upload(array $file, ?string $filename = null): array
    {
        // 检查上传错误
        if (!isset($file['error']) || $file['error'] !== UPLOAD_ERR_OK) {
            return [
                'success' => false,
                'path' => '',
                'message' => $this->getUploadErrorMessage($file['error'] ?? UPLOAD_ERR_NO_FILE)
            ];
        }
        
        // 验证文件类型
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $this->allowedTypes, true)) {
            return [
                'success' => false,
                'path' => '',
                'message' => '不允许的文件类型：' . $mimeType
            ];
        }
        
        // 验证文件大小
        if ($file['size'] > $this->maxSize) {
            $maxSizeMB = round($this->maxSize / 1024 / 1024, 2);
            return [
                'success' => false,
                'path' => '',
                'message' => '文件大小不能超过 ' . $maxSizeMB . ' MB'
            ];
        }
        
        // 生成文件名
        $extension = $this->getExtensionFromMime($mimeType) ?: pathinfo($file['name'], PATHINFO_EXTENSION);
        if (empty($extension)) {
            return [
                'success' => false,
                'path' => '',
                'message' => '无法确定文件扩展名'
            ];
        }
        
        $filename = $filename ?: $this->generateFilename();
        $filename = $this->sanitizeFilename($filename) . '.' . $extension;
        
        // 确定保存目录
        $saveDir = $this->uploadDir;
        if ($this->createSubdirs) {
            $saveDir .= '/' . date('Y/m');
            if (!is_dir($saveDir)) {
                mkdir($saveDir, 0755, true);
            }
        }
        
        // 确保文件名唯一
        $filePath = $saveDir . '/' . $filename;
        $counter = 1;
        while (file_exists($filePath)) {
            $nameWithoutExt = pathinfo($filename, PATHINFO_FILENAME);
            $ext = pathinfo($filename, PATHINFO_EXTENSION);
            $filename = $nameWithoutExt . '_' . $counter . '.' . $ext;
            $filePath = $saveDir . '/' . $filename;
            $counter++;
        }
        
        // 移动文件
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            return [
                'success' => false,
                'path' => '',
                'message' => '文件保存失败'
            ];
        }
        
        // 返回相对路径（相对于项目根目录）
        $relativePath = str_replace(__DIR__ . '/../', '', $filePath);
        $relativePath = str_replace('\\', '/', $relativePath);
        
        return [
            'success' => true,
            'path' => '/' . ltrim($relativePath, '/'),
            'message' => '上传成功'
        ];
    }
    
    /**
     * 删除文件
     * 
     * @param string $path 文件路径（相对或绝对）
     * @return bool
     */
    public function delete(string $path): bool
    {
        // 如果是相对路径，转换为绝对路径
        if (substr($path, 0, 1) !== '/' || !file_exists($path)) {
            $path = __DIR__ . '/../' . ltrim($path, '/');
        }
        
        if (file_exists($path) && is_file($path)) {
            return @unlink($path);
        }
        
        return false;
    }
    
    /**
     * 获取上传错误消息
     */
    private function getUploadErrorMessage(int $error): string
    {
        return match($error) {
            UPLOAD_ERR_INI_SIZE => '文件大小超过服务器限制',
            UPLOAD_ERR_FORM_SIZE => '文件大小超过表单限制',
            UPLOAD_ERR_PARTIAL => '文件只上传了一部分',
            UPLOAD_ERR_NO_FILE => '没有选择文件',
            UPLOAD_ERR_NO_TMP_DIR => '临时目录不存在',
            UPLOAD_ERR_CANT_WRITE => '文件写入失败',
            UPLOAD_ERR_EXTENSION => '文件上传被扩展阻止',
            default => '未知的上传错误'
        };
    }
    
    /**
     * 从 MIME 类型获取扩展名
     */
    private function getExtensionFromMime(string $mimeType): string
    {
        $map = [
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            'image/gif' => 'gif',
            'image/webp' => 'webp',
            'image/svg+xml' => 'svg',
            'application/pdf' => 'pdf',
            'text/plain' => 'txt',
        ];
        
        return $map[$mimeType] ?? '';
    }
    
    /**
     * 生成唯一文件名
     */
    private function generateFilename(): string
    {
        return date('YmdHis') . '_' . bin2hex(random_bytes(4));
    }
    
    /**
     * 清理文件名（移除危险字符）
     */
    private function sanitizeFilename(string $filename): string
    {
        // 移除路径分隔符和其他危险字符
        $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $filename);
        // 限制长度
        $filename = mb_substr($filename, 0, 100, 'UTF-8');
        return $filename;
    }
}

