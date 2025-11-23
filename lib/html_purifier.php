<?php
/**
 * HTML 净化器 - 用于安全地显示富文本内容
 * 
 * 允许的标签和属性：
 * - 文本格式：<b>, <strong>, <em>, <i>, <u>, <s>, <strike>
 * - 段落：<p>, <br>
 * - 链接：<a> (href, target, rel)
 * - 图片：<img> (src, alt, style - 仅限安全样式)
 * - 颜色：<span> (style - 仅限 color, background-color)
 */
class HTMLPurifier {
    /**
     * 允许的HTML标签白名单（用于 strip_tags）
     */
    private static $allowedTags = '<b><strong><em><i><u><s><strike><p><br><a><img><span>';

    /**
     * 允许的标签属性
     */
    private static $allowedAttributes = [
        'a' => ['href', 'target', 'rel'],
        'img' => ['src', 'alt', 'style'],
        'span' => ['style']
    ];

    /**
     * 净化HTML内容，只保留白名单中的标签和属性
     * 
     * @param string $html 原始HTML内容
     * @return string 净化后的HTML
     */
    public static function purify($html) {
        if (empty($html)) {
            return '';
        }

        // 先使用 strip_tags 移除所有不允许的标签
        $html = strip_tags($html, self::$allowedTags);
        
        // 使用 DOMDocument 解析和过滤属性（更可靠）
        // 如果解析失败，则使用简单的正则过滤
        if (class_exists('DOMDocument')) {
            $html = self::purifyWithDOM($html);
        } else {
            $html = self::purifyWithRegex($html);
        }
        
        return $html;
    }

    /**
     * 使用 DOMDocument 净化HTML（更可靠）
     * 
     * @param string $html 原始HTML
     * @return string 净化后的HTML
     */
    private static function purifyWithDOM($html) {
        // 限制HTML长度，避免处理过大的内容导致内存问题
        $maxLength = 100000; // 100KB
        if (strlen($html) > $maxLength) {
            $html = mb_substr($html, 0, $maxLength, 'UTF-8');
        }
        
        // 包装在容器中以便解析片段
        $wrapped = '<div>' . $html . '</div>';
        
        // 禁用错误报告（避免警告）
        $libxmlPreviousState = libxml_use_internal_errors(true);
        
        $dom = new DOMDocument('1.0', 'UTF-8');
        // 不添加XML声明
        $dom->formatOutput = false;
        $dom->preserveWhiteSpace = true;
        
        // 加载HTML（使用 UTF-8 编码）
        // 限制解析深度，避免深层嵌套导致内存问题
        @$dom->loadHTML('<?xml encoding="UTF-8">' . $wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD | LIBXML_PARSEHUGE);
        
        libxml_use_internal_errors($libxmlPreviousState);
        
        if (!$dom) {
            // 如果DOM解析失败，回退到正则方法
            return self::purifyWithRegex($html);
        }
        
        // 获取容器div
        $container = $dom->getElementsByTagName('div')->item(0);
        if (!$container) {
            return self::purifyWithRegex($html);
        }
        
        // 递归过滤所有元素（添加深度限制）
        self::filterElement($container, 0, 50); // 最大深度50层
        
        // 提取内容（移除包装的div）
        $result = '';
        foreach ($container->childNodes as $node) {
            $result .= $dom->saveHTML($node);
        }
        
        return $result;
    }

