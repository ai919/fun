# 可能的评分模式扩展

## 📊 当前已支持的评分模式

你的系统目前支持以下评分模式：

1. ✅ **Simple** - 简单累加
2. ✅ **Dimensions** - 多维度分析
3. ✅ **Range** - 精确区间
4. ✅ **Custom** - 自定义计算（包含多种策略）

---

## 🚀 可能的扩展评分模式

### 1. **投票/计数模式（Vote/Count）**

#### 描述
根据选项的 `map_result_code` 直接计数，选择被选次数最多的结果。

#### 应用场景
- 性格类型测试（如 MBTI 简化版）
- 偏好选择测试
- 分类测试（如"你更像哪种动物"）

#### 实现思路
```json
{
  "scoring_mode": "vote",
  "scoring_config": {
    "vote_threshold": 3,  // 可选：最少投票数
    "tie_breaker": "first"  // 平局处理：first, random, all
  }
}
```

#### 优势
- 简单直观
- 适合"多数决定"的场景
- 不需要复杂的权重配置

---

### 2. **百分比阈值模式（Percentage Threshold）**

#### 描述
计算各维度得分百分比，根据百分比阈值匹配结果（而不是简单的"最高维度"）。

#### 应用场景
- 需要"平衡型"结果的测试
- 多维度性格测试（如大五人格）
- 能力评估测试

#### 实现思路
```json
{
  "scoring_mode": "percentage_threshold",
  "scoring_config": {
    "dimensions": ["A", "B", "C"],
    "weights": {...},
    "thresholds": {
      "BALANCED": {"min": 0.25, "max": 0.75},  // 所有维度都在 25%-75% 之间
      "DOMINANT_A": {"dimension": "A", "min": 0.6},  // A 维度 >= 60%
      "DOMINANT_B": {"dimension": "B", "min": 0.6}
    }
  }
}
```

#### 优势
- 可以识别"平衡型"结果
- 更精确的维度分析
- 避免单一维度主导

---

### 3. **路径/决策树模式（Path/Decision Tree）**

#### 描述
根据答案序列，按照决策树路径选择结果。不同题目的答案会导向不同的下一题或结果。

#### 应用场景
- 诊断性测试（如"你适合什么职业"）
- 流程化测试
- 条件分支测试

#### 实现思路
```json
{
  "scoring_mode": "path",
  "scoring_config": {
    "tree": {
      "start": "q1",
      "nodes": {
        "q1": {
          "A": "q2",
          "B": "q3",
          "C": "result_TYPE_A"
        },
        "q2": {
          "A": "result_TYPE_B",
          "B": "result_TYPE_C"
        }
      }
    }
  }
}
```

#### 优势
- 支持动态题目流程
- 可以跳过不相关题目
- 更个性化的测试体验

#### 挑战
- 需要修改题目加载逻辑
- 结果可能不完整（如果用户中途退出）

---

### 4. **聚类/相似度模式（Cluster/Similarity）**

#### 描述
计算用户答案与预设"原型"答案的相似度，选择最相似的原型作为结果。

#### 应用场景
- 人格原型测试（如九型人格）
- 风格测试（如"你的设计风格"）
- 匹配测试（如"你像哪个历史人物"）

#### 实现思路
```json
{
  "scoring_mode": "similarity",
  "scoring_config": {
    "prototypes": {
      "TYPE_A": {
        "1": "A",
        "2": "B",
        "3": "A"
      },
      "TYPE_B": {
        "1": "B",
        "2": "A",
        "3": "B"
      }
    },
    "similarity_method": "hamming",  // hamming, euclidean, cosine
    "threshold": 0.7  // 最低相似度阈值
  }
}
```

#### 优势
- 直观的匹配逻辑
- 适合原型测试
- 可以显示相似度百分比

---

### 5. **加权累加模式（Weighted Sum）**

#### 描述
类似 Simple 模式，但每道题有不同的权重，最终分数 = Σ(选项分数 × 题目权重)。

