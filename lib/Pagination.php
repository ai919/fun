<?php
/**
 * 分页组件
 * 
 * 提供统一的分页逻辑和 HTML 渲染
 */
class Pagination
{
    private int $currentPage;
    private int $totalPages;
    private int $perPage;
    private int $totalItems;
    private string $baseUrl;
    private array $queryParams;
    
    /**
     * 构造函数
     * 
     * @param int $currentPage 当前页码
     * @param int $totalItems 总记录数
     * @param int $perPage 每页显示数量
     * @param string $baseUrl 基础 URL（不含查询参数）
     * @param array $queryParams 额外的查询参数
     */
    public function __construct(
        int $currentPage,
        int $totalItems,
        int $perPage = 20,
        string $baseUrl = '',
        array $queryParams = []
    ) {
        $this->currentPage = max(1, $currentPage);
        $this->totalItems = max(0, $totalItems);
        $this->perPage = max(1, $perPage);
        $this->totalPages = max(1, (int)ceil($this->totalItems / $this->perPage));
        
        // 确保当前页不超过总页数
        if ($this->currentPage > $this->totalPages) {
            $this->currentPage = $this->totalPages;
        }
        
        $this->baseUrl = $baseUrl ?: ($_SERVER['PHP_SELF'] ?? '');
        $this->queryParams = $queryParams;
    }
    
    /**
     * 获取当前页码
     */
    public function getCurrentPage(): int
    {
        return $this->currentPage;
    }
    
    /**
     * 获取总页数
     */
    public function getTotalPages(): int
    {
        return $this->totalPages;
    }
    
    /**
     * 获取每页显示数量
     */
    public function getPerPage(): int
    {
        return $this->perPage;
    }
    
    /**
     * 获取总记录数
     */
    public function getTotalItems(): int
    {
        return $this->totalItems;
    }
    
    /**
     * 获取偏移量（用于 SQL LIMIT）
     */
    public function getOffset(): int
    {
        return ($this->currentPage - 1) * $this->perPage;
    }
    
    /**
     * 是否有上一页
     */
    public function hasPrevious(): bool
    {
        return $this->currentPage > 1;
    }
    
    /**
     * 是否有下一页
     */
    public function hasNext(): bool
    {
        return $this->currentPage < $this->totalPages;
    }
    
    /**
     * 获取上一页页码
     */
    public function getPreviousPage(): ?int
    {
        return $this->hasPrevious() ? $this->currentPage - 1 : null;
    }
    
    /**
     * 获取下一页页码
     */
    public function getNextPage(): ?int
    {
        return $this->hasNext() ? $this->currentPage + 1 : null;
    }
    
    /**
     * 生成指定页码的 URL
     * 
     * @param int $page 页码
     * @return string
     */
    public function getPageUrl(int $page): string
    {
        $params = array_merge($this->queryParams, ['page' => $page]);
        $queryString = http_build_query($params);
        return $this->baseUrl . ($queryString ? '?' . $queryString : '');
    }
    
    /**
     * 获取页码范围（用于显示）
     * 
     * @param int $range 每边显示的页码数量
     * @return array
     */
    public function getPageRange(int $range = 2): array
    {
        $start = max(1, $this->currentPage - $range);
        $end = min($this->totalPages, $this->currentPage + $range);
        
        $pages = [];
        for ($i = $start; $i <= $end; $i++) {
            $pages[] = $i;
        }
        
        return $pages;
    }
    
    /**
     * 渲染分页 HTML（简单样式）
     * 
     * @param string $class 额外的 CSS 类名
     * @return string
     */
    public function render(string $class = ''): string
    {
        if ($this->totalPages <= 1) {
            return '';
        }
        
        $html = '<div class="pagination' . ($class ? ' ' . htmlspecialchars($class) : '') . '">';
        $html .= '<span class="pagination-info">第 ' . $this->currentPage . ' / ' . $this->totalPages . ' 页</span>';
        
        if ($this->hasPrevious()) {
            $html .= '<a href="' . htmlspecialchars($this->getPageUrl($this->getPreviousPage())) . '" class="pagination-link pagination-prev">← 上一页</a>';
        }
        
        if ($this->hasNext()) {
            $html .= '<a href="' . htmlspecialchars($this->getPageUrl($this->getNextPage())) . '" class="pagination-link pagination-next">下一页 →</a>';
        }
        
        $html .= '</div>';
        
        return $html;
    }
    
    /**
     * 渲染分页 HTML（带页码列表）
     * 
     * @param int $range 每边显示的页码数量
     * @param string $class 额外的 CSS 类名
     * @return string
     */
    public function renderWithPages(int $range = 2, string $class = ''): string
    {
        if ($this->totalPages <= 1) {
            return '';
        }
        
        $html = '<div class="pagination pagination-full' . ($class ? ' ' . htmlspecialchars($class) : '') . '">';
        
        // 首页
        if ($this->currentPage > 1) {
            $html .= '<a href="' . htmlspecialchars($this->getPageUrl(1)) . '" class="pagination-link">首页</a>';
        }
        
        // 上一页
        if ($this->hasPrevious()) {
            $html .= '<a href="' . htmlspecialchars($this->getPageUrl($this->getPreviousPage())) . '" class="pagination-link">←</a>';
        }
        
        // 页码列表
        $pages = $this->getPageRange($range);
        foreach ($pages as $page) {
            if ($page === $this->currentPage) {
                $html .= '<span class="pagination-current">' . $page . '</span>';
            } else {
                $html .= '<a href="' . htmlspecialchars($this->getPageUrl($page)) . '" class="pagination-link">' . $page . '</a>';
            }
        }
        
        // 下一页
        if ($this->hasNext()) {
            $html .= '<a href="' . htmlspecialchars($this->getPageUrl($this->getNextPage())) . '" class="pagination-link">→</a>';
        }
        
        // 末页
        if ($this->currentPage < $this->totalPages) {
            $html .= '<a href="' . htmlspecialchars($this->getPageUrl($this->totalPages)) . '" class="pagination-link">末页</a>';
        }
        
        $html .= '<span class="pagination-info">共 ' . $this->totalItems . ' 条</span>';
        $html .= '</div>';
        
        return $html;
    }
}

