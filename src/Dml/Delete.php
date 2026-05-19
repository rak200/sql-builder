<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dml;

use InvalidArgumentException;
use Rak200\SqlBuilder\Common\Enum\NullsPlacement;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\Order;
use Rak200\SqlBuilder\Common\TableReference;
use Rak200\SqlBuilder\Utils\StringUtils;

/**
 * SQL DELETE statement builder.
 *
 * Supports the SQL-standard `DELETE FROM ... WHERE ...` plus the common
 * dialect extensions:
 * - **USING** (PostgreSQL) for multi-table DELETE
 * - **ORDER BY / LIMIT** (MySQL) for bounded DELETE
 * - **RETURNING** (PostgreSQL, MariaDB, SQLite) to read deleted rows
 *
 * Usage example:
 * ```php
 * Delete::create()
 *     ->from('users', 'u')
 *     ->using('audit', 'a')
 *     ->where(Expression::binary('u.id', BinaryOperator::Equal, Expression::ref('a.user_id')))
 *     ->orderBy('u.id')
 *     ->limit(100)
 *     ->returning('u.id', 'u.email');
 * ```
 *
 * @package Rak200\SqlBuilder\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Delete implements ExpressionInterface {

    /** @var TableReference|null $table Target table reference */
    private ?TableReference $table = null;

    /** @var TableReference[] $using Additional tables referenced via USING */
    private array $using = [];

    /** @var ExpressionInterface|null $where Optional WHERE condition */
    private ?ExpressionInterface $where = null;

    /** @var Order[] $orderBy ORDER BY entries */
    private array $orderBy = [];

    /** @var int|null $limit Row limit */
    private ?int $limit = null;

    /** @var ExpressionInterface[] $returning RETURNING expressions */
    private array $returning = [];

    /**
     * Create a new DELETE statement builder.
     *
     * @return self
     */
    public static function create(): self {
        return new self();
    }

    /**
     * Set the target table.
     *
     * @param string $table Table name.
     * @param string|null $alias Optional alias.
     * @return static
     */
    public function from(string $table, ?string $alias = null): static {
        $this->table = new TableReference($table, $alias);
        return $this;
    }

    /**
     * Add a table to the USING list for multi-table DELETE. Call multiple times for several tables.
     *
     * @param string $table Table name.
     * @param string|null $alias Optional alias.
     * @return static
     */
    public function using(string $table, ?string $alias = null): static {
        $this->using[] = new TableReference($table, $alias);
        return $this;
    }

    /**
     * Set or append an AND condition to the WHERE clause.
     *
     * @param ExpressionInterface $condition Condition expression.
     * @return static
     */
    public function where(ExpressionInterface $condition): static {
        $this->where = $this->where === null ? $condition : Expression::and($this->where, $condition);
        return $this;
    }

    /**
     * Alias for {@see where()} — appends an AND condition to the WHERE clause.
     *
     * @param ExpressionInterface $condition Condition expression.
     * @return static
     */
    public function andWhere(ExpressionInterface $condition): static {
        return $this->where($condition);
    }

    /**
     * Append an OR condition to the WHERE clause.
     *
     * @param ExpressionInterface $condition Condition expression.
     * @return static
     */
    public function orWhere(ExpressionInterface $condition): static {
        $this->where = $this->where === null ? $condition : Expression::or($this->where, $condition);
        return $this;
    }

    /**
     * Add an ORDER BY entry.
     *
     * @param ExpressionInterface|string $expression Column name or expression to sort by.
     * @param SortDirection $direction Sort direction; defaults to ASC.
     * @param NullsPlacement|null $nulls Optional NULL placement.
     * @return static
     */
    public function orderBy(
        ExpressionInterface|string $expression,
        SortDirection $direction = SortDirection::ASC,
        ?NullsPlacement $nulls = null
    ): static {
        $this->orderBy[] = new Order($expression, $direction, $nulls);
        return $this;
    }

    /**
     * Set the maximum number of rows to delete.
     *
     * @param int $limit Non-negative row count.
     * @throws InvalidArgumentException If limit is negative.
     * @return static
     */
    public function limit(int $limit): static {
        if ($limit < 0) {
            throw new InvalidArgumentException('LIMIT must be zero or greater.');
        }
        $this->limit = $limit;
        return $this;
    }

    /**
     * Add expressions to the RETURNING clause. Strings are treated as column references.
     *
     * @param ExpressionInterface|string ...$expressions Columns or expressions to return.
     * @return static
     */
    public function returning(ExpressionInterface|string ...$expressions): static {
        foreach ($expressions as $expression) {
            $this->returning[] = $expression instanceof ExpressionInterface
                ? $expression
                : Expression::column($expression);
        }
        return $this;
    }

    /** {@inheritdoc}
     * @throws InvalidArgumentException When the target table is missing.
     */
    public function __toString(): string {
        if ($this->table === null) {
            throw new InvalidArgumentException('DELETE requires a target table; call from().');
        }

        $sql  = 'DELETE FROM ' . $this->table;
        $sql .= StringUtils::join($this->using,     ', ', ' USING ');
        $sql .= StringUtils::wrap((string) $this->where, ' WHERE ');
        $sql .= StringUtils::join($this->orderBy,   ', ', ' ORDER BY ');
        $sql .= StringUtils::wrap((string) $this->limit, ' LIMIT ');
        $sql .= StringUtils::join($this->returning, ', ', ' RETURNING ');

        return $sql;
    }
}
