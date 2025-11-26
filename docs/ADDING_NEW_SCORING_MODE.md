# 添加新评分模式扩展指南

## 概述

系统支持两种方式扩展评分模式：

1. **作为新的主模式**（如 `simple`, `dimensions`, `range`, `custom`）
2. **作为 Custom 模式的子策略**（如 `vote`, `weighted_sum`, `percentage_threshold`）

**推荐方式**：优先使用 **Custom 模式的子策略**，因为：
- 更灵活，不需要修改核心架构
- 更容易维护和测试
- 可以复用现有的维度计算逻辑
- 自动识别功能更容易扩展

---

## 方式一：添加 Custom 模式的子策略（推荐）

### 适用场景

当新的评分模式：
- 可以基于现有的维度计算或分数计算
- 需要特殊的匹配逻辑（如阈值、条件判断）
- 不需要完全不同的数据结构

### 实现步骤

#### 1. 在 `ScoreEngine.php` 中添加策略处理

**文件位置**：`lib/ScoreEngine.php`

**步骤**：

1. 在 `scoreCustom()` 方法的 `switch` 语句中添加新的 case：

```php
// 在 lib/ScoreEngine.php 的 scoreCustom() 方法中
switch ($strategy) {
    // ... 现有策略 ...
    
    case 'your_new_strategy':  // 你的新策略名称
        return self::scoreCustomYourNewStrategy($testId, $answers, $optionsByQuestion, $config, $pdo);
    
    // ... 其他策略 ...
}
```

2. 实现新的策略方法：

```php
/**
 * custom 策略：你的新策略名称
 * 
 * @param int $testId 测试ID
 * @param array $answers 用户答案 [question_id => option_id]
 * @param array $optionsByQuestion [question_id][option_id] => option_row
 * @param array|null $config scoring_config
 * @param \PDO $pdo 数据库连接
 * @return array 评分结果
 */
protected static function scoreCustomYourNewStrategy(
    int $testId,
    array $answers,
    array $optionsByQuestion,
    ?array $config,
    \PDO $pdo
): array {
    // 1. 验证配置
    if (!$config || !isset($config['your_required_field'])) {
        throw new \InvalidArgumentException('your_new_strategy 需要 your_required_field 配置');
    }
    
    // 2. 计算分数或维度得分
    // 可以复用现有的 scoreDimensions() 或 scoreSimple() 方法
    $dimResult = self::scoreDimensions($testId, $answers, $optionsByQuestion, $config, $pdo);
    $dimensionScores = $dimResult['dimension_scores'] ?? [];
    
    // 3. 实现你的特殊逻辑
    $selectedCode = null;
    // ... 你的匹配逻辑 ...
    
    // 4. 查找结果
    $result = self::findResultByCode($testId, $selectedCode, $pdo);
    
    // 5. 返回标准格式
    return [
        'result_id' => $result['id'] ?? null,
        'result_code' => $selectedCode,
        'total_score' => array_sum($dimensionScores),
        'dimension_scores' => $dimensionScores,
        'detail' => [
            // 可选的详细信息
        ],
    ];
}
```

#### 2. 更新自动识别功能（可选）

如果你希望系统能够自动识别新策略，需要更新自动识别逻辑。

**文件位置**：
- PHP: `lib/QuizImporter.php` → `detectScoringMode()` 方法
- TypeScript: `tools/quiz-import/import-quiz.ts` → `detectScoringMode()` 函数

**示例**（在 `detectScoringMode()` 方法中添加）：

```php
// 在 lib/QuizImporter.php 的 detectScoringMode() 方法中
private function detectScoringMode(array $payload): array
{
    $test = $payload['test'] ?? [];
    $config = $test['scoring_config'] ?? [];
    
    // ... 现有识别逻辑 ...
    
    // 添加你的新策略识别
    if (isset($config['your_required_field'])) {
        return [
            'mode' => 'custom',
            'config' => array_merge($config, ['strategy' => 'your_new_strategy'])
        ];
    }
    
    // ... 其他识别逻辑 ...
}
```

