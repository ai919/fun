# 出题注意事项指南

## 📋 核心检查清单

### ✅ 1. 选择正确的评分模式

根据测验类型选择合适的 `scoring_mode`：

| 测验类型 | 推荐模式 | 说明 |
|---------|---------|------|
| 简单分数累加 | `simple` | 每题选项有固定分数，累加后按区间匹配结果 |
| 多维度分析 | `dimensions` | 每个选项可给多个维度加权，取最高维度 |
| 精确区间控制 | `range` | 类似 simple，但支持自定义区间配置 |
| 复杂计算逻辑 | `custom` | 支持百分比、加权平均、条件逻辑等策略 |

**⚠️ 注意**：一旦选定模式，确保所有配置与该模式匹配！

---

### ✅ 2. 题目和选项配置

#### 题目（`questions` 表）

- ✅ **题目文本**：清晰、无歧义，避免过于复杂
- ✅ **排序**：`sort_order` 必须连续且唯一（1, 2, 3...）
- ✅ **数量**：建议 5-20 题，太少不够准确，太多用户疲劳

#### 选项（`question_options` 表）

- ✅ **选项键**：`option_key` 必须唯一（A, B, C, D...），且在同一题目内不重复
- ✅ **选项文本**：每个选项都要有意义，避免"以上都不是"这种选项
- ✅ **选项数量**：每题至少 2 个选项，建议 3-5 个
- ✅ **选项平衡**：各选项长度尽量接近，避免明显偏向某个选项

---

### ✅ 3. 分数配置（Simple/Range 模式）

#### `score_value` 字段

- ✅ **必须设置**：每个选项的 `score_value` 都要明确
- ✅ **数值范围**：建议使用整数，如 0, 1, 2, 3...
- ✅ **一致性**：同一测验内，相同"倾向"的选项分数应该一致
- ✅ **检查总和**：确保所有题目的分数范围合理

**示例**：
```sql
-- ✅ 正确：每题选项分数明确
选项 A: score_value = 0
选项 B: score_value = 1
选项 C: score_value = 2

-- ❌ 错误：分数未设置或混乱
选项 A: score_value = 0  -- 默认值
选项 B: score_value = 0  -- 未区分
```

---

### ✅ 4. 维度配置（Dimensions/Custom 模式）

#### `scoring_config` 配置

**必须包含**：
```json
{
  "dimensions": ["维度1", "维度2", "维度3"],
  "weights": {
    "1": {  // 题目编号（可以是 question_id 或 "1", "2"）
      "A": {"维度1": 2, "维度2": 1},  // 选项 A 的权重
      "B": {"维度1": 1, "维度2": 2}   // 选项 B 的权重
    }
  }
}
```

**注意事项**：

1. ✅ **维度名称**：必须与 `results.code` 匹配（或配置中的 `result_code`）
2. ✅ **权重覆盖**：确保每个选项至少给一个维度加权
3. ✅ **权重平衡**：避免某个维度权重过高或过低
4. ✅ **题目编号**：可以使用 `question_id`（数字）或题目顺序（"1", "2"）
5. ✅ **小数权重**：支持小数，如 `{"EXTROVERT": 1.5, "INTROVERT": 0.5}`

**示例**：
```json
{
  "scoring_mode": "dimensions",
  "scoring_config": {
    "dimensions": ["EXTROVERT", "INTROVERT"],
    "weights": {
      "1": {
        "A": {"EXTROVERT": 2},      // ✅ 正确
        "B": {"INTROVERT": 2}        // ✅ 正确
      },
      "2": {
        "A": {"EXTROVERT": 1.5},    // ✅ 支持小数
        "B": {"INTROVERT": 1.5}
      }
    }
  }
}
```

---

### ✅ 5. 结果配置（`results` 表）

#### Simple/Range 模式

- ✅ **分数区间**：`min_score` 和 `max_score` 必须设置
- ✅ **区间连续**：确保所有可能的分数都被覆盖，且区间不重叠
- ✅ **区间顺序**：建议按 `min_score` 排序

**示例**：
```sql
-- ✅ 正确：区间连续且覆盖完整
结果1: min_score = 0,  max_score = 5
结果2: min_score = 6,  max_score = 10
结果3: min_score = 11, max_score = 15

-- ❌ 错误：区间有 gap
结果1: min_score = 0,  max_score = 5
结果2: min_score = 7,  max_score = 10  -- gap: 6 分没有结果
```

#### Dimensions/Custom 模式

- ✅ **结果代码**：`code` 必须与维度名称或配置中的 `result_code` 匹配
- ✅ **至少一个结果**：每个维度都应该有对应的结果
- ✅ **备用结果**：建议设置一个默认结果（当找不到匹配时使用）

**示例**：
```sql
-- ✅ 正确：code 与维度名称匹配
维度: ["EXTROVERT", "INTROVERT"]
结果1: code = "EXTROVERT"
结果2: code = "INTROVERT"

-- ❌ 错误：code 不匹配
维度: ["EXTROVERT", "INTROVERT"]
结果1: code = "E"  -- 不匹配！
结果2: code = "I"  -- 不匹配！
```

---

### ✅ 6. 数据一致性检查

#### 必检项

1. ✅ **题目数量**：确保 `questions` 表中有题目
2. ✅ **选项数量**：每道题至少 2 个选项
3. ✅ **结果数量**：至少 1 个结果
4. ✅ **模式匹配**：`scoring_mode` 与 `scoring_config` 匹配
5. ✅ **维度匹配**：dimensions 模式的维度名称与结果 code 匹配

