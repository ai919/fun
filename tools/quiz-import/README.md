# Quiz Import CLI

一个面向运营/开发的命令行工具，可基于 JSON 配置在数据库内批量创建或覆盖测验、题目、选项及结果。适用于把策划稿快速落地到 DoFun 平台，而无需手动执行 SQL。

## 目录结构

```
tools/quiz-import/
├── README.md
├── import-quiz.ts                # CLI 主程序
├── schema/quiz-import.schema.json# JSON Schema（ide/后台可用于校验）
└── src/types.ts                  # TypeScript 类型定义
```

## 准备

1. 安装 Node.js (>= 18)。
2. 在项目根目录执行：

```bash
yarn install
```

3. 配置数据库连接：工具读取与 PHP 相同的环境变量：

| 变量         | 默认值      | 说明           |
|--------------|-------------|----------------|
| `DB_HOST`    | `127.0.0.1` | MySQL 主机     |
| `DB_PORT`    | `3306`      | MySQL 端口     |
| `DB_DATABASE`| `fun_quiz`  | 数据库名称     |
| `DB_USERNAME`| `root`      | 用户名         |
| `DB_PASSWORD`| `""`        | 密码           |
| `DB_CHARSET` | `utf8mb4`   | 连接字符集     |

可在 `.env` 中定义，工具会自动读取。

## JSON Schema

`schema/quiz-import.schema.json` 描述了导入所需结构，可在后台粘贴框、VSCode、Web IDE 中用于即时校验，避免导入失败。

关键字段：
- `test`：测验元信息（slug、标题、标签、计分模式等）
- `questions`：题干与选项，支持选项单独覆写分值
- `results`：分数区间与文案（可包含分享语、图片地址）

## JSON 导入规则

1. **文件要求**
   - 单个 UTF-8 编码 `.json` 文件，内容必须是合法 JSON；不要携带注释、BOM 或 Markdown。
   - 需通过 `schema/quiz-import.schema.json` 校验；推荐在编辑器接入 AJV、或运行 `yarn quiz:import <file> --dry-run` 即时验证。

2. **`test` 对象**
   - 必填：`slug`（≤80，`[a-z0-9-]`），`title`，`description`，`tags`（≤8 个、唯一且非空），`status`（`draft|published|archived`）。
- 可选：`subtitle`、`title_color`（HEX）、`sort_order`（≥0）、`scoring_mode`（默认 `simple`）、`scoring_config`、`display_mode`（默认 `single_page`）、`play_count_beautified`、`emoji`、`show_secondary_archetype`（默认 `true`）、`show_dimension_table`（默认 `true`）。导入时这两个布尔值会直连结果页 UI，可一键开启“副原型”卡片与“维度表”分布展示。
   - 若未填写 `emoji`，CLI 会基于显式值、标签映射或 slug 哈希自动填充。

3. **`questions` 数组**
   - 至少 1 题；每题包含 `text` 与 `options`，可配 `hint`。
   - `options` 至少 2 个，每个包含唯一的 `key`（单个大写字母）、`text`；可选 `map_result_code` 与 `score_override`。

4. **`results` 数组**
   - 至少 1 个结果，包含 `code`、`title`、`description`；可选 `image_url`、`min_score`、`max_score`、`social_quote`。
   - 若使用分数区间，确保区间连续覆盖全部可能得分，且 `min_score <= max_score`。

5. **计分逻辑**
   - `simple`：按 `map_result_code` 计数或默认投票。
   - `dimensions`：在 `scoring_config` 中定义维度权重，结果层可自定义解释。
   - `range`：使用 `scoring_config.option_scores` 或 `score_override` 给选项打分，再通过结果的 `min_score/max_score` 落档。
   - `custom`：工具只负责存储 JSON，业务侧自行解析。

6. **质量自检**
   - 跑 `yarn quiz:import payload.json --dry-run`，确认 Schema、slug、emoji、分数段等检查通过。
   - 题目/选项不可留空，标签应贴合主题，避免违规内容。
   - 如需上线数据库新字段或逻辑，先更新 schema、`src/types.ts`、`import-quiz.ts` 并同步 README 示例。

## 格式范例

```json
{
  "test": {
    "slug": "mental-age-2025",
    "title": "你的心智年龄有多大？",
    "subtitle": "6 道题测出真实心态",
    "description": "通过生活习惯、决策偏好，判断更贴近少年还是老灵魂。",
    "tags": ["心理", "性格"],
    "status": "draft",
    "scoring_mode": "range",
    "display_mode": "single_page",
    "emoji": "🧠",
    "show_secondary_archetype": true,
    "show_dimension_table": true,
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
        { "key": "A", "text": "先放一边", "map_result_code": "CHILL" },
        { "key": "B", "text": "请教朋友" },
        { "key": "C", "text": "搜资料" },
        { "key": "D", "text": "立刻开干" }
      ]
    }
  ],
  "results": [
    {
      "code": "YOUTH",
      "title": "元气少年",
      "description": "热情且敢于尝试，一切都刚刚开始！",
      "min_score": 0,
      "max_score": 6
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
      "image_url": "https://cdn.dofun/quiz/old-soul.png"
    }
  ]
}
```

## 使用方式

```bash
# 预览导入（不会写数据库）
yarn quiz:import ./payloads/mental_age.json --dry-run

# 覆盖同 slug 的现有测验
yarn quiz:import ./payloads/mental_age.json --overwrite
```

> **提示**：未加 `--overwrite` 时如 slug 已存在会直接终止。

> **提示**：CLI 同步写入 `tests.show_secondary_archetype` 与 `tests.show_dimension_table`，并在 `dimensions` 计分模式下导入 `scoring_config` 与维度分数映射，因此使用标准 JSON 即可获得“支持副原型／维度表”的完整体验，无需另外手工 SQL。

## Dry Run

`--dry-run` 会执行 Schema 校验及 slug 检查，但在真正写库前回滚事务，方便检查统计信息。

## 常见报错

| 提示 | 解决方案 |
|------|----------|
| `JSON Schema 校验失败` | 根据提示修复 payload 结构、必填字段、数据类型 |
| `slug 已存在` | 使用 `--overwrite` 或更换 slug |
| `ER_NO_SUCH_TABLE` | 确认数据库迁移已执行到最新 |

## 扩展

- 如需导入维度计分，可在 `scoring_config` 中设计更复杂结构，脚本默认透传到 `tests.scoring_config`，业务层读到即可使用。
- 若要绑定分享语/额外素材，可把内容放入 `results.social_quote`，回写后由后台读取 JSON 再渲染。

欢迎根据业务需要继续扩展脚本或把它接入后台上传入口。

## 后台入口

若已登录 DoFun 后台，可在左侧导航的「测验导入」页面直接上传或粘贴 JSON，UI 会实时显示 Dry run 结果并复用此脚本同样的校验/写库逻辑。适合运营同学无需命令行即可投放新测验。

