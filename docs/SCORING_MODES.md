# 测验评分模式说明

## 当前支持的模式

### 1. Simple 模式（简单累加）

**模式标识**: `simple`

**计算方式**: 
- 累加所有选中选项的 `score_value`
- 根据总分在 `results` 表中查找 `min_score <= 总分 <= max_score` 的结果

**适用场景**: 
- 简单的分数累加型测验
- 例如：心理年龄测试、智商测试等

**配置示例**:
```json
{
  "scoring_mode": "simple"
}
```

**结果匹配**: 通过 `min_score` 和 `max_score` 区间匹配

---

### 2. Dimensions 模式（多维度）

**模式标识**: `dimensions`

**计算方式**:
- 定义多个维度（如：CAT, DOG, FOX, DEER, OWL）
- 每个选项可以给多个维度加权
- 累加各维度得分，取最高维度
- 根据最高维度的 `code` 在 `results` 表中查找结果

**适用场景**:
- 性格类型测试（如 MBTI、九型人格）
- 多维度分析测试
- 原型测试

**配置示例**:
```json
{
  "scoring_mode": "dimensions",
  "scoring_config": {
    "dimensions": ["CAT", "DOG", "FOX", "DEER", "OWL"],
    "weights": {
      "32": {
        "A": {"CAT": 2},
        "B": {"DOG": 2},
        "C": {"FOX": 2},
        "D": {"DEER": 2}
      },
      "33": {
        "A": {"CAT": 1, "FOX": 1},
        "B": {"DOG": 1, "OWL": 1}
      }
    }
  }
}
```

**权重配置说明**:
- 键可以是 `question_id`（如 `"32"`）或题目顺序（如 `"q1"`, `"1"`）
- 每个选项可以给多个维度加权（支持小数）
- 最终取得分最高的维度作为结果

**结果匹配**: 通过 `code` 字段匹配（`code` = 最高维度的名称）

---

### 3. Range 模式（区间评分）

**模式标识**: `range`

**计算方式**: 
- 类似 Simple 模式，累加分数
- 但支持更灵活的区间配置和结果选择策略

**适用场景**:
- 需要精确区间控制的测试
- 分数段较多的测试

**配置示例**:
```json
{
  "scoring_mode": "range",
  "scoring_config": {
    "ranges": [
      {"min": 0, "max": 10, "code": "LOW"},
      {"min": 11, "max": 20, "code": "MEDIUM"},
      {"min": 21, "max": 30, "code": "HIGH"}
    ]
  }
}
```

**结果匹配**: 通过配置的区间或 `min_score`/`max_score` 匹配

---

### 4. Custom 模式（自定义计算）

**模式标识**: `custom`

**计算方式**: 
- 通过 `scoring_config` 配置自定义计算逻辑
- 支持多种计算策略

**适用场景**:
- 复杂的计算需求
- 百分比计算
- 加权平均
- 条件逻辑
- 多结果组合
- 投票/计数
- 加权累加
- 百分比阈值匹配

**配置示例**:

#### 4.1 百分比模式
```json
{
  "scoring_mode": "custom",
  "scoring_config": {
    "strategy": "percentage",
    "dimensions": ["A", "B", "C"],
    "weights": {
      "1": {"A": {"A": 1}, "B": {"B": 1}, "C": {"C": 1}}
    },
    "result_selection": "top_percentage"
  }
}
```

#### 4.2 加权平均模式
```json
{
  "scoring_mode": "custom",
  "scoring_config": {
    "strategy": "weighted_average",
    "dimensions": ["X", "Y", "Z"],
    "dimension_weights": {"X": 0.4, "Y": 0.3, "Z": 0.3},
    "weights": {
      "1": {"A": {"X": 2, "Y": 1}}
    }
  }
}
```

#### 4.3 条件逻辑模式
```json
{
  "scoring_mode": "custom",
  "scoring_config": {
    "strategy": "conditional",
    "rules": [
      {
        "condition": {"dimension": "A", "operator": ">", "value": 10},
        "result_code": "TYPE_A"
      },
      {
        "condition": {"dimension": "B", "operator": ">", "value": 10},
        "result_code": "TYPE_B"
      }
    ],
    "default_result_code": "TYPE_C"
  }
}
```

#### 4.4 多结果组合模式
```json
{
  "scoring_mode": "custom",
  "scoring_config": {
    "strategy": "multi_result",
    "dimensions": ["P", "C", "E", "W"],
    "weights": {
      "1": {"A": {"P": 1}, "B": {"C": 1}}
    },
    "result_selection": "top_two",
    "result_format": "PRIMARY_SECONDARY"
  }
}
```

#### 4.5 投票/计数模式 ⭐ 新增
```json
{
  "scoring_mode": "custom",
  "scoring_config": {
    "strategy": "vote",
    "vote_threshold": 3,
    "tie_breaker": "first"
  }
}
```

