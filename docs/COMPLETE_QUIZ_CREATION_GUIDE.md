# 测验题撰写完整指南

> **📌 重要提示**：本指南将帮助你创建符合系统要求的测验 JSON，确保导入成功且评分正确。

---

## 📋 目录

1. [快速开始](#快速开始)
2. [JSON 结构概览](#json-结构概览)
3. [评分模式详解](#评分模式详解)
4. [完整配置示例](#完整配置示例)
5. [验证和检查清单](#验证和检查清单)
6. [常见错误和解决方案](#常见错误和解决方案)
7. [如何申请新评分模式](#如何申请新评分模式)

> **💡 提示**：需要快速查阅？查看 [快速参考](./QUIZ_CREATION_QUICK_REFERENCE.md)

---

## 🚀 快速开始

### 第一步：确定评分模式

**⚠️ 必须明确指定评分模式！**

在创建测验前，你需要先确定使用哪种评分模式。系统支持以下 4 种模式：

| 评分模式 | 适用场景 | 配置复杂度 |
|---------|---------|-----------|
| `simple` | 简单分数累加，按区间匹配结果 | ⭐ 简单 |
| `dimensions` | 多维度分析，取最高维度 | ⭐⭐ 中等 |
| `range` | 精确区间控制 | ⭐⭐ 中等 |
| `custom` | 复杂计算逻辑（投票、加权、百分比等） | ⭐⭐⭐ 复杂 |

**选择建议**：
- 如果只是简单的分数累加 → 使用 `simple`
- 如果需要多维度分析（如性格类型） → 使用 `dimensions`
- 如果需要精确控制分数区间 → 使用 `range`
- 如果需要特殊计算逻辑 → 使用 `custom`

### 第二步：创建 JSON 文件

JSON 文件必须包含三个主要部分：
- `test` - 测验基本信息
- `questions` - 题目和选项
- `results` - 结果配置

### 第三步：验证 JSON

使用以下命令验证：
```bash
yarn quiz:import your-quiz.json --dry-run
```

---

## 📦 JSON 结构概览

### 基本结构

```json
{
  "test": {
    "slug": "your-quiz-slug",
    "title": "测验标题",
    "description": "测验描述",
    "tags": ["标签1", "标签2"],
    "status": "draft",
    "scoring_mode": "simple",
    "scoring_config": {}
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

### 字段说明

#### `test` 对象（必填字段）

| 字段 | 类型 | 必填 | 说明 | 示例 |
|-----|------|-----|------|------|
| `slug` | string | ✅ | 唯一标识符，小写字母、数字、短横线 | `"mental-age-test"` |
| `title` | string | ✅ | 测验标题，≤255 字符 | `"你的心智年龄有多大？"` |
| `description` | string | ✅ | 测验描述 | `"通过生活习惯判断..."` |
| `tags` | array | ✅ | 标签数组，1-8 个，唯一 | `["心理", "性格"]` |
| `status` | string | ✅ | 状态：`draft`/`published`/`archived` | `"draft"` |
| `scoring_mode` | string | ⚠️ | **评分模式，必须明确指定！** | `"simple"` |

#### `test` 对象（可选字段）

| 字段 | 类型 | 说明 | 示例 |
|-----|------|------|------|
| `subtitle` | string | 副标题，≤255 字符 | `"6 道题测出真实心态"` |
| `title_color` | string | 标题颜色，HEX 格式 | `"#4f46e5"` |
| `sort_order` | number | 排序值，≥0 | `0` |
| `scoring_config` | object | 评分配置（根据模式不同而不同） | 见下文 |
| `display_mode` | string | 显示模式：`single_page`/`step_by_step` | `"single_page"` |
| `emoji` | string | 表情符号 | `"🧠"` |
| `show_secondary_archetype` | boolean | 显示副原型 | `true` |
| `show_dimension_table` | boolean | 显示维度表 | `true` |

#### `questions` 数组

| 字段 | 类型 | 必填 | 说明 |
|-----|------|-----|------|
| `text` | string | ✅ | 题目文本 |
| `options` | array | ✅ | 选项数组，至少 2 个 |
| `hint` | string | ❌ | 提示文本 |

#### `options` 数组中的选项对象

| 字段 | 类型 | 必填 | 说明 |
|-----|------|-----|------|
| `key` | string | ✅ | 选项键，单个大写字母（A-Z） |
| `text` | string | ✅ | 选项文本 |
| `score_override` | number | ❌ | 选项分数覆盖值 |
| `map_result_code` | string | ❌ | 映射的结果代码（用于投票模式） |

#### `results` 数组

| 字段 | 类型 | 必填 | 说明 |
|-----|------|-----|------|
| `code` | string | ✅ | 结果代码，唯一，≤64 字符 |
| `title` | string | ✅ | 结果标题，≤255 字符 |
| `description` | string | ✅ | 结果描述 |
| `min_score` | number | ❌ | 最小分数（simple/range 模式需要） |
| `max_score` | number | ❌ | 最大分数（simple/range 模式需要） |
| `image_url` | string | ❌ | 结果图片 URL |
| `social_quote` | string | ❌ | 社交分享文案 |

---

## 🎯 评分模式详解

### 1. Simple 模式（简单累加）

**适用场景**：
- 简单的分数累加型测验
- 心理年龄测试
- 智商测试
- 简单的评估测试

**配置方法**：

```json
{
  "test": {
    "scoring_mode": "simple",
    "scoring_config": null
  },
  "questions": [
    {
      "text": "题目1",
      "options": [
        { "key": "A", "text": "选项A", "score_override": 0 },
        { "key": "B", "text": "选项B", "score_override": 1 },
        { "key": "C", "text": "选项C", "score_override": 2 }
      ]
    }
  ],
  "results": [
    {
      "code": "LOW",
      "title": "低分",
      "description": "你的得分较低",
      "min_score": 0,
      "max_score": 5
    },
    {
      "code": "HIGH",
      "title": "高分",
      "description": "你的得分较高",
      "min_score": 6,
      "max_score": 10
    }
  ]
}
```

**关键点**：
- ✅ 每个选项通过 `score_override` 设置分数
- ✅ 结果必须设置 `min_score` 和 `max_score`
- ✅ 分数区间必须连续且覆盖所有可能的分数
- ✅ 区间不能重叠

**计算逻辑**：
1. 累加所有选中选项的 `score_override` 值
2. 根据总分在 `results` 中查找 `min_score <= 总分 <= max_score` 的结果

---

### 2. Dimensions 模式（多维度）

**适用场景**：
- 性格类型测试（如 MBTI、九型人格）
- 多维度分析测试
- 原型测试

**配置方法**：

```json
{
  "test": {
    "scoring_mode": "dimensions",
    "scoring_config": {
      "dimensions": ["EXTROVERT", "INTROVERT", "AMBIVERT"],
      "weights": {
        "1": {
          "A": {"EXTROVERT": 2},
          "B": {"INTROVERT": 2},
          "C": {"AMBIVERT": 2}
        },
        "2": {
          "A": {"EXTROVERT": 1, "INTROVERT": 1},
          "B": {"EXTROVERT": 2},
          "C": {"INTROVERT": 2}
        }
      }
    }
  },
  "questions": [
    {
      "text": "在聚会上，你更倾向于？",
      "options": [
        { "key": "A", "text": "主动和陌生人聊天" },
        { "key": "B", "text": "和熟悉的朋友待在一起" },
        { "key": "C", "text": "看情况，有时主动有时被动" }
      ]
    },
    {
      "text": "周末你更喜欢？",
      "options": [
        { "key": "A", "text": "参加社交活动" },
        { "key": "B", "text": "独自在家休息" },
        { "key": "C", "text": "和几个好友小聚" }
      ]
    }
  ],
  "results": [
    {
      "code": "EXTROVERT",
      "title": "外向型",
      "description": "你是一个外向的人..."
    },
    {
      "code": "INTROVERT",
      "title": "内向型",
      "description": "你是一个内向的人..."
    },
    {
      "code": "AMBIVERT",
      "title": "中间型",
      "description": "你是一个平衡的人..."
    }
  ]
}
```

**关键点**：
- ✅ `dimensions` 数组定义所有维度名称
- ✅ `weights` 对象配置每个题目每个选项的维度权重
- ✅ 题目标识可以使用题目顺序（`"1"`, `"2"`）或 `question_id`
- ✅ 每个选项可以给多个维度加权（支持小数）
- ✅ 结果的 `code` 必须与维度名称匹配
- ✅ 结果**不需要** `min_score` 和 `max_score`

**计算逻辑**：
1. 累加各维度得分
2. 选择得分最高的维度
3. 根据维度名称在 `results` 中查找匹配的 `code`

**权重配置格式**：
```json
{
  "weights": {
    "题目序号或ID": {
      "选项键": {
        "维度名称": 权重值（支持小数）
      }
    }
  }
}
```

---

### 3. Range 模式（区间评分）

**适用场景**：
- 需要精确区间控制的测试
- 分数段较多的测试

**配置方法**：

```json
{
  "test": {
    "scoring_mode": "range",
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
      "text": "题目1",
      "options": [
        { "key": "A", "text": "选项A" },
        { "key": "B", "text": "选项B" },
        { "key": "C", "text": "选项C" },
        { "key": "D", "text": "选项D" }
      ]
    }
  ],
  "results": [
    {
      "code": "LOW",
      "title": "低分",
      "description": "你的得分较低",
      "min_score": 0,
      "max_score": 5
    },
    {
      "code": "MEDIUM",
      "title": "中分",
      "description": "你的得分中等",
      "min_score": 6,
      "max_score": 10
    },
    {
      "code": "HIGH",
      "title": "高分",
      "description": "你的得分较高",
      "min_score": 11,
      "max_score": 15
    }
  ]
}
```

**关键点**：
- ✅ 通过 `scoring_config.option_scores` 设置全局选项分数
- ✅ 或通过每个选项的 `score_override` 设置（优先级更高）
- ✅ 结果必须设置 `min_score` 和 `max_score`
- ✅ 分数区间必须连续且覆盖所有可能的分数

**计算逻辑**：
1. 累加所有选中选项的分数（优先使用 `score_override`，否则使用 `option_scores`）
2. 根据总分在 `results` 中查找匹配的区间

---

### 4. Custom 模式（自定义计算）

**适用场景**：
- 复杂的计算需求
- 投票/计数逻辑
- 加权平均
- 百分比计算
- 条件逻辑
- 多结果组合

Custom 模式支持多种子策略，通过 `scoring_config.strategy` 字段指定。

#### 4.1 投票策略 (`vote`)

**适用场景**：性格类型测试、偏好选择、分类测试

**配置方法**：

```json
{
  "test": {
    "scoring_mode": "custom",
    "scoring_config": {
      "strategy": "vote",
      "vote_threshold": 3,
      "tie_breaker": "first"
    }
  },
  "questions": [
    {
      "text": "你更像哪种动物？",
      "options": [
        { "key": "A", "text": "猫", "map_result_code": "CAT" },
        { "key": "B", "text": "狗", "map_result_code": "DOG" },
        { "key": "C", "text": "狐狸", "map_result_code": "FOX" }
      ]
    },
    {
      "text": "你的性格更像？",
      "options": [
        { "key": "A", "text": "独立自主", "map_result_code": "CAT" },
        { "key": "B", "text": "忠诚友好", "map_result_code": "DOG" },
        { "key": "C", "text": "聪明灵活", "map_result_code": "FOX" }
      ]
    }
  ],
  "results": [
    {
      "code": "CAT",
      "title": "猫系人格",
      "description": "你像猫一样独立..."
    },
    {
      "code": "DOG",
      "title": "狗系人格",
      "description": "你像狗一样忠诚..."
    },
    {
      "code": "FOX",
      "title": "狐狸系人格",
      "description": "你像狐狸一样聪明..."
    }
  ]
}
```

**关键点**：
- ✅ 每个选项必须设置 `map_result_code`，对应结果的 `code`
- ✅ 结果的 `code` 必须与 `map_result_code` 匹配
- ✅ 结果**不需要** `min_score` 和 `max_score`
- ✅ `vote_threshold`（可选）：最少投票数，默认 0
- ✅ `tie_breaker`（可选）：平局处理方式（`first`/`random`/`all`），默认 `first`

**计算逻辑**：
1. 统计每个 `map_result_code` 被选中的次数
2. 选择得票最多的结果代码

---

#### 4.2 加权累加策略 (`weighted_sum`)

**适用场景**：重要性不同的题目、能力测试、评估测试

**配置方法**：

```json
{
  "test": {
    "scoring_mode": "custom",
    "scoring_config": {
      "strategy": "weighted_sum",
      "question_weights": {
        "1": 1.0,
        "2": 2.0,
        "3": 0.5,
        "4": 1.5
      }
    }
  },
  "questions": [
    {
      "text": "题目1（权重1.0）",
      "options": [
        { "key": "A", "text": "选项A", "score_override": 5 },
        { "key": "B", "text": "选项B", "score_override": 3 }
      ]
    },
    {
      "text": "题目2（权重2.0）",
      "options": [
        { "key": "A", "text": "选项A", "score_override": 4 },
        { "key": "B", "text": "选项B", "score_override": 2 }
      ]
    }
  ],
  "results": [
    {
      "code": "LOW",
      "title": "低分",
      "description": "你的得分较低",
      "min_score": 0,
      "max_score": 10
    },
    {
      "code": "HIGH",
      "title": "高分",
      "description": "你的得分较高",
      "min_score": 11,
      "max_score": 20
    }
  ]
}
```

**关键点**：
- ✅ `question_weights` 配置每道题的权重
- ✅ 题目标识使用题目顺序（`"1"`, `"2"`）或 `question_id`
- ✅ 权重可以是任意正数（支持小数）
- ✅ 未配置的题目默认权重为 1.0
- ✅ 每个选项需要设置 `score_override`
- ✅ 结果需要设置 `min_score` 和 `max_score`

**计算公式**：
```
最终分数 = Σ(选项分数 × 题目权重)
```

---

#### 4.3 百分比阈值策略 (`percentage_threshold`)

**适用场景**：需要"平衡型"结果的测试、多维度性格测试、能力评估

**配置方法**：

```json
{
  "test": {
    "scoring_mode": "custom",
    "scoring_config": {
      "strategy": "percentage_threshold",
      "dimensions": ["A", "B", "C", "D"],
      "weights": {
        "1": {
          "A": {"A": 1},
          "B": {"B": 1},
          "C": {"C": 1},
          "D": {"D": 1}
        },
        "2": {
          "A": {"A": 2},
          "B": {"B": 2}
        }
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
  },
  "questions": [
    {
      "text": "题目1",
      "options": [
        { "key": "A", "text": "选项A" },
        { "key": "B", "text": "选项B" },
        { "key": "C", "text": "选项C" },
        { "key": "D", "text": "选项D" }
      ]
    }
  ],
  "results": [
    {
      "code": "BALANCED",
      "title": "平衡型",
      "description": "你的各维度得分很平衡"
    },
    {
      "code": "DOMINANT_A",
      "title": "A 型主导",
      "description": "你的 A 维度得分很高"
    },
    {
      "code": "DOMINANT_B",
      "title": "B 型主导",
      "description": "你的 B 维度得分很高"
    },
    {
      "code": "MIXED",
      "title": "混合型",
      "description": "你的得分分布较复杂"
    }
  ]
}
```

**关键点**：
- ✅ 需要定义 `dimensions` 和 `weights`（类似 dimensions 模式）
- ✅ `thresholds` 配置结果代码到阈值规则的映射
- ✅ 支持 `all_dimensions`（所有维度阈值检查）和 `dimension`（单个维度阈值检查）
- ✅ `default_result_code` 指定默认结果（当没有匹配时使用）
- ✅ 结果的 `code` 必须与 `thresholds` 中的键匹配

**计算逻辑**：
1. 计算各维度得分百分比
2. 根据百分比阈值匹配结果（而不是简单的"最高维度"）
3. 可以识别"平衡型"结果

---

#### 4.4 其他 Custom 策略

系统还支持以下策略（详细配置请参考 [评分模式详细说明](./SCORING_MODES.md)）：

- `percentage` - 百分比模式
- `weighted_average` - 加权平均模式
- `conditional` - 条件逻辑模式
- `multi_result` - 多结果组合模式

---

## 📝 完整配置示例

### 示例 1：Simple 模式完整示例

```json
{
  "test": {
    "slug": "mental-age-test",
    "title": "你的心智年龄有多大？",
    "subtitle": "6 道题测出真实心态",
    "description": "通过生活习惯、决策偏好，判断更贴近少年还是老灵魂。",
    "tags": ["心理", "性格"],
    "status": "draft",
    "scoring_mode": "simple",
    "display_mode": "single_page",
    "emoji": "🧠"
  },
  "questions": [
    {
      "text": "周末你最想做什么？",
      "options": [
        { "key": "A", "text": "补觉", "score_override": 0 },
        { "key": "B", "text": "看展", "score_override": 1 },
        { "key": "C", "text": "学习新技能", "score_override": 2 },
        { "key": "D", "text": "爬山", "score_override": 3 }
      ]
    },
    {
      "text": "遇到难题的第一反应是？",
      "options": [
        { "key": "A", "text": "先放一边", "score_override": 0 },
        { "key": "B", "text": "请教朋友", "score_override": 1 },
        { "key": "C", "text": "搜资料", "score_override": 2 },
        { "key": "D", "text": "立刻开干", "score_override": 3 }
      ]
    },
    {
      "text": "你更倾向于？",
      "options": [
        { "key": "A", "text": "稳定", "score_override": 0 },
        { "key": "B", "text": "冒险", "score_override": 2 },
        { "key": "C", "text": "平衡", "score_override": 1 }
      ]
    }
  ],
  "results": [
    {
      "code": "YOUTH",
      "title": "元气少年",
      "description": "热情且敢于尝试，一切都刚刚开始！",
      "min_score": 0,
      "max_score": 6,
      "social_quote": "我还是个少年！"
    },
    {
      "code": "BALANCED",
      "title": "稳重青年",
      "description": "权衡理性与感性，是队友最信赖的伙伴。",
      "min_score": 7,
      "max_score": 12,
      "social_quote": "稳就是帅"
    },
    {
      "code": "OLD_SOUL",
      "title": "老灵魂",
      "description": "经验丰富、洞察敏锐，对生活有自己的一套。",
      "min_score": 13,
      "max_score": 18,
      "image_url": "https://cdn.dofun.local/quiz/old-soul.png"
    }
  ]
}
```

### 示例 2：Dimensions 模式完整示例

```json
{
  "test": {
    "slug": "personality-animal",
    "title": "你是什么动物人格？",
    "subtitle": "5 道题测出你的动物原型",
    "description": "通过行为偏好，发现你内心的动物原型。",
    "tags": ["心理", "性格", "趣味"],
    "status": "draft",
    "scoring_mode": "dimensions",
    "scoring_config": {
      "dimensions": ["CAT", "DOG", "FOX", "DEER", "OWL"],
      "weights": {
        "1": {
          "A": {"CAT": 2},
          "B": {"DOG": 2},
          "C": {"FOX": 2},
          "D": {"DEER": 2}
        },
        "2": {
          "A": {"CAT": 1, "FOX": 1},
          "B": {"DOG": 2},
          "C": {"OWL": 2}
        },
        "3": {
          "A": {"DEER": 2},
          "B": {"OWL": 2},
          "C": {"CAT": 1, "DOG": 1}
        }
      }
    },
    "display_mode": "step_by_step",
    "emoji": "🐾",
    "show_secondary_archetype": true,
    "show_dimension_table": true
  },
  "questions": [
    {
      "text": "在聚会上，你更倾向于？",
      "options": [
        { "key": "A", "text": "独自在角落观察" },
        { "key": "B", "text": "和所有人热情互动" },
        { "key": "C", "text": "和几个有趣的人深入交流" },
        { "key": "D", "text": "安静地享受氛围" }
      ]
    },
    {
      "text": "遇到问题时，你更倾向于？",
      "options": [
        { "key": "A", "text": "独立思考，寻找创新方案" },
        { "key": "B", "text": "寻求团队帮助" },
        { "key": "C", "text": "分析利弊，做出最优决策" }
      ]
    },
    {
      "text": "你更看重？",
      "options": [
        { "key": "A", "text": "和谐与平衡" },
        { "key": "B", "text": "智慧与洞察" },
        { "key": "C", "text": "自由与独立" }
      ]
    }
  ],
  "results": [
    {
      "code": "CAT",
      "title": "猫系人格",
      "description": "你像猫一样独立、优雅，喜欢独处但也享受陪伴。",
      "image_url": "https://cdn.dofun.local/quiz/cat.png"
    },
    {
      "code": "DOG",
      "title": "狗系人格",
      "description": "你像狗一样忠诚、友好，是团队中最可靠的伙伴。",
      "image_url": "https://cdn.dofun.local/quiz/dog.png"
    },
    {
      "code": "FOX",
      "title": "狐狸系人格",
      "description": "你像狐狸一样聪明、灵活，总能找到最佳解决方案。",
      "image_url": "https://cdn.dofun.local/quiz/fox.png"
    },
    {
      "code": "DEER",
      "title": "鹿系人格",
      "description": "你像鹿一样温和、敏感，追求和谐与平衡。",
      "image_url": "https://cdn.dofun.local/quiz/deer.png"
    },
    {
      "code": "OWL",
      "title": "猫头鹰系人格",
      "description": "你像猫头鹰一样智慧、深思，喜欢深入思考问题。",
      "image_url": "https://cdn.dofun.local/quiz/owl.png"
    }
  ]
}
```

### 示例 3：Custom 投票模式完整示例

```json
{
  "test": {
    "slug": "animal-personality-vote",
    "title": "你更像哪种动物？",
    "description": "通过选择偏好，发现你的动物原型。",
    "tags": ["心理", "趣味"],
    "status": "draft",
    "scoring_mode": "custom",
    "scoring_config": {
      "strategy": "vote",
      "vote_threshold": 2,
      "tie_breaker": "first"
    },
    "emoji": "🐾"
  },
  "questions": [
    {
      "text": "你更像哪种动物？",
      "options": [
        { "key": "A", "text": "猫", "map_result_code": "CAT" },
        { "key": "B", "text": "狗", "map_result_code": "DOG" },
        { "key": "C", "text": "狐狸", "map_result_code": "FOX" }
      ]
    },
    {
      "text": "你的性格更像？",
      "options": [
        { "key": "A", "text": "独立自主", "map_result_code": "CAT" },
        { "key": "B", "text": "忠诚友好", "map_result_code": "DOG" },
        { "key": "C", "text": "聪明灵活", "map_result_code": "FOX" }
      ]
    },
    {
      "text": "你更喜欢？",
      "options": [
        { "key": "A", "text": "独处", "map_result_code": "CAT" },
        { "key": "B", "text": "社交", "map_result_code": "DOG" },
        { "key": "C", "text": "小团体", "map_result_code": "FOX" }
      ]
    }
  ],
  "results": [
    {
      "code": "CAT",
      "title": "猫系人格",
      "description": "你像猫一样独立、优雅。",
      "image_url": "https://cdn.dofun.local/quiz/cat.png"
    },
    {
      "code": "DOG",
      "title": "狗系人格",
      "description": "你像狗一样忠诚、友好。",
      "image_url": "https://cdn.dofun.local/quiz/dog.png"
    },
    {
      "code": "FOX",
      "title": "狐狸系人格",
      "description": "你像狐狸一样聪明、灵活。",
      "image_url": "https://cdn.dofun.local/quiz/fox.png"
    }
  ]
}
```

---

## ✅ 验证和检查清单

### 导入前检查

在导入 JSON 前，请确保：

#### 1. JSON 格式检查

- [ ] JSON 格式正确（无语法错误）
- [ ] 无注释、BOM 或 Markdown 标记
- [ ] UTF-8 编码

#### 2. 必填字段检查

- [ ] `test.slug` 已填写且符合格式（小写字母、数字、短横线）
- [ ] `test.title` 已填写且 ≤255 字符
- [ ] `test.description` 已填写
- [ ] `test.tags` 已填写且 1-8 个，唯一
- [ ] `test.status` 已填写且为 `draft`/`published`/`archived`
- [ ] **`test.scoring_mode` 已明确指定** ⚠️
- [ ] `questions` 数组至少 1 题
- [ ] 每题的 `text` 已填写
- [ ] 每题的 `options` 至少 2 个
- [ ] 每个选项的 `key` 已填写且为单个大写字母（A-Z）
- [ ] 每个选项的 `text` 已填写
- [ ] `results` 数组至少 1 个结果
- [ ] 每个结果的 `code`、`title`、`description` 已填写

#### 3. 评分模式特定检查

**Simple 模式**：
- [ ] 每个选项设置了 `score_override`
- [ ] 每个结果设置了 `min_score` 和 `max_score`
- [ ] 分数区间连续且覆盖所有可能的分数
- [ ] 区间不重叠

**Dimensions 模式**：
- [ ] `scoring_config.dimensions` 数组已定义
- [ ] `scoring_config.weights` 对象已配置
- [ ] 每个题目的每个选项都有权重配置
- [ ] 结果的 `code` 与维度名称匹配
- [ ] 结果**没有**设置 `min_score` 和 `max_score`

**Range 模式**：
- [ ] `scoring_config.option_scores` 已配置或每个选项设置了 `score_override`
- [ ] 每个结果设置了 `min_score` 和 `max_score`
- [ ] 分数区间连续且覆盖所有可能的分数

**Custom 模式**：
- [ ] `scoring_config.strategy` 已指定
- [ ] 根据策略类型检查相应配置（见上文各策略说明）

#### 4. 数据一致性检查

- [ ] 选项 `key` 在同一题目内不重复
- [ ] 结果 `code` 不重复
- [ ] 题目数量合理（建议 5-20 题）
- [ ] 选项数量合理（建议 3-5 个）

### 验证命令

```bash
# 1. 使用 CLI 工具验证（推荐）
yarn quiz:import your-quiz.json --dry-run

# 2. 使用 PHP 诊断工具（导入后）
php check_specific_test.php <test_id>
```

### 测试建议

导入后，建议测试以下场景：

1. **极端情况**：
   - 全部选第一个选项
   - 全部选最后一个选项
   - 全部选中间选项

2. **边界情况**：
   - 分数刚好在区间边界
   - 维度得分相同（检查 tie_breaker）

3. **正常情况**：
   - 随机选择选项
   - 平衡选择选项

---

## 🚨 常见错误和解决方案

### 错误 1：未指定评分模式

**错误信息**：
```
scoring_mode 未指定或为默认值
```

**解决方案**：
- 在 `test` 对象中明确设置 `scoring_mode` 字段
- 不要依赖自动识别功能

### 错误 2：分数区间不连续

**错误示例**：
```json
{
  "results": [
    { "code": "LOW", "min_score": 0, "max_score": 5 },
    { "code": "HIGH", "min_score": 7, "max_score": 10 }  // ❌ gap: 6 分没有结果
  ]
}
```

**解决方案**：
```json
{
  "results": [
    { "code": "LOW", "min_score": 0, "max_score": 5 },
    { "code": "HIGH", "min_score": 6, "max_score": 10 }  // ✅ 连续
  ]
}
```

### 错误 3：维度名称与结果 code 不匹配

**错误示例**：
```json
{
  "scoring_config": {
    "dimensions": ["EXTROVERT", "INTROVERT"]
  },
  "results": [
    { "code": "E", "title": "外向" },  // ❌ 不匹配
    { "code": "I", "title": "内向" }   // ❌ 不匹配
  ]
}
```

**解决方案**：
```json
{
  "scoring_config": {
    "dimensions": ["EXTROVERT", "INTROVERT"]
  },
  "results": [
    { "code": "EXTROVERT", "title": "外向" },  // ✅ 匹配
    { "code": "INTROVERT", "title": "内向" }   // ✅ 匹配
  ]
}
```

### 错误 4：权重配置不完整

**错误示例**：
```json
{
  "weights": {
    "1": {
      "A": {"EXTROVERT": 2},
      "B": {"INTROVERT": 2}
      // ❌ 缺少选项 C 的权重
    }
  }
}
```

**解决方案**：
```json
{
  "weights": {
    "1": {
      "A": {"EXTROVERT": 2},
      "B": {"INTROVERT": 2},
      "C": {"EXTROVERT": 1, "INTROVERT": 1}  // ✅ 补充完整
    }
  }
}
```

### 错误 5：选项 key 重复

**错误示例**：
```json
{
  "options": [
    { "key": "A", "text": "选项1" },
    { "key": "A", "text": "选项2" }  // ❌ 重复
  ]
}
```

**解决方案**：
```json
{
  "options": [
    { "key": "A", "text": "选项1" },
    { "key": "B", "text": "选项2" }  // ✅ 唯一
  ]
}
```

### 错误 6：投票模式 map_result_code 不匹配

**错误示例**：
```json
{
  "options": [
    { "key": "A", "text": "猫", "map_result_code": "CAT" }
  ],
  "results": [
    { "code": "DOG", "title": "狗" }  // ❌ 不匹配
  ]
}
```

**解决方案**：
```json
{
  "options": [
    { "key": "A", "text": "猫", "map_result_code": "CAT" }
  ],
  "results": [
    { "code": "CAT", "title": "猫" }  // ✅ 匹配
  ]
}
```

---

## 🔧 如何申请新评分模式

如果你需要的评分逻辑不在现有模式中，可以申请添加新评分模式。

### 申请流程

1. **明确需求**：
   - 描述你的评分逻辑
   - 说明为什么现有模式无法满足需求
   - 提供具体的计算示例

2. **填写申请表单**：

在 JSON 文件中添加以下字段：

```json
{
  "test": {
    "scoring_mode": "custom",
    "scoring_config": {
      "strategy": "your_new_strategy",
      "note": "需要新增评分策略：描述你的需求",
      "calculation_example": "计算逻辑示例",
      "use_case": "使用场景说明"
    }
  }
}
```

或者，在 `test` 对象中添加：

```json
{
  "test": {
    "scoring_mode": "custom",
    "scoring_config": {
      "strategy": "proposed_new_strategy",
      "requirements": {
        "description": "详细描述你的评分需求",
        "calculation_logic": "描述计算逻辑",
        "example": "提供具体示例",
        "why_not_existing": "说明为什么现有模式无法满足"
      }
    },
    "new_scoring_mode_request": {
      "name": "新评分模式名称",
      "description": "详细描述",
      "use_cases": ["使用场景1", "使用场景2"],
      "example_config": {
        "strategy": "new_strategy",
        "param1": "value1"
      }
    }
  }
}
```

3. **提交申请**：
   - 将 JSON 文件提交给开发团队
   - 或通过后台管理界面提交

### 申请示例

```json
{
  "test": {
    "slug": "example-new-mode",
    "title": "示例：申请新评分模式",
    "description": "这是一个申请新评分模式的示例",
    "tags": ["示例"],
    "status": "draft",
    "scoring_mode": "custom",
    "scoring_config": {
      "strategy": "proposed_rank_based",
      "note": "需要新增基于排名的评分策略",
      "requirements": {
        "description": "根据选项的排名顺序计算分数，而不是固定分数",
        "calculation_logic": "如果用户选择了排名第1的选项，得3分；排名第2得2分；排名第3得1分",
        "example": "题目有3个选项，用户选择顺序为：A(第1)、B(第2)、C(第3)，则A得3分，B得2分，C得1分",
        "why_not_existing": "现有模式都是基于固定分数或权重，无法处理基于排名的动态计算"
      }
    },
    "new_scoring_mode_request": {
      "name": "rank_based",
      "description": "基于选项排名的评分模式",
      "use_cases": [
        "偏好排序测试",
        "重要性评估测试"
      ],
      "example_config": {
        "strategy": "rank_based",
        "rank_scores": {
          "1": 3,
          "2": 2,
          "3": 1
        }
      }
    }
  },
  "questions": [
    {
      "text": "请按重要性排序以下选项",
      "options": [
        { "key": "A", "text": "选项A" },
        { "key": "B", "text": "选项B" },
        { "key": "C", "text": "选项C" }
      ]
    }
  ],
  "results": [
    {
      "code": "RESULT1",
      "title": "结果1",
      "description": "结果描述"
    }
  ]
}
```

### 开发团队处理流程

1. **评估需求**：开发团队会评估你的需求
2. **技术可行性**：确认技术实现方案
3. **开发实现**：如果通过，会开发新评分模式
4. **文档更新**：更新相关文档和示例
5. **通知反馈**：通知你新评分模式已可用

### 注意事项

- ⚠️ 新评分模式的开发需要时间，请提前申请
- ⚠️ 如果现有模式可以满足需求，建议优先使用现有模式
- ⚠️ 申请时请提供详细的需求说明和示例

---

## 📚 相关文档

- [出题注意事项指南](./QUESTION_CREATION_GUIDE.md) - 出题时的注意事项
- [评分模式详细说明](./SCORING_MODES.md) - 所有评分模式的详细说明
- [添加新评分模式扩展指南](./ADDING_NEW_SCORING_MODE.md) - 如何添加新评分模式（开发人员）
- [自动识别评分模式](./AUTO_DETECT_SCORING_MODE.md) - 自动识别功能说明
- [配置诊断工具](../check_tests_config.php) - 配置检查工具

---

## ✨ 总结

### 关键要点

1. **必须明确指定评分模式** - 不要依赖自动识别
2. **根据需求选择合适的模式** - 参考上文的适用场景
3. **仔细检查配置** - 使用验证清单
4. **测试不同场景** - 确保评分正确
5. **如有新需求，及时申请** - 通过申请流程提交

### 快速参考

| 需求 | 推荐模式 | 配置复杂度 |
|-----|---------|-----------|
| 简单分数累加 | `simple` | ⭐ |
| 多维度分析 | `dimensions` | ⭐⭐ |
| 精确区间控制 | `range` | ⭐⭐ |
| 投票/计数 | `custom` + `vote` | ⭐⭐ |
| 加权累加 | `custom` + `weighted_sum` | ⭐⭐ |
| 百分比阈值 | `custom` + `percentage_threshold` | ⭐⭐⭐ |
| 其他复杂逻辑 | `custom` + 新策略 | ⭐⭐⭐ |

---

**祝你出题顺利！如有问题，请参考相关文档或联系开发团队。** 🎉