#### 3. 更新文档

更新以下文档文件：

1. **`docs/SCORING_MODES.md`** - 添加新策略的说明
2. **`docs/AUTO_DETECT_SCORING_MODE.md`** - 如果支持自动识别，添加识别规则
3. **`SCORING_SYSTEM_SUMMARY.md`** - 在功能列表中添加新策略
4. **`tools/quiz-import/README.md`** - 在配置示例中添加新策略示例

#### 4. 更新 Schema（如果配置结构有变化）

**文件位置**：`tools/quiz-import/schema/quiz-import.schema.json`

如果新策略需要新的配置字段，需要在 schema 中添加：

```json
{
  "properties": {
    "scoring_config": {
      "properties": {
        "strategy": {
          "enum": [
            "percentage",
            "weighted_average",
            "conditional",
            "multi_result",
            "vote",
            "weighted_sum",
            "percentage_threshold",
            "your_new_strategy"  // 添加新策略
          ]
        },
        "your_required_field": {  // 添加新字段
          "type": "string",
          "description": "你的新字段说明"
        }
      }
    }
  }
}
```

#### 5. 测试

创建测试用例验证新策略：

```php
// 测试文件：test_your_new_strategy.php
<?php
require_once 'lib/ScoreEngine.php';
require_once 'lib/Database.php';

// 1. 准备测试数据
$testId = 1;  // 使用测试用的 test_id
$answers = [
    1 => 10,  // question_id => option_id
    2 => 15,
];

// 2. 准备配置
$config = [
    'strategy' => 'your_new_strategy',
    'your_required_field' => 'your_value',
    // ... 其他配置
];

// 3. 执行评分
$result = ScoreEngine::score($testId, $answers, $config);

// 4. 验证结果
assert($result['result_code'] === 'EXPECTED_CODE');
echo "测试通过！\n";
```

---

## 方式二：添加新的主评分模式

### 适用场景

当新的评分模式：
- 需要完全不同的数据结构
- 无法通过 Custom 模式实现
- 需要独立的数据库字段或表结构

### 实现步骤

#### 1. 在 `Constants.php` 中添加常量

**文件位置**：`lib/Constants.php`

```php
// 在 Constants 类中添加
const SCORING_MODE_YOUR_NEW_MODE = 'your_new_mode';

// 更新 getScoringModes() 方法
public static function getScoringModes(): array
{
    return [
        self::SCORING_MODE_SIMPLE,
        self::SCORING_MODE_DIMENSIONS,
        self::SCORING_MODE_RANGE,
        self::SCORING_MODE_CUSTOM,
        self::SCORING_MODE_YOUR_NEW_MODE,  // 添加新模式
    ];
}

// 更新 getScoringModeLabels() 方法
public static function getScoringModeLabels(): array
{
    return [
        // ... 现有标签 ...
        self::SCORING_MODE_YOUR_NEW_MODE => 'Your New Mode（你的新模式）',
    ];
}
```

#### 2. 在 `ScoreEngine.php` 中添加处理逻辑

**文件位置**：`lib/ScoreEngine.php`

1. 在 `score()` 方法的 `switch` 语句中添加：

```php
// 在 lib/ScoreEngine.php 的 score() 方法中
switch ($mode) {
    // ... 现有模式 ...
    
    case Constants::SCORING_MODE_YOUR_NEW_MODE:
        $detail = self::scoreYourNewMode($testId, $normalizedAnswers, $optionsByQuestion, $config, $pdo);
        break;
    
    // ... 其他模式 ...
}
```

2. 实现新的评分方法：

