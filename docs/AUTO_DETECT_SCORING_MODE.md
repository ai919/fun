# 评分模式自动识别功能

## 概述

系统现在支持**自动识别评分模式**功能。当你导入 JSON 格式的测验时，如果未指定 `scoring_mode` 或指定为默认的 `simple`，系统会根据 JSON 数据的特征自动推断应该使用哪种评分模式。

## 识别规则

系统按以下优先级顺序识别评分模式：

### 1. Dimensions 模式

**识别条件**：
- `scoring_config` 中存在 `dimensions` 字段（数组）
- `scoring_config` 中存在 `weights` 字段（对象）

**示例**：
```json
{
  "test": {
    "scoring_config": {
      "dimensions": ["CAT", "DOG", "FOX"],
      "weights": {
        "1": {
          "A": {"CAT": 2},
          "B": {"DOG": 2}
        }
      }
    }
  }
}
```

**结果**：自动识别为 `dimensions` 模式

---

### 2. Custom 模式 - 投票策略 (vote)

**识别条件**：
- 超过 70% 的选项有 `map_result_code` 字段
- 结果的 `code` 与选项的 `map_result_code` 匹配（匹配度 ≥ 80%）
- 结果**没有** `min_score` 和 `max_score` 区间（或全为 0）

**示例**：
```json
{
  "questions": [
    {
      "text": "你更喜欢？",
      "options": [
        { "key": "A", "text": "猫", "map_result_code": "CAT" },
        { "key": "B", "text": "狗", "map_result_code": "DOG" }
      ]
    }
  ],
  "results": [
    { "code": "CAT", "title": "猫系人格", "description": "..." },
    { "code": "DOG", "title": "狗系人格", "description": "..." }
  ]
}
```

**结果**：自动识别为 `custom` 模式，并生成配置：
```json
{
  "scoring_mode": "custom",
  "scoring_config": {
    "strategy": "vote",
    "vote_threshold": 0,
    "tie_breaker": "first"
  }
}
```

---

### 3. Custom 模式 - 加权累加策略 (weighted_sum)

**识别条件**：
- `scoring_config` 中存在 `question_weights` 字段

**示例**：
```json
{
  "test": {
    "scoring_config": {
      "question_weights": {
        "1": 2.0,
        "2": 1.5,
        "3": 1.0
      }
    }
  }
}
```

**结果**：自动识别为 `custom` 模式，并补充 `strategy` 字段：
```json
{
  "scoring_mode": "custom",
  "scoring_config": {
    "strategy": "weighted_sum",
    "question_weights": {
      "1": 2.0,
      "2": 1.5,
      "3": 1.0
    }
  }
}
```

---

### 4. Custom 模式 - 百分比阈值策略 (percentage_threshold)

**识别条件**：
- `scoring_config` 中存在 `thresholds` 字段

**示例**：
```json
{
  "test": {
    "scoring_config": {
      "dimensions": ["A", "B", "C"],
      "weights": { ... },
      "thresholds": {
        "BALANCED": {
          "all_dimensions": {
            "A": {"min": 20, "max": 30},
            "B": {"min": 20, "max": 30}
          }
        }
      }
    }
  }
}
```

**结果**：自动识别为 `custom` 模式，并补充 `strategy` 字段：
```json
{
  "scoring_mode": "custom",
  "scoring_config": {
    "strategy": "percentage_threshold",
    "dimensions": ["A", "B", "C"],
    "weights": { ... },
    "thresholds": { ... }
  }
}
```

---

### 5. Range 模式

**识别条件**：
- 结果有 `min_score` 和/或 `max_score` 字段（且不全为 0）
- 同时满足以下任一条件：
  - `scoring_config` 中有 `option_scores` 字段
  - 选项中有 `score_override` 字段

**示例**：
```json
{
  "test": {
    "scoring_config": {
      "option_scores": {
        "A": 0,
        "B": 1,
        "C": 2,
        "D": 3
      }
    }
  },
  "results": [
    { "code": "LOW", "title": "低分", "min_score": 0, "max_score": 5 },
    { "code": "HIGH", "title": "高分", "min_score": 6, "max_score": 10 }
  ]
}
```

**结果**：自动识别为 `range` 模式

---

### 6. Simple 模式（默认）

如果以上条件都不满足，系统会使用 `simple` 模式作为默认值。

---

## 使用场景

### 场景 1：投票类测试（自动识别为 vote 模式）

