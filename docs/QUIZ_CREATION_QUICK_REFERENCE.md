# 测验题撰写快速参考

> **📌 提示**：这是快速参考，详细说明请查看 [完整指南](./COMPLETE_QUIZ_CREATION_GUIDE.md)

---

## 🎯 第一步：选择评分模式

**⚠️ 必须明确指定！**

| 需求 | 评分模式 | 配置 |
|-----|---------|------|
| 简单分数累加 | `simple` | 选项设置 `score_override`，结果设置 `min_score`/`max_score` |
| 多维度分析 | `dimensions` | 配置 `dimensions` 和 `weights`，结果 `code` 匹配维度名 |
| 精确区间控制 | `range` | 配置 `option_scores` 或 `score_override`，结果设置区间 |
| 投票/计数 | `custom` + `vote` | 选项设置 `map_result_code`，结果 `code` 匹配 |
| 加权累加 | `custom` + `weighted_sum` | 配置 `question_weights`，选项设置 `score_override` |
| 百分比阈值 | `custom` + `percentage_threshold` | 配置 `dimensions`、`weights`、`thresholds` |

---

## 📝 JSON 结构模板

### 最小模板

```json
{
  "test": {
    "slug": "your-quiz-slug",
    "title": "测验标题",
    "description": "测验描述",
    "tags": ["标签1"],
    "status": "draft",
    "scoring_mode": "simple"
  },
  "questions": [
    {
      "text": "题目文本",
      "options": [
        { "key": "A", "text": "选项A" }
      ]
    }
  ],
  "results": [
    {
      "code": "RESULT1",
      "title": "结果标题",
      "description": "结果描述"
    }
  ]
}
```

---

## ✅ 必填字段检查清单

### test 对象
- [ ] `slug` - 小写字母、数字、短横线
- [ ] `title` - ≤255 字符
- [ ] `description` - 非空
- [ ] `tags` - 1-8 个，唯一
- [ ] `status` - `draft`/`published`/`archived`
- [ ] **`scoring_mode`** - 必须明确指定 ⚠️

### questions 数组
- [ ] 至少 1 题
- [ ] 每题的 `text` 非空
- [ ] 每题的 `options` 至少 2 个
- [ ] 每个选项的 `key` 为单个大写字母（A-Z）
- [ ] 每个选项的 `text` 非空
- [ ] 同一题目内选项 `key` 不重复

### results 数组
- [ ] 至少 1 个结果
- [ ] 每个结果的 `code`、`title`、`description` 非空
- [ ] 结果 `code` 不重复

---

## 🔧 各模式配置要点

### Simple 模式

```json
{
  "scoring_mode": "simple",
  "questions": [
    {
      "options": [
        { "key": "A", "text": "选项A", "score_override": 0 },
        { "key": "B", "text": "选项B", "score_override": 1 }
      ]
    }
  ],
  "results": [
    {
      "code": "LOW",
      "min_score": 0,
      "max_score": 5
    },
    {
      "code": "HIGH",
      "min_score": 6,
      "max_score": 10
    }
  ]
}
```

**检查点**：
- ✅ 每个选项有 `score_override`
- ✅ 每个结果有 `min_score` 和 `max_score`
- ✅ 分数区间连续且不重叠

---

### Dimensions 模式

```json
{
  "scoring_mode": "dimensions",
  "scoring_config": {
    "dimensions": ["EXTROVERT", "INTROVERT"],
    "weights": {
      "1": {
        "A": {"EXTROVERT": 2},
        "B": {"INTROVERT": 2}
      }
    }
  },
  "results": [
    {
      "code": "EXTROVERT",
      "title": "外向"
    },
    {
      "code": "INTROVERT",
      "title": "内向"
    }
  ]
}
```

**检查点**：
- ✅ `dimensions` 数组已定义
- ✅ `weights` 配置完整
- ✅ 结果 `code` 与维度名匹配
- ✅ 结果**没有** `min_score`/`max_score`

---

### Range 模式

```json
{
  "scoring_mode": "range",
  "scoring_config": {
    "option_scores": {
      "A": 0,
      "B": 1,
      "C": 2
    }
  },
  "results": [
    {
      "code": "LOW",
      "min_score": 0,
      "max_score": 5
    }
  ]
}
```

**检查点**：
- ✅ `option_scores` 已配置或选项有 `score_override`
- ✅ 结果有 `min_score`/`max_score`
- ✅ 分数区间连续

---

### Custom 投票模式

```json
{
  "scoring_mode": "custom",
  "scoring_config": {
    "strategy": "vote",
    "tie_breaker": "first"
  },
  "questions": [
    {
      "options": [
        { "key": "A", "text": "猫", "map_result_code": "CAT" }
      ]
    }
  ],
  "results": [
    {
      "code": "CAT",
      "title": "猫系"
    }
  ]
}
```

**检查点**：
- ✅ 选项有 `map_result_code`
- ✅ `map_result_code` 与结果 `code` 匹配
- ✅ 结果**没有** `min_score`/`max_score`

---

## 🚨 常见错误

| 错误 | 解决方案 |
|-----|---------|
| 未指定 `scoring_mode` | 明确设置 `scoring_mode` 字段 |
| 分数区间不连续 | 确保区间覆盖所有可能的分数 |
| 维度名与结果 `code` 不匹配 | 确保 `code` 与维度名一致 |
| 选项 `key` 重复 | 同一题目内使用不同的 `key` |
| 权重配置不完整 | 确保每个选项都有权重配置 |

---

## 🔍 验证命令

```bash
# 验证 JSON（推荐）
yarn quiz:import your-quiz.json --dry-run

# 检查配置（导入后）
php check_specific_test.php <test_id>
```

---

## 📚 完整文档

- [测验题撰写完整指南](./COMPLETE_QUIZ_CREATION_GUIDE.md) 📖 **详细说明**
- [出题注意事项指南](./QUESTION_CREATION_GUIDE.md)
- [评分模式详细说明](./SCORING_MODES.md)

---

## 💡 快速提示

1. **明确指定评分模式** - 不要依赖自动识别
2. **使用验证命令** - 导入前先验证
3. **检查数据一致性** - 确保配置完整
4. **测试不同场景** - 确保评分正确
5. **需要新功能？** - 在 JSON 中添加申请说明

---

**祝你出题顺利！** 🎉

