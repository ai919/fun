# AI 测验出题集中指南

> 面向需要让 AI 直接产出可导入 JSON 的策划/运营。遵循本文档即可一次性生成符合 `tools/quiz-import` Schema 的测验文件。

---

## 1. 工作流与硬性规则

1. **锁定评分模式**  
   - `simple`: 选项给分、总分落区间。  
   - `dimensions`: 多维度加权，取最高维度代码。  
   - `range`: 细分区间或题目分值跨度大。  
   - `custom`: vote / weighted_sum / percentage_threshold 等策略。  
   → 评分模式一旦确定，所有字段必须与该模式匹配。

2. **填满必填字段**  
   - `test`: `slug`（小写+短横线）、`title`、`description`、`tags`（1-8 个）、`status`。  
   - `questions`: ≥1 题；每题 `text` + ≥2 个选项，每个选项 `key`=单个大写字母、`text` 非空。  
   - `results`: ≥1 项；`code`、`title`、`description`。  
   - 详情参考 `tools/quiz-import/schema/quiz-import.schema.json`。

3. **统一选项键与排序**  
   - 同题内 `key` 去重，按 `A,B,C...` 递增。  
   - 题目默认按数组顺序展示，无需额外 `sort_order`。

4. **JSON 纯净**  
   - UTF-8、无注释、无多余字段。  
   - 所有数值用阿拉伯数字，避免“~”“以上”这类模糊描述。

---

## 2. 通用模板（复制后替换内容）

```json
{
  "test": {
    "slug": "<lowercase-slug>",
    "title": "<主标题>",
    "subtitle": "<副标题，可选>",
    "description": "<40-120 字说明>",
    "tags": ["心理", "性格"],
    "status": "draft",
    "scoring_mode": "<simple|dimensions|range|custom>",
    "display_mode": "single_page",
    "emoji": "🧠",
    "scoring_config": {}
  },
  "questions": [
    {
      "text": "题干 1",
      "hint": "可选提示",
      "options": [
        { "key": "A", "text": "选项 A" },
        { "key": "B", "text": "选项 B" }
      ]
    }
  ],
  "results": [
    {
      "code": "RESULT_KEY",
      "title": "结果标题",
      "description": "结果解释"
    }
  ]
}
```

---

## 3. 模式专属配置速查

| 模式 | 选项字段 | `scoring_config` | 结果要求 |
|------|----------|------------------|----------|
| `simple` | 每个选项需 `score_override`（整数/小数） | 可空 | 每个结果要有 `min_score` & `max_score`，闭区间不重叠 |
| `range` | 选项可复用 `score_override` 或统一 `option_scores` | `"option_scores": { "A":0, ... }` | 同 `simple`；如使用 `ranges`，需 `"ranges":[{"min":0,"max":5,"code":"LOW"}]` |
| `dimensions` | 仅需 `key`+`text` | `"dimensions": ["CAT","DOG"], "weights": { "1": { "A": {"CAT":2} } }` | 结果 `code` 必须在 `dimensions` 列表内；不能含 `min_score`/`max_score` |
| `custom` | 视策略而定：`map_result_code` (vote) / `score_override` (weighted_sum) | 需写 `"strategy": "<vote|weighted_sum|percentage_threshold|...>"` 与该策略要求的字段 | 按策略匹配；多数情况下不写分数区间 |

---

## 4. 示例

### 4.1 Simple：心智年龄

```json
{
  "test": {
    "slug": "mental-age-lite",
    "title": "你的心智年龄有多大？",
    "description": "6 道题推测你更偏少年派还是老灵魂。",
    "tags": ["心理", "生活"],
    "status": "draft",
    "scoring_mode": "simple"
  },
  "questions": [
    {
      "text": "周末你最想做什么？",
      "options": [
        { "key": "A", "text": "补觉到自然醒", "score_override": 0 },
        { "key": "B", "text": "看展/看剧", "score_override": 1 },
        { "key": "C", "text": "学习新技能", "score_override": 2 },
        { "key": "D", "text": "即兴旅行", "score_override": 3 }
      ]
    }
  ],
  "results": [
    {
      "code": "YOUTH",
      "title": "元气少年",
      "description": "活力值拉满，凡事好奇。",
      "min_score": 0,
      "max_score": 4
    },
    {
      "code": "MATURE",
      "title": "老灵魂",
      "description": "偏爱稳定和沉淀。",
      "min_score": 5,
      "max_score": 12
    }
  ]
}
```

### 4.2 Dimensions：动物原型

