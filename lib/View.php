<?php
/**
 * 轻量级模板系统
 * 
 * 提供简单的模板渲染功能，支持变量替换、循环、条件判断等
 */
class View
{
    private string $templateDir;
    private array $data = [];
    private static ?View $instance = null;
    
    /**
     * 构造函数
     * 
     * @param string $templateDir 模板目录
     */
    public function __construct(string $templateDir = '')
    {
        $this->templateDir = $templateDir ?: __DIR__ . '/../templates';
        
        // 确保模板目录存在
        if (!is_dir($this->templateDir)) {
            mkdir($this->templateDir, 0755, true);
        }
    }
    
    /**
     * 获取单例实例
     */
    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * 设置模板目录
     */
    public function setTemplateDir(string $dir): void
    {
        $this->templateDir = $dir;
    }
    
    /**
     * 设置变量
     * 
     * @param string|array $key 变量名或变量数组
     * @param mixed $value 变量值
     */
    public function assign(string|array $key, mixed $value = null): void
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }
    }
    
    /**
     * 渲染模板
     * 
     * @param string $template 模板文件名（不含扩展名）
     * @param array $data 额外的数据
     * @return string 渲染后的 HTML
     */
    public function render(string $template, array $data = []): string
    {
        $templatePath = $this->templateDir . '/' . $template . '.php';
        
        if (!file_exists($templatePath)) {
            throw new RuntimeException("模板文件不存在: {$templatePath}");
        }
        
        // 合并数据
        $vars = array_merge($this->data, $data);
        
        // 提取变量到当前作用域
        extract($vars, EXTR_SKIP);
        
        // 开始输出缓冲
        ob_start();
        
        try {
            include $templatePath;
        } catch (Throwable $e) {
            ob_end_clean();
            throw $e;
        }
        
        return ob_get_clean();
    }
    
    /**
     * 渲染模板并直接输出
     * 
     * @param string $template 模板文件名
     * @param array $data 额外的数据
     */
    public function display(string $template, array $data = []): void
    {
        echo $this->render($template, $data);
    }
    
    /**
     * 包含子模板
     * 
     * @param string $template 模板文件名
     * @param array $data 额外的数据
     */
    public function include(string $template, array $data = []): void
    {
        $templatePath = $this->templateDir . '/' . $template . '.php';
        
        if (!file_exists($templatePath)) {
            throw new RuntimeException("模板文件不存在: {$templatePath}");
        }
        
        // 合并数据
        $vars = array_merge($this->data, $data);
        extract($vars, EXTR_SKIP);
        
        include $templatePath;
    }
    
    /**
     * 转义 HTML
     * 
     * @param string $string 要转义的字符串
     * @return string
     */
    public function escape(string $string): string
    {
        return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
    }
    
    /**
     * 格式化日期
     * 
     * @param string|int $datetime 日期时间（字符串或时间戳）
     * @param string $format 格式
     * @return string
     */
    public function date(string|int $datetime, string $format = 'Y-m-d H:i:s'): string
    {
        $timestamp = is_numeric($datetime) ? (int)$datetime : strtotime($datetime);
        return date($format, $timestamp);
    }
    
    /**
     * 静态方法：快速渲染
     * 
     * @param string $template 模板文件名
     * @param array $data 数据
     * @return string
     */
    public static function make(string $template, array $data = []): string
    {
        $view = self::getInstance();
        return $view->render($template, $data);
    }
}

