<?php

/**
 * 根据选中的选项 ID 计算各维度得分
 *
 * @param PDO $pdo
 * @param int[] $optionIds
 * @return array ['anxiety' => 10, 'avoidance' => 5, ...]
 */
function calculateDimensionScores(PDO $pdo, array $optionIds): array
{
    if (!$optionIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
    $stmt = $pdo->prepare("SELECT dimension_key, score FROM options WHERE id IN ($placeholders)");
    $stmt->execute($optionIds);

    $scores = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $dim = $row['dimension_key'] ?: 'unknown';
        if (!isset($scores[$dim])) {
            $scores[$dim] = 0;
        }
        $scores[$dim] += (int)$row['score'];
    }

    return $scores;
}

/**
 * 按维度和得分获取结果文案
 *
 * @param PDO $pdo
 * @param int $testId
 * @param string $dimensionKey
 * @param int $score
 * @return array|null
 */
function getResultForDimension(PDO $pdo, int $testId, string $dimensionKey, int $score): ?array
{
    $stmt = $pdo->prepare(
        "SELECT * FROM results 
         WHERE test_id = ? 
           AND dimension_key = ?
           AND ? BETWEEN range_min AND range_max
         LIMIT 1"
    );
    $stmt->execute([$testId, $dimensionKey, $score]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ?: null;
}