**JSON 数据**：
```json
{
  "test": {
    "slug": "animal-personality",
    "title": "你的动物性格",
    "description": "...",
    "tags": ["personality"],
    "status": "draft"
    // 注意：没有指定 scoring_mode
  },
  "questions": [
    {
      "text": "你更喜欢？",
      "options": [
        { "key": "A", "text": "猫", "map_result_code": "CAT" },
        { "key": "B", "text": "狗", "map_result_code": "DOG" },
        { "key": "C", "text": "狐狸", "map_result_code": "FOX" }
      ]
    },
    {
      "text": "你的性格更像？",
      "options": [
        { "key": "A", "text": "独立", "map_result_code": "CAT" },
        { "key": "B", "text": "忠诚", "map_result_code": "DOG" },
        { "key": "C", "text": "聪明", "map_result_code": "FOX" }
      ]
    }
  ],
  "results": [
    { "code": "CAT", "title": "猫系人格", "description": "..." },
    { "code": "DOG", "title": "狗系人格", "description": "..." },
    { "code": "FOX", "title": "狐狸系人格", "description": "..." }
  ]
}
```

**自动识别结果**：
- `scoring_mode`: `custom`
- `scoring_config`: 
  ```json
  {
    "strategy": "vote",
    "vote_threshold": 0,
    "tie_breaker": "first"
  }
  ```

---

### 场景 2：多维度测试（自动识别为 dimensions 模式）

**JSON 数据**：
```json
{
  "test": {
    "slug": "mbti-test",
    "scoring_config": {
      "dimensions": ["E", "I", "S", "N", "T", "F", "J", "P"],
      "weights": {
        "1": {
          "A": {"E": 1},
          "B": {"I": 1}
        }
      }
    }
  },
  "questions": [...],
  "results": [...]
}
```

**自动识别结果**：
- `scoring_mode`: `dimensions`
- 使用提供的 `scoring_config` 不变

---

### 场景 3：分数区间测试（自动识别为 range 模式）

**JSON 数据**：
```json
{
  "test": {
    "slug": "iq-test",
    "scoring_config": {
      "option_scores": {
        "A": 0,
        "B": 1,
        "C": 2,
        "D": 3
      }
    }
  },
  "questions": [
    {
      "text": "1+1=?",
      "options": [
        { "key": "A", "text": "1" },
        { "key": "B", "text": "2" },
        { "key": "C", "text": "3" }
      ]
    }
  ],
  "results": [
    { "code": "LOW", "title": "低分", "min_score": 0, "max_score": 5 },
    { "code": "HIGH", "title": "高分", "min_score": 6, "max_score": 10 }
  ]
}
```

**自动识别结果**：
- `scoring_mode`: `range`
- 使用提供的 `scoring_config` 不变

---

## 手动覆盖

如果你明确指定了 `scoring_mode`，系统会**优先使用你指定的模式**，不会进行自动识别。

**示例**：
```json
{
  "test": {
    "scoring_mode": "simple"  // 明确指定，不会自动识别
  }
}
```

---

## 调试和验证

### 使用 Dry Run 查看识别结果

运行 `--dry-run` 模式时，如果系统进行了自动识别，会在输出中显示：

```bash
yarn quiz:import payload.json --dry-run
```

输出示例：
```
🧪 Dry run 模式：不会写入数据库。
   - 操作：创建新测验
   - slug: animal-personality
   - 标题: 你的动物性格
   - 结果数: 3, 题目数: 5
   - overwrite: 否
   - 自动识别评分模式: custom
```

### 查看实际导入的配置

导入后，可以在数据库中查看 `tests.scoring_mode` 和 `tests.scoring_config` 字段，确认自动识别的结果。

---

## 注意事项

1. **识别准确性**：自动识别基于数据特征，可能不是 100% 准确。如果识别结果不符合预期，请手动指定 `scoring_mode`。

2. **投票模式识别**：投票模式的识别需要满足以下条件：
   - 至少 70% 的选项有 `map_result_code`
   - 至少 80% 的 `map_result_code` 与结果的 `code` 匹配
   - 结果没有分数区间

3. **配置补充**：对于 `custom` 模式的子策略（如 `weighted_sum`、`percentage_threshold`），如果配置中缺少 `strategy` 字段，系统会自动补充。

4. **优先级**：如果 JSON 中已经指定了 `scoring_mode`（且不是 `simple`），系统不会进行自动识别。

---

## 最佳实践

1. **明确指定**：对于复杂的评分逻辑，建议在 JSON 中明确指定 `scoring_mode` 和 `scoring_config`，避免自动识别错误。

2. **使用 Dry Run**：导入前使用 `--dry-run` 检查自动识别结果，确认是否符合预期。

3. **验证结果**：导入后测试评分逻辑，确保自动识别的模式工作正常。

4. **文档记录**：在 JSON 文件中添加注释（虽然 JSON 不支持注释，但可以在外部文档中说明），记录预期的评分模式。

---

## 技术实现

自动识别功能在以下文件中实现：
- PHP 版本：`lib/QuizImporter.php` → `detectScoringMode()` 方法
- TypeScript 版本：`tools/quiz-import/import-quiz.ts` → `detectScoringMode()` 函数

两个版本的识别逻辑保持一致，确保 CLI 和后台导入行为一致。