```php
/**
 * your_new_mode 模式：你的新模式说明
 * 
 * @param int $testId
 * @param array $answers
 * @param array $optionsByQuestion
 * @param array|null $config
 * @param \PDO $pdo
 * @return array
 */
protected static function scoreYourNewMode(
    int $testId,
    array $answers,
    array $optionsByQuestion,
    ?array $config,
    \PDO $pdo
): array {
    // 实现你的评分逻辑
    // ...
    
    return [
        'result_id' => $resultId,
        'result_code' => $resultCode,
        'total_score' => $totalScore,
        // ... 其他字段
    ];
}
```

#### 3. 更新导入工具

**文件位置**：
- PHP: `lib/QuizImporter.php`
- TypeScript: `tools/quiz-import/import-quiz.ts`

在 `detectScoringMode()` 方法中添加识别逻辑（如果需要自动识别）。

#### 4. 更新 Schema

**文件位置**：`tools/quiz-import/schema/quiz-import.schema.json`

```json
{
  "properties": {
    "test": {
      "properties": {
        "scoring_mode": {
          "enum": [
            "simple",
            "dimensions",
            "range",
            "custom",
            "your_new_mode"  // 添加新模式
          ]
        }
      }
    }
  }
}
```

#### 5. 更新所有文档

更新所有相关文档，包括：
- `docs/SCORING_MODES.md`
- `docs/AUTO_DETECT_SCORING_MODE.md`
- `SCORING_SYSTEM_SUMMARY.md`
- `tools/quiz-import/README.md`

---

## 完整示例：添加 "排名模式" 策略

假设我们要添加一个"排名模式"，根据各维度得分排名来选择结果。

### 1. 实现策略方法

```php
// 在 lib/ScoreEngine.php 中添加

case 'ranking':
    return self::scoreCustomRanking($testId, $answers, $optionsByQuestion, $config, $pdo);

// 实现方法
protected static function scoreCustomRanking(
    int $testId,
    array $answers,
    array $optionsByQuestion,
    ?array $config,
    \PDO $pdo
): array {
    // 1. 先计算维度得分
    $dimResult = self::scoreDimensions($testId, $answers, $optionsByQuestion, $config, $pdo);
    $dimensionScores = $dimResult['dimension_scores'] ?? [];
    
    if (empty($dimensionScores)) {
        return $dimResult;  // 回退到 dimensions 模式
    }
    
    // 2. 按得分排序
    arsort($dimensionScores);
    $rankedDimensions = array_keys($dimensionScores);
    
    // 3. 根据排名规则选择结果
    $topN = $config['top_n'] ?? 1;  // 默认取前1名
    $selectedCode = null;
    
    if ($topN === 1) {
        // 取最高分
        $selectedCode = $rankedDimensions[0];
    } else {
        // 取前N名的组合（需要配置中有组合映射）
        $combination = array_slice($rankedDimensions, 0, $topN);
        sort($combination);  // 排序以保证一致性
        $combinationKey = implode('_', $combination);
        
        // 查找组合映射
        $combinationMap = $config['combination_map'] ?? [];
        $selectedCode = $combinationMap[$combinationKey] ?? $rankedDimensions[0];
    }
    
    // 4. 查找结果
    $result = self::findResultByCode($testId, $selectedCode, $pdo);
    
    return [
        'result_id' => $result['id'] ?? null,
        'result_code' => $selectedCode,
        'total_score' => array_sum($dimensionScores),
        'dimension_scores' => $dimensionScores,
        'detail' => [
            'ranking' => $rankedDimensions,
            'top_n' => $topN,
        ],
    ];
}
```

### 2. 更新自动识别（可选）

```php
// 在 lib/QuizImporter.php 的 detectScoringMode() 中添加
if (isset($config['top_n']) || isset($config['combination_map'])) {
    return [
        'mode' => 'custom',
        'config' => array_merge($config, ['strategy' => 'ranking'])
    ];
}
```

### 3. 配置示例