#### 应用场景
- 重要性不同的题目
- 能力测试（核心能力题权重更高）
- 评估测试

#### 实现思路
```json
{
  "scoring_mode": "weighted_sum",
  "scoring_config": {
    "question_weights": {
      "1": 1.0,   // 题目1权重
      "2": 2.0,   // 题目2权重（更重要）
      "3": 0.5    // 题目3权重（较不重要）
    }
  }
}
```

#### 优势
- 简单但灵活
- 可以突出重要题目
- 易于理解和配置

---

### 6. **排名/排序模式（Ranking）**

#### 描述
根据维度分数排名，选择排名最高的维度，或根据排名组合生成结果。

#### 应用场景
- 多维度性格测试
- 能力评估
- 偏好排序测试

#### 实现思路
```json
{
  "scoring_mode": "ranking",
  "scoring_config": {
    "dimensions": ["A", "B", "C", "D"],
    "weights": {...},
    "result_mapping": {
      "A_B_C": "TYPE_1",  // A第一，B第二，C第三
      "B_A_C": "TYPE_2",
      "A_C_B": "TYPE_3"
    }
  }
}
```

#### 优势
- 考虑维度排序
- 可以生成组合结果
- 更细致的分析

---

### 7. **时间加权模式（Time-Weighted）**

#### 描述
根据用户答题时间调整分数，快速回答可能加分或减分。

#### 应用场景
- 反应速度测试
- 直觉测试
- 时间压力测试

#### 实现思路
```json
{
  "scoring_mode": "time_weighted",
  "scoring_config": {
    "base_scoring": "simple",  // 基础评分模式
    "time_weights": {
      "fast": {"max_seconds": 5, "multiplier": 1.2},
      "normal": {"min_seconds": 5, "max_seconds": 15, "multiplier": 1.0},
      "slow": {"min_seconds": 15, "multiplier": 0.8}
    }
  }
}
```

#### 优势
- 增加测试趣味性
- 可以测试反应速度
- 动态调整难度

#### 挑战
- 需要记录答题时间
- 可能不公平（网络慢的用户）

---

### 8. **部分匹配模式（Partial Match）**

#### 描述
对于多选题或复合答案，根据匹配程度给分。

#### 应用场景
- 多选题测试
- 知识测试
- 技能评估

#### 实现思路
```json
{
  "scoring_mode": "partial_match",
  "scoring_config": {
    "questions": {
      "1": {
        "correct_options": ["A", "B", "C"],  // 正确答案
        "full_score": 10,
        "partial_score_per_option": 3.33
      }
    }
  }
}
```

#### 优势
- 更公平的评分
- 适合多选题
- 鼓励部分正确

---

### 9. **标准制评分模式（Standards-Based）**

#### 描述
使用等级而非分数，如"掌握"、"接近掌握"、"需要改进"。

#### 应用场景
- 教育评估
- 技能测试
- 能力认证

#### 实现思路
```json
{
  "scoring_mode": "standards_based",
  "scoring_config": {
    "standards": {
      "MASTERED": {"min_percentage": 90},
      "PROFICIENT": {"min_percentage": 75, "max_percentage": 89},
      "DEVELOPING": {"min_percentage": 60, "max_percentage": 74},
      "NEEDS_IMPROVEMENT": {"max_percentage": 59}
    }
  }
}
```

#### 优势
- 更直观的结果
- 适合教育场景
- 避免分数焦虑

---

### 10. **自适应/动态难度模式（Adaptive）**

#### 描述
根据用户前面的答案，动态调整后续题目的难度或权重。

#### 应用场景
- 能力测试
- 诊断测试
- 个性化评估

#### 实现思路
```json
{
  "scoring_mode": "adaptive",
  "scoring_config": {
    "base_scoring": "simple",
    "adaptation_rules": {
      "if_score_high": {
        "condition": {"total_score": ">", "value": 10},
        "action": {"increase_difficulty": true, "weight_multiplier": 1.5}
      }
    }
  }
}
```

#### 优势
- 更精确的评估
- 个性化体验
- 减少题目数量

