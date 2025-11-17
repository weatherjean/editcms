<?php

declare(strict_types=1);

namespace Edit\Core\Database;

/**
 * Lightweight SQL Query Builder
 *
 * Inspired by Eloquent and Doctrine but minimal and zero-dependency.
 * Focuses on security through parameterized queries and operator whitelisting.
 */
class QueryBuilder
{
    private Database $db;
    private string $table = '';
    private array $select = ['*'];
    private array $joins = [];
    private array $wheres = [];
    private array $bindings = [];
    private ?string $orderByColumn = null;
    private string $orderDirection = 'ASC';
    private ?int $limitValue = null;
    private ?int $offsetValue = null;
    private string $type = 'select'; // select, insert, update, delete

    // Whitelisted operators to prevent SQL injection
    private const ALLOWED_OPERATORS = [
        '=', '!=', '<>', '>', '>=', '<', '<=',
        'LIKE', 'NOT LIKE', 'IN', 'NOT IN',
        'IS NULL', 'IS NOT NULL'
    ];

    // Whitelisted join types
    private const ALLOWED_JOIN_TYPES = ['INNER', 'LEFT', 'RIGHT'];

    // Whitelisted order directions
    private const ALLOWED_ORDER_DIRECTIONS = ['ASC', 'DESC'];

    public function __construct(Database $db)
    {
        $this->db = $db;
    }

    /**
     * Set the table to query
     */
    public function table(string $table): self
    {
        $this->validateIdentifier($table);
        $this->table = $table;
        return $this;
    }

    /**
     * Set the columns to select
     */
    public function select(array|string $columns = ['*']): self
    {
        $this->type = 'select';
        $this->select = is_array($columns) ? $columns : [$columns];

        // Validate each column name
        foreach ($this->select as $column) {
            if ($column !== '*' && !str_contains($column, '(')) {
                // Only validate simple column names (not functions like COUNT(*))
                $this->validateIdentifier($column);
            }
        }

        return $this;
    }

    /**
     * Add a WHERE clause
     * Supports both: where('col', '=', 'val') and where('col', 'val')
     */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        // If only 2 args, assume operator is '='
        if ($value === null && func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->validateIdentifier($column);
        $this->validateOperator($operator);

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add an OR WHERE clause
     * Supports both: orWhere('col', '=', 'val') and orWhere('col', 'val')
     */
    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null && func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }

        $this->validateIdentifier($column);
        $this->validateOperator($operator);

        $this->wheres[] = [
            'type' => 'basic',
            'column' => $column,
            'operator' => $operator,
            'value' => $value,
            'boolean' => 'OR'
        ];