```json
{
  "scoring_mode": "custom",
  "scoring_config": {
    "strategy": "ranking",
    "dimensions": ["A", "B", "C", "D"],
    "weights": {
      "1": {
        "A": {"A": 2},
        "B": {"B": 2}
      }
    },
    "top_n": 2,
    "combination_map": {
      "A_B": "BALANCED_AB",
      "A_C": "BALANCED_AC"
    }
  }
}
```

---

## 测试和验证

### 1. 单元测试

创建测试文件验证新策略：

```php
<?php
require_once 'lib/ScoreEngine.php';
require_once 'lib/Database.php';

// 测试你的新策略
function testYourNewStrategy() {
    $testId = 1;
    $answers = [1 => 10, 2 => 15];
    $config = [
        'strategy' => 'your_new_strategy',
        // ... 配置
    ];
    
    $result = ScoreEngine::score($testId, $answers, $config);
    
    // 验证结果
    assert(isset($result['result_id']), '应该有 result_id');
    assert(isset($result['result_code']), '应该有 result_code');
    
    echo "✓ 测试通过\n";
}
```

### 2. 集成测试

使用诊断工具验证：

```bash
# 检查特定测试的配置
php check_specific_test.php <test_id>

# 检查所有测试的配置
php check_tests_config.php
```

### 3. 使用 Dry Run 验证导入

```bash
# 使用 CLI 工具验证 JSON 配置
yarn quiz:import your_test.json --dry-run
```

---

## 最佳实践

### 1. 代码组织

- **保持方法简洁**：每个策略方法应该只负责一种计算逻辑
- **复用现有代码**：优先使用 `scoreDimensions()` 或 `scoreSimple()` 作为基础
- **错误处理**：验证配置，提供清晰的错误信息

### 2. 配置设计

- **向后兼容**：新字段应该是可选的，或者有合理的默认值
- **文档完善**：在配置示例中说明每个字段的用途
- **类型明确**：在 Schema 中明确定义字段类型

### 3. 性能考虑

- **避免重复计算**：如果多个策略需要维度得分，先计算一次
- **数据库查询优化**：批量查询，避免 N+1 问题
- **缓存结果**：对于复杂的计算，考虑缓存中间结果

### 4. 测试覆盖

- **边界情况**：测试空配置、缺失字段、极端值
- **多种答案组合**：测试不同的答案组合
- **回退机制**：测试配置错误时的回退行为

---

## 常见问题

### Q: 我应该选择方式一还是方式二？

**A**: 
- 如果你的新评分模式可以基于维度或分数计算，使用**方式一**（Custom 子策略）
- 如果需要完全不同的数据结构或数据库设计，使用**方式二**（新主模式）

### Q: 如何确保新策略与现有功能兼容？

**A**: 
1. 运行现有的测试套件
2. 使用 `check_tests_config.php` 检查所有测试
3. 在测试环境中导入几个现有测试，确保它们仍然正常工作

### Q: 如果新策略需要修改数据库结构怎么办？

**A**: 
1. 创建数据库迁移脚本（`database/XXX_add_xxx_field.sql`）
2. 更新相关的模型类
3. 确保向后兼容（新字段应该有默认值或允许 NULL）

### Q: 如何让新策略支持自动识别？

**A**: 
1. 在 `detectScoringMode()` 方法中添加识别规则
2. 识别规则应该基于配置字段的存在或数据特征
3. 确保识别规则不会误判现有测试

---

## 总结

添加新评分模式的步骤：

1. ✅ **选择扩展方式**：Custom 子策略（推荐）或新主模式
2. ✅ **实现评分逻辑**：在 `ScoreEngine.php` 中添加方法
3. ✅ **更新自动识别**（可选）：在 `detectScoringMode()` 中添加规则
4. ✅ **更新 Schema**：在 `quiz-import.schema.json` 中添加配置定义
5. ✅ **更新文档**：更新所有相关文档
6. ✅ **测试验证**：创建测试用例，使用诊断工具验证

遵循这些步骤，你可以安全、高效地扩展评分系统！🎉

