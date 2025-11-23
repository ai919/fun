# 结构化数据使用指南

本文档说明如何在 DoFun心理实验空间 中使用结构化数据（Schema.org）来提升 SEO。

## 已实现的结构化数据类型

### 1. WebPage（基础页面）
所有页面都自动包含基础的 WebPage 结构化数据，包括：
- 页面名称
- URL
- 描述
- 所属网站信息

### 2. BreadcrumbList（面包屑导航）
用于显示页面层级结构，帮助搜索引擎理解网站导航。

**使用示例：**
```php
$seo = build_seo_meta('generic', [
    'breadcrumbs' => [
        ['name' => '首页', 'url' => '/'],
        ['name' => '测验列表', 'url' => '/index.php'],
        ['name' => '具体测验', 'url' => '/test.php?slug=xxx'],
    ],
]);
```

**自动应用：**
- 测验页面（test.php）：自动生成 首页 > 测验列表 > 测验名称
- 结果页面（result.php）：自动生成 首页 > 测验列表 > 测验名称 > 结果名称

### 3. Quiz（测验）
测验页面自动包含 Quiz 结构化数据，包括：
- 测验名称
- 描述
- 题目数量
- 封面图片

**自动应用：**
- 所有测验页面（test.php）自动包含

### 4. FAQPage（常见问题）
用于显示常见问题，可以在搜索结果中显示为富文本摘要。

**使用示例：**
```php
$seo = build_seo_meta('generic', [
    'faqs' => [
        [
            'question' => '如何开始测验？',
            'answer' => '在首页选择你感兴趣的测验，点击开始即可。',
        ],
        [
            'question' => '测验结果准确吗？',
            'answer' => '我们的测验基于心理学研究，但仅供参考，不能替代专业心理评估。',
        ],
    ],
]);
```

## 验证结构化数据

### Google Rich Results Test
访问 [Google Rich Results Test](https://search.google.com/test/rich-results) 来验证你的结构化数据是否正确。

### Schema.org Validator
访问 [Schema.org Validator](https://validator.schema.org/) 来验证结构化数据的语法。

## 最佳实践

1. **保持数据准确**：确保结构化数据与页面内容一致
2. **使用完整信息**：尽可能提供完整的属性（如图片、描述等）
3. **定期验证**：使用验证工具检查结构化数据
4. **避免重复**：不要为同一内容创建多个重复的结构化数据块

## 技术实现

所有结构化数据功能都在 `seo_helper.php` 中实现：

- `build_breadcrumb_structured_data()` - 构建面包屑导航
- `build_faq_structured_data()` - 构建常见问题
- `build_quiz_structured_data()` - 构建测验数据
- `build_seo_meta()` - 主函数，整合所有结构化数据
- `render_seo_head()` - 输出所有 meta 标签和结构化数据

## 未来扩展

可以考虑添加的结构化数据类型：
- Organization（组织信息）
- Person（作者信息）
- Review（用户评价）
- AggregateRating（评分汇总）