#### 使用诊断工具

```bash
# 检查所有测验配置
php check_tests_config.php

# 检查特定测验
php check_specific_test.php <test_id>
```

---

### ✅ 7. 测试验证

#### 测试不同答案组合

1. ✅ **极端情况**：全部选 A、全部选 B
2. ✅ **平衡情况**：A/B 各选一半
3. ✅ **边界情况**：分数刚好在区间边界
4. ✅ **维度平局**：两个维度分数相同（检查 tie_breaker）

#### 验证结果

- ✅ 结果是否合理
- ✅ 分数计算是否正确
- ✅ 维度匹配是否正确
- ✅ 是否有遗漏的分数区间

---

## 🚨 常见错误

### ❌ 错误 1：分数区间不连续

```sql
-- ❌ 错误
结果1: min_score = 0,  max_score = 5
结果2: min_score = 7,  max_score = 10  -- 6 分没有结果！

-- ✅ 正确
结果1: min_score = 0,  max_score = 5
结果2: min_score = 6,  max_score = 10
```

### ❌ 错误 2：维度名称与结果 code 不匹配

```json
// ❌ 错误
"dimensions": ["EXTROVERT", "INTROVERT"]
// 但 results.code = "E", "I"

// ✅ 正确
"dimensions": ["EXTROVERT", "INTROVERT"]
// results.code = "EXTROVERT", "INTROVERT"
```

### ❌ 错误 3：权重配置不完整

```json
// ❌ 错误：选项 C 没有权重
"weights": {
  "1": {
    "A": {"EXTROVERT": 2},
    "B": {"INTROVERT": 2}
    // 缺少 C 的权重！
  }
}

// ✅ 正确
"weights": {
  "1": {
    "A": {"EXTROVERT": 2},
    "B": {"INTROVERT": 2},
    "C": {"EXTROVERT": 1, "INTROVERT": 1}
  }
}
```

### ❌ 错误 4：题目编号格式不一致

```json
// ❌ 错误：混用数字和字符串
"weights": {
  "1": {...},      // 字符串
  2: {...}         // 数字（可能不匹配）
}

// ✅ 正确：统一使用字符串
"weights": {
  "1": {...},
  "2": {...}
}
```

### ❌ 错误 5：选项键重复

```sql
-- ❌ 错误：同一题目内选项键重复
题目1: 选项 A, 选项 A  -- 重复！

-- ✅ 正确
题目1: 选项 A, 选项 B, 选项 C
```

---

## 📝 出题流程建议

### 步骤 1：规划测验结构

1. 确定测验类型和目标
2. 选择合适的评分模式
3. 设计题目数量和维度（如适用）

### 步骤 2：创建测验基础信息

```sql
INSERT INTO tests (slug, title, description, scoring_mode, scoring_config)
VALUES (...);
```

### 步骤 3：添加题目和选项

```sql
-- 先添加题目
INSERT INTO questions (test_id, question_text, sort_order) VALUES (...);

-- 再添加选项
INSERT INTO question_options (question_id, option_key, option_text, score_value) VALUES (...);
```

### 步骤 4：配置评分规则

- Simple/Range：设置 `score_value` 和结果区间
- Dimensions/Custom：配置 `scoring_config`

### 步骤 5：添加结果

```sql
INSERT INTO results (test_id, code, title, description, min_score, max_score)
VALUES (...);
```

### 步骤 6：验证和测试

1. 运行诊断工具
2. 测试不同答案组合
3. 检查结果是否合理

---

## 🎯 最佳实践

### 1. 题目设计

- ✅ 题目清晰、简洁
- ✅ 避免双重否定
- ✅ 选项互斥且完整
- ✅ 避免引导性语言

### 2. 分数设计

- ✅ 分数范围合理（不要太大或太小）
- ✅ 分数分布均匀
- ✅ 避免所有选项分数相同

### 3. 维度设计

- ✅ 维度数量适中（2-6 个）
- ✅ 维度名称清晰、有意义
- ✅ 权重分布有差异

### 4. 结果设计

- ✅ 结果描述准确、有吸引力
- ✅ 结果数量适中（3-8 个）
- ✅ 结果之间有明显差异

---

## 🔧 工具和资源

### 诊断工具

- `check_tests_config.php` - 检查所有测验配置
- `check_specific_test.php` - 检查特定测验

### 导入工具

- `tools/quiz-import/` - JSON 导入工具
- 支持批量导入和配置验证

### 文档

- [测验题撰写完整指南](./COMPLETE_QUIZ_CREATION_GUIDE.md) 📖 **完整指南 - 推荐阅读**
- `SCORING_SYSTEM_SUMMARY.md` - 评分系统总结
- `docs/SCORING_MODES.md` - 评分模式详细说明

---

## ❓ 遇到问题时

1. **检查配置**：运行诊断工具
2. **查看日志**：检查错误日志
3. **测试数据**：用不同答案组合测试
4. **参考示例**：查看现有测验的配置

---

## ✨ 总结

出题时记住：

1. ✅ **选择正确的评分模式**
2. ✅ **配置完整且一致**
3. ✅ **验证数据完整性**
4. ✅ **测试不同场景**
5. ✅ **使用诊断工具**

遵循这些注意事项，可以避免大部分常见问题！🎉

