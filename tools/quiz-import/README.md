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

## 使用方式

```bash
# 预览导入（不会写数据库）
yarn quiz:import ./payloads/mental_age.json --dry-run

# 覆盖同 slug 的现有测验
yarn quiz:import ./payloads/mental_age.json --overwrite
```

> **提示**：未加 `--overwrite` 时如 slug 已存在会直接终止。

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

