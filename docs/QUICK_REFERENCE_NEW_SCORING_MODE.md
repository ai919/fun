# 添加新评分模式 - 快速参考

## 🎯 两种扩展方式

### 方式一：Custom 子策略（推荐）⭐

**适用**：基于维度/分数计算的新逻辑

**步骤**：
1. 在 `lib/ScoreEngine.php` → `scoreCustom()` 添加 case
2. 实现 `scoreCustomYourStrategy()` 方法
3. 更新自动识别（可选）
4. 更新 Schema 和文档

**示例**：
```php
// 1. 添加 case
case 'your_strategy':
    return self::scoreCustomYourStrategy(...);

// 2. 实现方法
protected static function scoreCustomYourStrategy(...): array {
    // 计算逻辑
    return ['result_id' => ..., 'result_code' => ...];
}
```

---

### 方式二：新主模式

**适用**：需要完全不同数据结构的情况

**步骤**：
1. 在 `lib/Constants.php` 添加常量
2. 在 `ScoreEngine::score()` 添加 case
3. 实现 `scoreYourNewMode()` 方法
4. 更新 Schema、文档、自动识别

---

## 📝 必须修改的文件

### 核心文件
- ✅ `lib/ScoreEngine.php` - 添加评分逻辑
- ✅ `lib/Constants.php` - 添加常量（仅方式二）
- ✅ `lib/QuizImporter.php` - 更新自动识别（可选）
- ✅ `tools/quiz-import/import-quiz.ts` - 更新自动识别（可选）

### 配置和文档
- ✅ `tools/quiz-import/schema/quiz-import.schema.json` - 更新 Schema
- ✅ `docs/SCORING_MODES.md` - 添加说明
- ✅ `SCORING_SYSTEM_SUMMARY.md` - 更新功能列表
- ✅ `tools/quiz-import/README.md` - 添加配置示例

---

## 🔍 自动识别扩展

在 `detectScoringMode()` 中添加识别规则：

```php
// PHP: lib/QuizImporter.php
if (isset($config['your_required_field'])) {
    return [
        'mode' => 'custom',
        'config' => array_merge($config, ['strategy' => 'your_strategy'])
    ];
}
```

```typescript
// TypeScript: tools/quiz-import/import-quiz.ts
if (config?.your_required_field) {
  return {
    mode: 'custom',
    config: { ...config, strategy: 'your_strategy' }
  };
}
```

---

## ✅ 测试清单

- [ ] 单元测试：验证评分逻辑
- [ ] 集成测试：使用 `check_specific_test.php`
- [ ] 导入测试：使用 `yarn quiz:import --dry-run`
- [ ] 边界测试：空配置、缺失字段、极端值
- [ ] 兼容性测试：确保现有测试不受影响

---

## 📚 完整文档

详细指南：**[添加新评分模式扩展指南](./ADDING_NEW_SCORING_MODE.md)**

---

## 💡 最佳实践

1. **优先使用 Custom 子策略** - 更灵活、易维护
2. **复用现有方法** - 使用 `scoreDimensions()` 或 `scoreSimple()` 作为基础
3. **向后兼容** - 新字段应该是可选的
4. **完善文档** - 提供配置示例和使用说明
5. **充分测试** - 覆盖各种边界情况

