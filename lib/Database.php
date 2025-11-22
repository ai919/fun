<?php
/**
 * 数据库抽象层和查询构建器
 * 
 * 提供统一的数据库查询接口，支持链式调用
 * 支持查询日志和性能分析
 */
class Database
{
    private $pdo;
    private $table;
    private $select = '*';
    private $where = [];
    private $whereParams = [];
    private $orderBy = [];
    private $groupBy = [];
    private $having = [];
    private $limit = null;
    private $offset = null;
    private $joins = [];
    private static $queryLog = [];
    private static $queryLogEnabled = false;

    /**
     * 构造函数
     * 
     * @param PDO $pdo 数据库连接
     */
    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * 启用查询日志
     */
    public static function enableQueryLog()
    {
        self::$queryLogEnabled = true;
        self::$queryLog = [];
    }

    /**
     * 禁用查询日志
     */
    public static function disableQueryLog()
    {
        self::$queryLogEnabled = false;
    }

    /**
     * 获取查询日志
     * 
     * @return array
     */
    public static function getQueryLog(): array
    {
        return self::$queryLog;
    }

    /**
     * 记录查询日志
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数
     * @param float $time 执行时间（秒）
     */
    private function logQuery(string $sql, array $params, float $time)
    {
        if (self::$queryLogEnabled) {
            self::$queryLog[] = [
                'sql' => $sql,
                'params' => $params,
                'time' => $time,
                'timestamp' => microtime(true),
            ];
        }
    }

    /**
     * 设置表名
     * 
     * @param string $table 表名
     * @return self
     */
    public function table(string $table): self
    {
        $this->table = $table;
        return $this;
    }

    /**
     * 设置查询字段
     * 
     * @param string|array $columns 字段名或字段数组
     * @return self
     */
    public function select($columns = '*'): self
    {
        if (is_array($columns)) {
            $this->select = implode(', ', $columns);
        } else {
            $this->select = $columns;
        }
        return $this;
    }

    /**
     * WHERE 条件
     * 
     * @param string|array $column 字段名或条件数组
     * @param mixed $operator 操作符或值
     * @param mixed $value 值（当 $operator 为操作符时）
     * @return self
     */
    public function where($column, $operator = null, $value = null): self
    {
        if (is_array($column)) {
            // 数组形式：['field' => 'value', 'field2' => 'value2']
            foreach ($column as $key => $val) {
                $this->where($key, '=', $val);
            }
            return $this;
        }

        // 如果只传了两个参数，第二个参数作为值，操作符默认为 =
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * OR WHERE 条件
     * 
     * @param string $column 字段名
     * @param mixed $operator 操作符
     * @param mixed $value 值
     * @return self
     */
    public function orWhere(string $column, $operator = null, $value = null): self
    {
        if ($value === null) {
            $value = $operator;
            $operator = '=';
        }

        $this->where[] = [
            'type' => 'OR',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
        ];

        return $this;
    }

    /**
     * WHERE IN 条件
     * 
     * @param string $column 字段名
     * @param array $values 值数组
     * @return self
     */
    public function whereIn(string $column, array $values): self
    {
        $this->where[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => 'IN',
            'value' => $values,
        ];

        return $this;
    }

    /**
     * WHERE NULL 条件
     * 
     * @param string $column 字段名
     * @return self
     */
    public function whereNull(string $column): self
    {
        $this->where[] = [
            'type' => 'AND',
            'column' => $column,
            'operator' => 'IS NULL',
            'value' => null,
        ];

        return $this;
    }

    /**
     * JOIN 连接
     * 
     * @param string $table 表名
     * @param string $first 第一个字段
     * @param string $operator 操作符
     * @param string $second 第二个字段
     * @param string $type JOIN 类型（INNER, LEFT, RIGHT）
     * @return self
     */
    public function join(string $table, string $first, string $operator, string $second, string $type = 'INNER'): self
    {
        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $first,
            'operator' => $operator,
            'second' => $second,
        ];

        return $this;
    }

