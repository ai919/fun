<?php
/**
 * 图片处理辅助类
 * 
 * 提供图片缩放、裁剪、格式转换等功能
 */
class ImageHelper
{
    /**
     * 缩放图片
     * 
     * @param string $sourcePath 源图片路径
     * @param string $destPath 目标图片路径
     * @param int $maxWidth 最大宽度
     * @param int $maxHeight 最大高度
     * @param bool $keepRatio 是否保持宽高比
     * @param int $quality 图片质量（1-100，仅 JPEG）
     * @return bool 是否成功
     */
    public static function resize(
        string $sourcePath,
        string $destPath,
        int $maxWidth,
        int $maxHeight,
        bool $keepRatio = true,
        int $quality = 85
    ): bool {
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }
        
        [$srcWidth, $srcHeight, $type] = $imageInfo;
        
        // 计算目标尺寸
        if ($keepRatio) {
            $ratio = min($maxWidth / $srcWidth, $maxHeight / $srcHeight);
            $dstWidth = (int)($srcWidth * $ratio);
            $dstHeight = (int)($srcHeight * $ratio);
        } else {
            $dstWidth = $maxWidth;
            $dstHeight = $maxHeight;
        }
        
        // 创建源图片资源
        $srcImage = self::createImageFromFile($sourcePath, $type);
        if ($srcImage === false) {
            return false;
        }
        
        // 创建目标图片资源
        $dstImage = imagecreatetruecolor($dstWidth, $dstHeight);
        
