<?php

function calculateDimensionScores(PDO $pdo, array $optionIds, int $testId): array
{
    $optionIds = array_values(array_unique(array_map('intval', $optionIds)));
    if (!$optionIds) {
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
    $stmt = $pdo->prepare(
        "SELECT o.id, o.dimension_key, o.score, q.test_id
         FROM options o
         INNER JOIN questions q ON q.id = o.question_id
         WHERE o.id IN ($placeholders)"
    );
    $stmt->execute($optionIds);

    $scores = [];
    $seen   = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $optionId = (int)$row['id'];
        $seen[$optionId] = true;
        if ((int)$row['test_id'] !== $testId) {
            throw new InvalidArgumentException('选项与测试不匹配');
        }
        $dim = $row['dimension_key'] ?: 'unknown';
        if (!isset($scores[$dim])) {
            $scores[$dim] = 0;
        }
        $scores[$dim] += (int)$row['score'];
    }

    if (count($seen) !== count($optionIds)) {
        throw new InvalidArgumentException('存在无效选项');
    }

    return $scores;
}

function getResultByTotalScore(PDO $pdo, int $testId, int $totalScore): ?array
{
    $stmt = $pdo->prepare(
        "SELECT *
         FROM results
         WHERE test_id = :test_id
           AND :score BETWEEN min_score AND max_score
         ORDER BY min_score ASC
         LIMIT 1"
    );
    $stmt->execute([
        ':test_id' => $testId,
        ':score'   => $totalScore,
    ]);
    $res = $stmt->fetch(PDO::FETCH_ASSOC);
    return $res ?: null;
}