    /**
     * LEFT JOIN
     */
    public function leftJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'LEFT');
    }

    /**
     * RIGHT JOIN
     */
    public function rightJoin(string $table, string $first, string $operator, string $second): self
    {
        return $this->join($table, $first, $operator, $second, 'RIGHT');
    }

    /**
     * ORDER BY
     * 
     * @param string $column 字段名
     * @param string $direction 排序方向（ASC, DESC）
     * @return self
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orderBy[] = "$column $direction";
        return $this;
    }

    /**
     * GROUP BY
     * 
     * @param string|array $columns 字段名或字段数组
     * @return self
     */
    public function groupBy($columns): self
    {
        if (is_array($columns)) {
            $this->groupBy = array_merge($this->groupBy, $columns);
        } else {
            $this->groupBy[] = $columns;
        }
        return $this;
    }

    /**
     * LIMIT
     * 
     * @param int $limit 限制数量
     * @return self
     */
    public function limit(int $limit): self
    {
        $this->limit = $limit;
        return $this;
    }

    /**
     * OFFSET
     * 
     * @param int $offset 偏移量
     * @return self
     */
    public function offset(int $offset): self
    {
        $this->offset = $offset;
        return $this;
    }

    /**
     * 构建 WHERE 子句
     * 
     * @return array [sql, params]
     */
    private function buildWhere(): array
    {
        if (empty($this->where)) {
            return ['', []];
        }

        $sql = [];
        $params = [];

        foreach ($this->where as $index => $condition) {
            $type = $index === 0 ? '' : $condition['type'];
            $column = $condition['column'];
            $operator = $condition['operator'];
            $value = $condition['value'];

            if ($operator === 'IN') {
                $placeholders = implode(',', array_fill(0, count($value), '?'));
                $sql[] = "$type $column IN ($placeholders)";
                $params = array_merge($params, $value);
            } elseif ($operator === 'IS NULL') {
                $sql[] = "$type $column IS NULL";
            } else {
                $sql[] = "$type $column $operator ?";
                $params[] = $value;
            }
        }

        return ['WHERE ' . implode(' ', $sql), $params];
    }

    /**
     * 构建 SQL 语句
     * 
     * @return array [sql, params]
     */
    private function buildSql(): array
    {
        if (!$this->table) {
            throw new Exception('表名未设置');
        }

        $sql = "SELECT {$this->select} FROM {$this->table}";

        // JOIN
        foreach ($this->joins as $join) {
            $sql .= " {$join['type']} JOIN {$join['table']} ON {$join['first']} {$join['operator']} {$join['second']}";
        }

        // WHERE
        list($whereSql, $whereParams) = $this->buildWhere();
        $sql .= ' ' . $whereSql;
        $params = $whereParams;

        // GROUP BY
        if (!empty($this->groupBy)) {
            $sql .= ' GROUP BY ' . implode(', ', $this->groupBy);
        }

        // ORDER BY
        if (!empty($this->orderBy)) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orderBy);
        }

        // LIMIT
        if ($this->limit !== null) {
            $sql .= " LIMIT {$this->limit}";
        }

        // OFFSET
        if ($this->offset !== null) {
            $sql .= " OFFSET {$this->offset}";
        }

        return [$sql, $params];
    }

    /**
     * 重置查询构建器
     */
    private function reset()
    {
        $this->table = null;
        $this->select = '*';
        $this->where = [];
        $this->whereParams = [];
        $this->orderBy = [];
        $this->groupBy = [];
        $this->having = [];
        $this->limit = null;
        $this->offset = null;
        $this->joins = [];
    }

    /**
     * 执行查询并返回所有结果
     * 
     * @return array
     */
    public function get(): array
    {
        list($sql, $params) = $this->buildSql();

        $startTime = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $results = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $time = microtime(true) - $startTime;

        $this->logQuery($sql, $params, $time);

        $this->reset();
        return $results;
    }

    /**
     * 执行查询并返回第一条结果
     * 
     * @return array|null
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * 执行查询并返回单个值
     * 
     * @return mixed
     */
    public function value()
    {
        $result = $this->first();
        return $result ? reset($result) : null;
    }

    /**
     * 统计数量
     * 
     * @return int
     */
    public function count(): int
    {
        $originalSelect = $this->select;
        $this->select = 'COUNT(*) as count';
        $result = $this->value();
        $this->select = $originalSelect;
        return (int)$result;
    }

    /**
     * 插入数据
     * 
     * @param array $data 数据数组
     * @return int 插入的 ID
     */
    public function insert(array $data): int
    {
        if (!$this->table) {
            throw new Exception('表名未设置');
        }

        $columns = array_keys($data);
        $placeholders = implode(',', array_fill(0, count($columns), '?'));
        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES ($placeholders)";
        $params = array_values($data);

        $startTime = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $time = microtime(true) - $startTime;

        $this->logQuery($sql, $params, $time);

        return (int)$this->pdo->lastInsertId();
    }

    /**
     * 批量插入
     * 
     * @param array $dataArray 数据数组
     * @return int 影响的行数
     */
    public function insertBatch(array $dataArray): int
    {
        if (empty($dataArray) || !$this->table) {
            return 0;
        }

        $columns = array_keys($dataArray[0]);
        $values = [];
        $params = [];

        foreach ($dataArray as $data) {
            $placeholders = implode(',', array_fill(0, count($columns), '?'));
            $values[] = "($placeholders)";
            $params = array_merge($params, array_values($data));
        }

        $sql = "INSERT INTO {$this->table} (" . implode(', ', $columns) . ") VALUES " . implode(', ', $values);

        $startTime = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $time = microtime(true) - $startTime;

        $this->logQuery($sql, $params, $time);

        return $stmt->rowCount();
    }

    /**
     * 更新数据
     * 
     * @param array $data 数据数组
     * @return int 影响的行数
     */
    public function update(array $data): int
    {
        if (!$this->table) {
            throw new Exception('表名未设置');
        }

        list($whereSql, $whereParams) = $this->buildWhere();

        if (empty($whereSql)) {
            throw new Exception('UPDATE 操作必须包含 WHERE 条件');
        }

        $set = [];
        $params = [];
        foreach ($data as $column => $value) {
            $set[] = "$column = ?";
            $params[] = $value;
        }

        $sql = "UPDATE {$this->table} SET " . implode(', ', $set) . ' ' . $whereSql;
        $params = array_merge($params, $whereParams);

        $startTime = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $time = microtime(true) - $startTime;

        $this->logQuery($sql, $params, $time);

        $this->reset();
        return $stmt->rowCount();
    }

    /**
     * 删除数据
     * 
     * @return int 影响的行数
     */
    public function delete(): int
    {
        if (!$this->table) {
            throw new Exception('表名未设置');
        }

        list($whereSql, $params) = $this->buildWhere();

        if (empty($whereSql)) {
            throw new Exception('DELETE 操作必须包含 WHERE 条件');
        }

        $sql = "DELETE FROM {$this->table} $whereSql";

        $startTime = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $time = microtime(true) - $startTime;

        $this->logQuery($sql, $params, $time);

        $this->reset();
        return $stmt->rowCount();
    }

    /**
     * 执行原生 SQL
     * 
     * @param string $sql SQL 语句
     * @param array $params 参数
     * @return PDOStatement
     */
    public function raw(string $sql, array $params = []): PDOStatement
    {
        $startTime = microtime(true);
        $stmt = $this->pdo->prepare($sql);
        $stmt->execute($params);
        $time = microtime(true) - $startTime;

        $this->logQuery($sql, $params, $time);

        return $stmt;
    }

    /**
     * 开始事务
     */
    public function beginTransaction(): bool
    {
        return $this->pdo->beginTransaction();
    }

    /**
     * 提交事务
     */
    public function commit(): bool
    {
        return $this->pdo->commit();
    }

    /**
     * 回滚事务
     */
    public function rollBack(): bool
    {
        return $this->pdo->rollBack();
    }
}