**说明**:
- 根据选项的 `map_result_code` 直接计数
- 选择被选次数最多的结果代码
- `vote_threshold`: 可选，最少投票数阈值
- `tie_breaker`: 平局处理方式（`first`、`random`、`all`）

**适用场景**:
- 性格类型测试（如"你更像哪种动物"）
- 偏好选择测试
- 分类测试

**注意**: 需要在 `question_options` 表中设置 `map_result_code` 字段

#### 4.6 加权累加模式 ⭐ 新增
```json
{
  "scoring_mode": "custom",
  "scoring_config": {
    "strategy": "weighted_sum",
    "question_weights": {
      "1": 1.0,
      "2": 2.0,
      "3": 0.5
    }
  }
}
```

**说明**:
- 类似 Simple 模式，但每道题有不同的权重
- 最终分数 = Σ(选项分数 × 题目权重)
- `question_weights`: 题目标识（question_id）到权重的映射

**适用场景**:
- 重要性不同的题目
- 能力测试（核心能力题权重更高）
- 评估测试

#### 4.7 百分比阈值模式 ⭐ 新增
```json
{
  "scoring_mode": "custom",
  "scoring_config": {
    "strategy": "percentage_threshold",
    "dimensions": ["A", "B", "C", "D"],
    "weights": {
      "1": {"A": {"A": 1}, "B": {"B": 1}}
    },
    "thresholds": {
      "BALANCED": {
        "all_dimensions": {
          "A": {"min": 20, "max": 30},
          "B": {"min": 20, "max": 30},
          "C": {"min": 20, "max": 30},
          "D": {"min": 20, "max": 30}
        }
      },
      "DOMINANT_A": {
        "dimension": "A",
        "min": 60
      },
      "DOMINANT_B": {
        "dimension": "B",
        "min": 60
      }
    },
    "default_result_code": "MIXED"
  }
}
```

**说明**:
- 计算各维度得分百分比
- 根据百分比阈值匹配结果（而不是简单的"最高维度"）
- 可以识别"平衡型"结果
- `thresholds`: 结果代码到阈值规则的映射
  - `dimension`: 单个维度阈值检查
  - `all_dimensions`: 所有维度阈值检查（用于"平衡型"）
  - `min`/`max`: 百分比范围

**适用场景**:
- 需要"平衡型"结果的测试
- 多维度性格测试（如大五人格）
- 能力评估测试

---

## 配置字段说明

### scoring_config 通用结构

```json
{
  "dimensions": ["维度1", "维度2", ...],  // 维度列表（dimensions/custom 模式需要）
  "weights": {                             // 权重配置
    "question_id或q1": {                   // 题目标识
      "option_key": {                      // 选项键（A, B, C, D）
        "dimension": weight                // 维度 => 权重值（支持小数）
      }
    }
  },
  "mapping": {                             // 旧格式兼容（已废弃，建议用 weights）
    "q1": {
      "A": {"dimension": weight}
    }
  },
  "strategy": "策略名称",                  // custom 模式专用
  "其他配置": "..."                        // 策略特定配置
}
```

---

## 结果匹配规则

### Simple / Range 模式
- 通过 `results.min_score` 和 `results.max_score` 区间匹配
- 优先匹配严格区间（`min_score <= 总分 <= max_score`）
- 如果没有严格匹配，选择 `min_score <= 总分` 的最大区间

### Dimensions / Custom 模式
- 通过 `results.code` 字段匹配
- `code` 值应该等于维度名称或配置中指定的结果代码
- 如果没有匹配，返回该测验的第一条结果

---

## 扩展性

系统设计支持通过 `scoring_config` 灵活配置不同的计算方式：

1. **维度数量**: 可以定义任意数量的维度
2. **权重值**: 支持整数和小数权重
3. **多维度权重**: 每个选项可以同时给多个维度加权
4. **题目标识**: 支持 `question_id` 或题目顺序（`q1`, `q2` 或 `"1"`, `"2"`）
5. **自定义策略**: `custom` 模式支持通过 `strategy` 字段扩展新的计算方式

---

## 最佳实践

1. **维度命名**: 使用清晰、有意义的维度名称（如：`EXTROVERT`, `INTROVERT`）
2. **权重设计**: 确保不同维度的权重分布有差异，避免所有维度得分相同
3. **结果代码**: `results.code` 应该与维度名称或配置中的结果代码一致
4. **测试验证**: 在发布前，用不同的答案组合测试，确保能产生不同的结果
5. **配置验证**: 使用诊断工具检查配置是否正确

---

## 诊断工具

使用以下工具检查配置：

```bash
# 检查所有测验配置
php check_tests_config.php

# 检查特定测验
php check_specific_test.php <test_id>
```