        return $this;
    }

    /**
     * Add a WHERE IN clause
     */
    public function whereIn(string $column, array $values): self
    {
        $this->validateIdentifier($column);

        $this->wheres[] = [
            'type' => 'in',
            'column' => $column,
            'values' => $values,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add a WHERE NULL clause
     */
    public function whereNull(string $column): self
    {
        $this->validateIdentifier($column);

        $this->wheres[] = [
            'type' => 'null',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add a WHERE NOT NULL clause
     */
    public function whereNotNull(string $column): self
    {
        $this->validateIdentifier($column);

        $this->wheres[] = [
            'type' => 'not_null',
            'column' => $column,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add a raw WHERE EXISTS clause with subquery
     */
    public function whereExists(string $sql, array $bindings = []): self
    {
        $this->wheres[] = [
            'type' => 'exists',
            'sql' => $sql,
            'bindings' => $bindings,
            'boolean' => 'AND'
        ];

        return $this;
    }

    /**
     * Add a JOIN clause
     */
    public function join(string $table, string $firstColumn, string $operator, string $secondColumn, string $type = 'INNER'): self
    {
        $type = strtoupper($type);

        if (!in_array($type, self::ALLOWED_JOIN_TYPES)) {
            throw new \InvalidArgumentException("Invalid join type: {$type}");
        }

        $this->validateIdentifier($table);
        $this->validateIdentifier($firstColumn);
        $this->validateIdentifier($secondColumn);
        $this->validateOperator($operator);

        $this->joins[] = [
            'type' => $type,
            'table' => $table,
            'first' => $firstColumn,
            'operator' => $operator,
            'second' => $secondColumn
        ];

        return $this;
    }

    /**
     * Add a LEFT JOIN clause
     */
    public function leftJoin(string $table, string $firstColumn, string $operator, string $secondColumn): self
    {
        return $this->join($table, $firstColumn, $operator, $secondColumn, 'LEFT');
    }

    /**
     * Add ORDER BY clause
     */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->validateIdentifier($column);

        $direction = strtoupper($direction);
        if (!in_array($direction, self::ALLOWED_ORDER_DIRECTIONS)) {
            throw new \InvalidArgumentException("Invalid order direction: {$direction}");
        }

        $this->orderByColumn = $column;
        $this->orderDirection = $direction;

        return $this;
    }

    /**
     * Add LIMIT clause
     */
    public function limit(int $limit): self
    {
        if ($limit < 0) {
            throw new \InvalidArgumentException("Limit must be >= 0");
        }

        $this->limitValue = $limit;
        return $this;
    }

    /**
     * Add OFFSET clause
     */
    public function offset(int $offset): self
    {
        if ($offset < 0) {
            throw new \InvalidArgumentException("Offset must be >= 0");
        }

        $this->offsetValue = $offset;
        return $this;
    }

    /**
     * Execute the query and return all results
     */
    public function get(): array
    {
        $sql = $this->toSql();
        return $this->db->query($sql, $this->bindings);
    }

    /**
     * Execute the query and return first result
     */
    public function first(): ?array
    {
        $this->limit(1);
        $results = $this->get();
        return $results[0] ?? null;
    }

    /**
     * Get the count of matching records
     */
    public function count(): int
    {
        $originalSelect = $this->select;
        $this->select = ['COUNT(*) as count'];

        $sql = $this->toSql();
        $result = $this->db->query($sql, $this->bindings);

        $this->select = $originalSelect;

        return (int) ($result[0]['count'] ?? 0);
    }

    /**
     * Insert a record
     */
    public function insert(array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException("Insert data cannot be empty");
        }

        $columns = array_keys($data);
        foreach ($columns as $column) {
            $this->validateIdentifier($column);
        }

        $placeholders = array_fill(0, count($columns), '?');
        $this->bindings = array_values($data);

        $sql = sprintf(
            "INSERT INTO %s (%s) VALUES (%s)",
            $this->escapeIdentifier($this->table),
            implode(', ', array_map([$this, 'escapeIdentifier'], $columns)),
            implode(', ', $placeholders)
        );

        $this->db->execute($sql, $this->bindings);
        return $this->db->lastInsertId();
    }

    /**
     * Update records
     */
    public function update(array $data): int
    {
        if (empty($data)) {
            throw new \InvalidArgumentException("Update data cannot be empty");
        }

        $columns = array_keys($data);
        foreach ($columns as $column) {
            $this->validateIdentifier($column);
        }

        $sets = [];
        $bindings = [];

        foreach ($data as $column => $value) {
            $sets[] = $this->escapeIdentifier($column) . ' = ?';
            $bindings[] = $value;
        }

        // Add WHERE bindings after SET bindings
        $this->buildWhereBindings();
        $bindings = array_merge($bindings, $this->bindings);

        $sql = sprintf(
            "UPDATE %s SET %s%s",
            $this->escapeIdentifier($this->table),
            implode(', ', $sets),
            $this->buildWhereClause()
        );

        return $this->db->execute($sql, $bindings);
    }

    /**
     * Delete records
     */
    public function delete(): int
    {
        $this->buildWhereBindings();

        $sql = sprintf(
            "DELETE FROM %s%s",
            $this->escapeIdentifier($this->table),
            $this->buildWhereClause()
        );

        return $this->db->execute($sql, $this->bindings);
    }

    /**
     * Build the SQL query
     */
    public function toSql(): string
    {
        if (empty($this->table)) {
            throw new \RuntimeException("No table specified for query");
        }

        $this->bindings = [];

        // Build SELECT clause
        $columns = implode(', ', array_map(function($col) {
            return $col === '*' || str_contains($col, '(') ? $col : $this->escapeIdentifier($col);
        }, $this->select));

        $sql = "SELECT {$columns} FROM " . $this->escapeIdentifier($this->table);

        // Build JOINs
        foreach ($this->joins as $join) {
            $sql .= sprintf(
                " %s JOIN %s ON %s %s %s",
                $join['type'],
                $this->escapeIdentifier($join['table']),
                $join['first'],
                $join['operator'],
                $join['second']
            );
        }

        // Build WHERE
        $sql .= $this->buildWhereClause();

        // Build ORDER BY
        if ($this->orderByColumn !== null) {
            $sql .= sprintf(
                " ORDER BY %s %s",
                $this->escapeIdentifier($this->orderByColumn),
                $this->orderDirection
            );
        }

        // Build LIMIT/OFFSET
        if ($this->limitValue !== null) {
            $sql .= " LIMIT ?";
            $this->bindings[] = $this->limitValue;

            if ($this->offsetValue !== null) {
                $sql .= " OFFSET ?";
                $this->bindings[] = $this->offsetValue;
            }
        }

        return $sql;
    }

    /**
     * Build WHERE clause
     */
    private function buildWhereClause(): string
    {
        if (empty($this->wheres)) {
            return '';
        }

        $sql = ' WHERE ';
        $clauses = [];

        foreach ($this->wheres as $index => $where) {
            $boolean = $index === 0 ? '' : " {$where['boolean']} ";

            switch ($where['type']) {
                case 'basic':
                    $clauses[] = $boolean . sprintf(
                        "%s %s ?",
                        $this->escapeIdentifier($where['column']),
                        $where['operator']
                    );
                    $this->bindings[] = $where['value'];
                    break;

                case 'in':
                    $placeholders = implode(', ', array_fill(0, count($where['values']), '?'));
                    $clauses[] = $boolean . sprintf(
                        "%s IN (%s)",
                        $this->escapeIdentifier($where['column']),
                        $placeholders
                    );
                    foreach ($where['values'] as $value) {
                        $this->bindings[] = $value;
                    }
                    break;

                case 'null':
                    $clauses[] = $boolean . $this->escapeIdentifier($where['column']) . " IS NULL";
                    break;

                case 'not_null':
                    $clauses[] = $boolean . $this->escapeIdentifier($where['column']) . " IS NOT NULL";
                    break;

                case 'exists':
                    $clauses[] = $boolean . "EXISTS (" . $where['sql'] . ")";
                    foreach ($where['bindings'] as $binding) {
                        $this->bindings[] = $binding;
                    }
                    break;
            }
        }

        return $sql . implode('', $clauses);
    }

    /**
     * Build WHERE bindings (for UPDATE/DELETE)
     */
    private function buildWhereBindings(): void
    {
        $this->bindings = [];

        foreach ($this->wheres as $where) {
            switch ($where['type']) {
                case 'basic':
                    $this->bindings[] = $where['value'];
                    break;

                case 'in':
                    foreach ($where['values'] as $value) {
                        $this->bindings[] = $value;
                    }
                    break;

                case 'exists':
                    foreach ($where['bindings'] as $binding) {
                        $this->bindings[] = $binding;
                    }
                    break;
            }
        }
    }

    /**
     * Validate identifier (table/column name)
     */
    private function validateIdentifier(string $identifier): void
    {
        // Allow qualified names (table.column)
        $parts = explode('.', $identifier);

        foreach ($parts as $part) {
            // Allow alphanumeric, underscore, hyphen
            if (!preg_match('/^[a-zA-Z0-9_-]+$/', $part)) {
                throw new \InvalidArgumentException(
                    "Invalid identifier format: {$identifier}. Must be alphanumeric with underscores/hyphens only."
                );
            }
        }
    }

    /**
     * Validate operator
     */
    private function validateOperator(string $operator): void
    {
        $operator = strtoupper($operator);

        if (!in_array($operator, self::ALLOWED_OPERATORS)) {
            throw new \InvalidArgumentException(
                "Invalid operator: {$operator}. Allowed: " . implode(', ', self::ALLOWED_OPERATORS)
            );
        }
    }

    /**
     * Escape identifier for SQL (SQLite uses double quotes)
     */
    private function escapeIdentifier(string $identifier): string
    {
        // Handle qualified names
        if (str_contains($identifier, '.')) {
            $parts = explode('.', $identifier);
            return implode('.', array_map(fn($p) => '"' . str_replace('"', '""', $p) . '"', $parts));
        }

        return '"' . str_replace('"', '""', $identifier) . '"';
    }
}