#### 挑战
- 实现复杂
- 需要实时计算
- 可能影响用户体验

---

### 11. **矩阵/交叉分析模式（Matrix）**

#### 描述
使用二维矩阵（如"内向-外向" × "理性-感性"）定位结果。

#### 应用场景
- 性格测试（如 DISC、MBTI）
- 行为分析
- 风格测试

#### 实现思路
```json
{
  "scoring_mode": "matrix",
  "scoring_config": {
    "axes": {
      "x": {"dimensions": ["INTROVERT", "EXTROVERT"], "label": "社交倾向"},
      "y": {"dimensions": ["THINKING", "FEELING"], "label": "决策方式"}
    },
    "matrix": {
      "INTROVERT_THINKING": "ISTJ",
      "INTROVERT_FEELING": "ISFJ",
      "EXTROVERT_THINKING": "ESTJ",
      "EXTROVERT_FEELING": "ESFJ"
    }
  }
}
```

#### 优势
- 直观的二维分析
- 经典的性格测试方法
- 易于可视化

---

### 12. **贝叶斯/概率模式（Bayesian/Probability）**

#### 描述
使用概率模型，根据答案计算各结果的后验概率。

#### 应用场景
- 诊断测试
- 风险评估
- 预测性测试

#### 实现思路
```json
{
  "scoring_mode": "bayesian",
  "scoring_config": {
    "prior_probabilities": {
      "TYPE_A": 0.3,
      "TYPE_B": 0.4,
      "TYPE_C": 0.3
    },
    "likelihoods": {
      "1": {
        "A": {"TYPE_A": 0.8, "TYPE_B": 0.2, "TYPE_C": 0.1},
        "B": {"TYPE_A": 0.1, "TYPE_B": 0.7, "TYPE_C": 0.2}
      }
    }
  }
}
```

#### 优势
- 统计上更严谨
- 可以处理不确定性
- 适合诊断场景

#### 挑战
- 需要概率数据
- 实现复杂
- 用户可能不理解

---

## 🎯 推荐优先级

### 高优先级（容易实现且实用）

1. **投票/计数模式** - 简单，应用广泛
2. **加权累加模式** - Simple 模式的增强版
3. **百分比阈值模式** - Custom 模式的扩展

### 中优先级（有一定价值）

4. **排名/排序模式** - 多维度测试的增强
5. **矩阵/交叉分析模式** - 经典性格测试方法
6. **部分匹配模式** - 多选题支持

### 低优先级（复杂或小众）

7. **路径/决策树模式** - 需要修改题目加载逻辑
8. **时间加权模式** - 需要记录时间数据
9. **自适应模式** - 实现复杂
10. **贝叶斯模式** - 需要概率数据，用户理解成本高

---

## 💡 实现建议

### 方式 1：扩展 Custom 模式

在 `ScoreEngine::scoreCustom` 中添加新的策略：

```php
case 'vote':
    return self::scoreCustomVote($testId, $answers, $optionsByQuestion, $config, $pdo);

case 'weighted_sum':
    return self::scoreCustomWeightedSum($testId, $answers, $optionsByQuestion, $config, $pdo);
```

**优势**：不需要修改数据库结构，只需要扩展代码。

### 方式 2：新增独立模式

在 `Constants.php` 中添加新的模式常量，在 `ScoreEngine::score` 中添加新的 case。

**优势**：更清晰，但需要更新数据库枚举。

### 方式 3：插件化架构

设计评分模式接口，允许动态加载评分策略。

**优势**：最灵活，但需要重构现有代码。

---

## 📝 总结

除了当前支持的 4 种评分模式，还有至少 **12 种**可能的扩展模式。建议：

1. **短期**：实现投票模式、加权累加模式（通过 Custom 模式扩展）
2. **中期**：考虑矩阵模式、排名模式（如果业务需要）
3. **长期**：根据实际需求评估是否需要路径模式、自适应模式等复杂功能

**记住**：不是所有模式都需要实现，选择对业务最有价值的模式！