    /**
     * 递归过滤DOM元素及其属性
     * 
     * @param DOMElement $element DOM元素
     * @param int $depth 当前递归深度
     * @param int $maxDepth 最大递归深度，防止无限递归
     */
    private static function filterElement($element, $depth = 0, $maxDepth = 50) {
        // 防止无限递归
        if ($depth > $maxDepth) {
            return;
        }
        
        if ($element->nodeType !== XML_ELEMENT_NODE) {
            return;
        }
        
        $tagName = strtolower($element->tagName);
        
        // 检查是否允许此标签的属性
        if (isset(self::$allowedAttributes[$tagName])) {
            $allowedAttrs = self::$allowedAttributes[$tagName];
            $attributesToRemove = [];
            
            // 遍历所有属性
            foreach ($element->attributes as $attr) {
                $attrName = strtolower($attr->name);
                
                if (!in_array($attrName, $allowedAttrs)) {
                    // 不允许的属性，标记为删除
                    $attributesToRemove[] = $attr;
                } else {
                    // 验证属性值
                    $attrValue = $attr->value;
                    
                    if ($attrName === 'style') {
                        $filteredStyle = self::filterStyle($attrValue);
                        if ($filteredStyle) {
                            $element->setAttribute($attrName, $filteredStyle);
                        } else {
                            $attributesToRemove[] = $attr;
                        }
                    } elseif ($attrName === 'href' || $attrName === 'src') {
                        // URL 验证：只允许 http, https, 相对路径, 锚点
                        if (!preg_match('/^(https?:\/\/|\/|#|mailto:)/i', $attrValue)) {
                            $attributesToRemove[] = $attr;
                        } else {
                            // 转义属性值
                            $element->setAttribute($attrName, htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8'));
                        }
                    } elseif ($attrName === 'target') {
                        // target 只允许 _blank, _self
                        if (!in_array($attrValue, ['_blank', '_self'])) {
                            $attributesToRemove[] = $attr;
                        }
                    } else {
                        // 其他允许的属性，转义值
                        $element->setAttribute($attrName, htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8'));
                    }
                }
            }
            
            // 移除不允许的属性
            foreach ($attributesToRemove as $attr) {
                $element->removeAttribute($attr->name);
            }
        } else {
            // 此标签不允许任何属性，移除所有属性
            while ($element->attributes->length > 0) {
                $element->removeAttribute($element->attributes->item(0)->name);
            }
        }
        
        // 递归处理子元素（增加深度计数）
        $children = [];
        foreach ($element->childNodes as $child) {
            $children[] = $child;
        }
        foreach ($children as $child) {
            self::filterElement($child, $depth + 1, $maxDepth);
        }
    }

    /**
     * 使用正则表达式净化HTML（备用方法）
     * 
     * @param string $html 原始HTML
     * @return string 净化后的HTML
     */
    private static function purifyWithRegex($html) {
        // 移除所有属性，然后手动添加允许的属性
        // 这是一个简化的实现，可能不如DOM方法可靠
        $html = preg_replace_callback('/<(\w+)([^>]*)>/i', function($matches) {
            $tagName = strtolower($matches[1]);
            $attrString = $matches[2];
            
            if (!isset(self::$allowedAttributes[$tagName]) || empty($attrString)) {
                return '<' . $tagName . '>';
            }
            
            $allowedAttrs = self::$allowedAttributes[$tagName];
            $filtered = [];
            
            // 解析属性
            preg_match_all('/(\w+)=(["\']?)([^"\']*?)\2/i', $attrString, $attrMatches, PREG_SET_ORDER);
            
            foreach ($attrMatches as $attrMatch) {
                $attrName = strtolower($attrMatch[1]);
                $attrValue = $attrMatch[3];
                
                if (in_array($attrName, $allowedAttrs)) {
                    if ($attrName === 'style') {
                        $attrValue = self::filterStyle($attrValue);
                        if ($attrValue) {
                            $filtered[] = $attrName . '="' . htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8') . '"';
                        }
                    } elseif ($attrName === 'href' || $attrName === 'src') {
                        if (preg_match('/^(https?:\/\/|\/|#|mailto:)/i', $attrValue)) {
                            $filtered[] = $attrName . '="' . htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8') . '"';
                        }
                    } elseif ($attrName === 'target') {
                        if (in_array($attrValue, ['_blank', '_self'])) {
                            $filtered[] = $attrName . '="' . htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8') . '"';
                        }
                    } else {
                        $filtered[] = $attrName . '="' . htmlspecialchars($attrValue, ENT_QUOTES, 'UTF-8') . '"';
                    }
                }
            }
            
            return '<' . $tagName . ($filtered ? ' ' . implode(' ', $filtered) : '') . '>';
        }, $html);
        
        return $html;
    }

    /**
     * 过滤 style 属性，只允许安全的CSS属性
     * 
     * @param string $style 原始style字符串
     * @return string 过滤后的style字符串
     */
    private static function filterStyle($style) {
        // 只允许 color, background-color, max-width, width, height 等安全属性
        $allowedProperties = [
            'color', 'background-color', 'background',
            'max-width', 'width', 'height',
            'text-align', 'font-size', 'font-weight',
            'margin', 'margin-top', 'margin-bottom', 'margin-left', 'margin-right',
            'padding', 'padding-top', 'padding-bottom', 'padding-left', 'padding-right'
        ];
        
        $filtered = [];
        $properties = explode(';', $style);
        
        foreach ($properties as $prop) {
            $prop = trim($prop);
            if (empty($prop)) continue;
            
            $parts = explode(':', $prop, 2);
            if (count($parts) !== 2) continue;
            
            $propName = strtolower(trim($parts[0]));
            $propValue = trim($parts[1]);
            
            if (in_array($propName, $allowedProperties)) {
                // 验证值中不包含危险的表达式（如 javascript:, expression(), @import）
                if (!preg_match('/javascript:|expression\s*\(|@import|url\s*\(\s*["\']?javascript:/i', $propValue)) {
                    $filtered[] = $propName . ': ' . $propValue;
                }
            }
        }
        
        return implode('; ', $filtered);
    }

    /**
     * 净化并保留换行（将 \n 转换为 <br>）
     * 用于处理可能包含换行符的文本内容
     * 
     * @param string $text 原始文本
     * @param bool $allowHtml 是否允许HTML标签（如果为false，则完全转义）
     * @return string 净化后的HTML
     */
    public static function purifyWithBreaks($text, $allowHtml = true) {
        if (empty($text)) {
            return '';
        }
        
        if (!$allowHtml) {
            // 完全转义，只转换换行
            return nl2br(htmlspecialchars($text, ENT_QUOTES, 'UTF-8'));
        }
        
        // 先净化HTML
        $purified = self::purify($text);
        
        // 如果原文本中有换行符，但净化后没有 <br> 或 <p> 标签，则添加 <br>
        // 注意：如果原文本已经是HTML格式，可能已经包含 <br> 或 <p> 标签
        if (strpos($text, "\n") !== false && !preg_match('/<br|<p/i', $purified)) {
            $purified = nl2br($purified);
        }
        
        return $purified;
    }
}

