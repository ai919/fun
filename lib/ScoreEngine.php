<?php

class ScoreEngine
{
    private static array $lastDetail = [
        'total_score'      => 0,
        'dimension_scores' => [],
    ];

    public static function score(array $test, array $answers, PDO $pdo): ?string
    {
        self::$lastDetail = [
            'total_score'      => 0,
            'dimension_scores' => [],
        ];

        $mode = strtolower($test['scoring_mode'] ?? 'simple');
        return match ($mode) {
            'dimensions' => self::scoreDimensions($test, $answers, $pdo),
            'range'      => self::scoreRange($test, $answers, $pdo),
            'custom'     => self::scoreCustom($test, $answers, $pdo),
            default      => self::scoreSimple($test, $answers, $pdo),
        };
    }

    public static function getLastDetail(): array
    {
        return self::$lastDetail;
    }

    private static function scoreSimple(array $test, array $answers, PDO $pdo): ?string
    {
        $options = self::loadSelectedOptions($answers, (int)$test['id'], $pdo);
        if (!$options) {
            return null;
        }
        $scores = [];
        foreach ($options as $row) {
            $code = strtoupper(trim((string)$row['map_result_code']));
            if ($code === '') {
                continue;
            }
            $value = isset($row['score_value']) ? (float)$row['score_value'] : 1.0;
            if (!isset($scores[$code])) {
                $scores[$code] = 0.0;
            }
            $scores[$code] += $value;
        }
        if (!$scores) {
            return null;
        }
        $bestCode  = null;
        $bestScore = -INF;
        foreach ($scores as $code => $score) {
            if ($score > $bestScore) {
                $bestScore = $score;
                $bestCode  = $code;
            }
        }
        self::$lastDetail['total_score'] = $bestScore > 0 ? $bestScore : array_sum($scores);
        return $bestCode;
    }

    private static function scoreDimensions(array $test, array $answers, PDO $pdo): ?string
    {
        $options = self::loadSelectedOptions($answers, (int)$test['id'], $pdo);
        if (!$options) {
            return null;
        }

        $config = self::decodeConfig($test['scoring_config'] ?? null);
        $dimScores = [];
        foreach ($options as $row) {
            $map = trim((string)$row['map_result_code']);
            if ($map === '') {
                continue;
            }
            $segments = array_filter(array_map('trim', explode(',', $map)));
            $optionWeight = isset($row['score_value']) ? (float)$row['score_value'] : 1.0;
            foreach ($segments as $segment) {
                if (strpos($segment, ':') === false) {
                    continue;
                }
                [$dim, $val] = array_map('trim', explode(':', $segment, 2));
                if ($dim === '') {
                    continue;
                }
                $dim = strtoupper($dim);
                $value = is_numeric($val) ? (float)$val : 0.0;
                if (!isset($dimScores[$dim])) {
                    $dimScores[$dim] = 0.0;
                }
                $dimScores[$dim] += $value * $optionWeight;
            }
        }
        if (!$dimScores) {
            return null;
        }

        $tieBreakIE = strtoupper($config['tie_breaker']['IE'] ?? 'I');
        $tieBreakRF = strtoupper($config['tie_breaker']['RF'] ?? 'R');

        $I = $dimScores['I'] ?? 0.0;
        $E = $dimScores['E'] ?? 0.0;
        $R = $dimScores['R'] ?? 0.0;
        $F = $dimScores['F'] ?? 0.0;

        $axis1 = $I > $E ? 'I' : ($I < $E ? 'E' : $tieBreakIE);
        $axis2 = $R > $F ? 'R' : ($R < $F ? 'F' : $tieBreakRF);
        $resultCode = strtoupper($axis1 . $axis2);

        self::$lastDetail['dimension_scores'] = $dimScores;
        self::$lastDetail['total_score']      = array_sum($dimScores);

        return $resultCode;
    }

    private static function scoreRange(array $test, array $answers, PDO $pdo): ?string
    {
        // TODO: implement range-based scoring
        return null;
    }

    private static function scoreCustom(array $test, array $answers, PDO $pdo): ?string
    {
        // Placeholder for custom scripts
        return null;
    }

    private static function loadSelectedOptions(array $answers, int $testId, PDO $pdo): array
    {
        $optionIds = [];
        foreach ($answers as $value) {
            if (is_array($value)) {
                continue;
            }
            $optionIds[] = (int)$value;
        }
        $optionIds = array_values(array_unique(array_filter($optionIds, static fn($v) => $v > 0)));
        if (!$optionIds) {
            return [];
        }
        $placeholders = implode(',', array_fill(0, count($optionIds), '?'));
        $stmt = $pdo->prepare(
            "SELECT qo.*, q.test_id
             FROM question_options qo
             JOIN questions q ON q.id = qo.question_id
             WHERE qo.id IN ($placeholders)"
        );
        $stmt->execute($optionIds);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!$rows || count($rows) !== count($optionIds)) {
            return [];
        }
        foreach ($rows as $row) {
            if ((int)$row['test_id'] !== $testId) {
                return [];
            }
        }
        return $rows;
    }

    private static function decodeConfig($config): array
    {
        if (is_array($config)) {
            return $config;
        }
        if (is_string($config) && $config !== '') {
            $decoded = json_decode($config, true);
            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                return $decoded;
            }
        }
        return [];
    }
}