```json
{
  "test": {
    "slug": "animal-archetype-mini",
    "title": "测你是哪种动物原型",
    "description": "多维度分析你的社交、思考与行动偏好。",
    "tags": ["性格", "原型"],
    "status": "draft",
    "scoring_mode": "dimensions",
    "scoring_config": {
      "dimensions": ["CAT", "DOG", "OWL"],
      "weights": {
        "1": {
          "A": { "CAT": 2 },
          "B": { "DOG": 2 },
          "C": { "OWL": 2 }
        },
        "2": {
          "A": { "OWL": 1.5, "CAT": 0.5 },
          "B": { "DOG": 2 }
        }
      }
    }
  },
  "questions": [
    {
      "text": "陌生场合你会？",
      "options": [
        { "key": "A", "text": "观察再行动" },
        { "key": "B", "text": "主动聊天" },
        { "key": "C", "text": "随意切换" }
      ]
    }
  ],
  "results": [
    { "code": "CAT", "title": "猫系思考者", "description": "安静、敏锐。" },
    { "code": "DOG", "title": "狗系伙伴", "description": "外向、忠诚。" },
    { "code": "OWL", "title": "猫头鹰派", "description": "洞察、理性。" }
  ]
}
```

### 4.3 Custom（vote）：你是哪种宠物

```json
{
  "test": {
    "slug": "pet-match-vote",
    "title": "朋友眼中的你是哪种宠物？",
    "description": "投票式测验，每题直接映射结果。",
    "tags": ["性格"],
    "status": "draft",
    "scoring_mode": "custom",
    "scoring_config": {
      "strategy": "vote",
      "tie_breaker": "first"
    }
  },
  "questions": [
    {
      "text": "收到惊喜礼物你会？",
      "options": [
        { "key": "A", "text": "围着送礼人转圈", "map_result_code": "DOG" },
        { "key": "B", "text": "淡定拆开研究", "map_result_code": "CAT" },
        { "key": "C", "text": "趴着享受抚摸", "map_result_code": "SEAL" }
      ]
    }
  ],
  "results": [
    { "code": "DOG", "title": "狗狗型", "description": "热情直接。" },
    { "code": "CAT", "title": "猫猫型", "description": "独立克制。" },
    { "code": "SEAL", "title": "小海豹型", "description": "佛系治愈。" }
  ]
}
```

---

## 5. 验证清单（AI 生成后逐条自检）

- **结构**  
  - [ ] JSON 顶层只含 `test`、`questions`、`results`。  
  - [ ] 无多余逗号、注释或 Markdown。

- **test 节**  
  - [ ] `slug` 符合 `^[a-z0-9-]+$` 且 ≤80 字符。  
  - [ ] `tags` 1-8 个，互不重复。  
  - [ ] `scoring_mode` 与内容一致，`scoring_config` 填全所需字段。

- **questions 节**  
  - [ ] 每题 ≥2 个选项，每个 `key` 唯一。  
  - [ ] Simple/Range 选项都有分数来源（`score_override` 或全局 `option_scores`）。  
  - [ ] Custom vote 选项写 `map_result_code` 并与结果对齐。

- **results 节**  
  - [ ] Simple/Range：每条写 `min_score`/`max_score`，区间连续且覆盖全部可能总分。  
  - [ ] Dimensions/Custom：不要写分数区间，`code` 要与维度或策略产出的代码一致。

- **语义**  
  - [ ] 题目数量 5-12（可按需求调整，但需在导入前确认）。  
  - [ ] 题干、选项为完整句子，无“以上皆是”等模糊项。  
  - [ ] 结果描述 ≥2 句，给出可执行建议或特征。

- **命令验证**  
  - [ ] 保存文件 `quiz-xxxx.json`。  
  - [ ] 运行 `yarn quiz:import quiz-xxxx.json --dry-run` 查看 Schema 校验结果。  
  - [ ] 根据 CLI 输出修正直至通过。

---

## 6. 常见失败原因速查

| 现象 | 定位方式 | 解决方案 |
|------|----------|----------|
| `scoring_mode` 缺失或与配置冲突 | CLI 报 `scoring_mode mismatch` | 明确设置模式并调整 `scoring_config` |
| 选项键重复 | Schema 校验失败 | 调整为唯一大写字母 |
| Simple 区间断层/重叠 | 导入成功但结果异常 | 重新计算总分范围，保证 `min_score` 从 0 开始连续递增 |
| Dimensions 命中 `code` 不存在 | 运行期崩溃 | 结果数组补齐所有维度代码 |
| Custom vote 未写 `map_result_code` | CLI 报缺字段 | 为每个选项添加映射 |

---

## 7. 参考资料

- `docs/COMPLETE_QUIZ_CREATION_GUIDE.md`：深入字段解释与更多示例。  
- `docs/QUIZ_CREATION_QUICK_REFERENCE.md`：速查模式要点。  
- `docs/SCORING_MODES.md`、`SCORING_SYSTEM_SUMMARY.md`：计分策略说明。  
- `docs/QUESTION_CREATION_GUIDE.md`：题目与选项写作规范。  
- `tools/quiz-import/schema/quiz-import.schema.json`：Schema 源文件。

> 将本指南作为 AI Prompt 的“系统指令”，即可显著提升一次成稿率。