        // 保持透明度（PNG/GIF）
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefill($dstImage, 0, 0, $transparent);
        }
        
        // 缩放
        imagecopyresampled(
            $dstImage, $srcImage,
            0, 0, 0, 0,
            $dstWidth, $dstHeight,
            $srcWidth, $srcHeight
        );
        
        // 保存
        $result = self::saveImage($dstImage, $destPath, $type, $quality);
        
        // 清理资源
        imagedestroy($srcImage);
        imagedestroy($dstImage);
        
        return $result;
    }
    
    /**
     * 裁剪图片
     * 
     * @param string $sourcePath 源图片路径
     * @param string $destPath 目标图片路径
     * @param int $x 起始 X 坐标
     * @param int $y 起始 Y 坐标
     * @param int $width 裁剪宽度
     * @param int $height 裁剪高度
     * @param int $quality 图片质量
     * @return bool 是否成功
     */
    public static function crop(
        string $sourcePath,
        string $destPath,
        int $x,
        int $y,
        int $width,
        int $height,
        int $quality = 85
    ): bool {
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }
        
        [$srcWidth, $srcHeight, $type] = $imageInfo;
        
        // 验证裁剪区域
        if ($x < 0 || $y < 0 || $x + $width > $srcWidth || $y + $height > $srcHeight) {
            return false;
        }
        
        // 创建源图片资源
        $srcImage = self::createImageFromFile($sourcePath, $type);
        if ($srcImage === false) {
            return false;
        }
        
        // 创建目标图片资源
        $dstImage = imagecreatetruecolor($width, $height);
        
        // 保持透明度
        if ($type === IMAGETYPE_PNG || $type === IMAGETYPE_GIF) {
            imagealphablending($dstImage, false);
            imagesavealpha($dstImage, true);
            $transparent = imagecolorallocatealpha($dstImage, 255, 255, 255, 127);
            imagefill($dstImage, 0, 0, $transparent);
        }
        
        // 裁剪
        imagecopyresampled(
            $dstImage, $srcImage,
            0, 0, $x, $y,
            $width, $height,
            $width, $height
        );
        
        // 保存
        $result = self::saveImage($dstImage, $destPath, $type, $quality);
        
        // 清理资源
        imagedestroy($srcImage);
        imagedestroy($dstImage);
        
        return $result;
    }
    
    /**
     * 转换为 WebP 格式
     * 
     * @param string $sourcePath 源图片路径
     * @param string $destPath 目标图片路径
     * @param int $quality 图片质量（0-100）
     * @return bool 是否成功
     */
    public static function convertToWebP(string $sourcePath, string $destPath, int $quality = 80): bool
    {
        if (!function_exists('imagewebp')) {
            return false;
        }
        
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }
        
        [, , $type] = $imageInfo;
        
        $srcImage = self::createImageFromFile($sourcePath, $type);
        if ($srcImage === false) {
            return false;
        }
        
        // 确保目录存在
        $dir = dirname($destPath);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $result = imagewebp($srcImage, $destPath, $quality);
        imagedestroy($srcImage);
        
        return $result;
    }
    
    /**
     * 生成缩略图
     * 
     * @param string $sourcePath 源图片路径
     * @param string $destPath 目标图片路径
     * @param int $thumbSize 缩略图尺寸（正方形）
     * @param int $quality 图片质量
     * @return bool 是否成功
     */
    public static function createThumbnail(
        string $sourcePath,
        string $destPath,
        int $thumbSize = 200,
        int $quality = 85
    ): bool {
        if (!file_exists($sourcePath)) {
            return false;
        }
        
        $imageInfo = @getimagesize($sourcePath);
        if ($imageInfo === false) {
            return false;
        }
        
        [$srcWidth, $srcHeight, $type] = $imageInfo;
        
        // 计算裁剪区域（居中裁剪）
        $size = min($srcWidth, $srcHeight);
        $x = (int)(($srcWidth - $size) / 2);
        $y = (int)(($srcHeight - $size) / 2);
        
        // 先裁剪成正方形
        $tempPath = $destPath . '.temp';
        if (!self::crop($sourcePath, $tempPath, $x, $y, $size, $size, $quality)) {
            return false;
        }
        
        // 再缩放
        $result = self::resize($tempPath, $destPath, $thumbSize, $thumbSize, false, $quality);
        
        // 删除临时文件
        @unlink($tempPath);
        
        return $result;
    }
    
    /**
     * 从文件创建图片资源
     */
    private static function createImageFromFile(string $path, int $type)
    {
        return match($type) {
            IMAGETYPE_JPEG => @imagecreatefromjpeg($path),
            IMAGETYPE_PNG => @imagecreatefrompng($path),
            IMAGETYPE_GIF => @imagecreatefromgif($path),
            IMAGETYPE_WEBP => function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : false,
            default => false
        };
    }
    
    /**
     * 保存图片
     */
    private static function saveImage($image, string $path, int $type, int $quality): bool
    {
        // 确保目录存在
        $dir = dirname($path);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        return match($type) {
            IMAGETYPE_JPEG => @imagejpeg($image, $path, $quality),
            IMAGETYPE_PNG => @imagepng($image, $path, (int)(9 - ($quality / 100) * 9)),
            IMAGETYPE_GIF => @imagegif($image, $path),
            IMAGETYPE_WEBP => function_exists('imagewebp') ? @imagewebp($image, $path, $quality) : false,
            default => false
        };
    }
    
    /**
     * 获取图片信息
     * 
     * @param string $path 图片路径
     * @return array|null ['width' => int, 'height' => int, 'type' => string, 'mime' => string, 'size' => int]
     */
    public static function getInfo(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }
        
        $imageInfo = @getimagesize($path);
        if ($imageInfo === false) {
            return null;
        }
        
        [$width, $height, $type, $mime, , , $size] = array_merge($imageInfo, [filesize($path)]);
        
        $typeNames = [
            IMAGETYPE_JPEG => 'JPEG',
            IMAGETYPE_PNG => 'PNG',
            IMAGETYPE_GIF => 'GIF',
            IMAGETYPE_WEBP => 'WebP',
        ];
        
        return [
            'width' => $width,
            'height' => $height,
            'type' => $typeNames[$type] ?? 'Unknown',
            'mime' => $mime,
            'size' => $size
        ];
    }
}

