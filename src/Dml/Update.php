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
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Prepared\PreparedStatement;

/**
 * SQL UPDATE statement builder.
 *
 * Supports the SQL-standard `UPDATE <table> SET ... [WHERE ...]` plus the
 * common dialect extensions:
 * - **FROM** (PostgreSQL) for multi-table UPDATE
 * - **ORDER BY / LIMIT** (MySQL) for bounded UPDATE
 * - **RETURNING** (PostgreSQL, MariaDB, SQLite) to read updated rows
 *
 * @package Rak200\SqlBuilder\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Update implements ExpressionInterface {

    /** @var TableReference|null Target table reference. */
    public private(set) ?TableReference $table = null;

    /** @var array<string, ExpressionInterface> Column-to-value assignments in insertion order. */
    public private(set) array $assignments = [];

    /** @var TableReference[] Additional tables referenced via FROM. */
    public private(set) array $from = [];

    /** @var ExpressionInterface|null Optional WHERE condition. */
    public private(set) ?ExpressionInterface $where = null;

    /** @var Order[] ORDER BY entries. */
    public private(set) array $orderBy = [];

    /** @var int|null Row limit. */
    public private(set) ?int $limit = null;

    /** @var ExpressionInterface[] RETURNING expressions. */
    public private(set) array $returning = [];

    public static function create(): self {
        return new self();
    }

    public function table(string $table, ?string $alias = null): static {
        $this->table = new TableReference($table, $alias);
        return $this;
    }

    public function set(string $column, mixed $value): static {
        $this->assignments[$column] = $value instanceof ExpressionInterface
            ? $value
            : Expression::value($value);
        return $this;
    }

    public function from(string $table, ?string $alias = null): static {
        $this->from[] = new TableReference($table, $alias);
        return $this;
    }

    public function where(ExpressionInterface $condition): static {
        $this->where = $this->where === null ? $condition : Expression::and($this->where, $condition);
        return $this;
    }

    public function andWhere(ExpressionInterface $condition): static {
        return $this->where($condition);
    }

    public function orWhere(ExpressionInterface $condition): static {
        $this->where = $this->where === null ? $condition : Expression::or($this->where, $condition);
        return $this;
    }

    public function orderBy(
        ExpressionInterface|string $expression,
        SortDirection $direction = SortDirection::ASC,
        ?NullsPlacement $nulls = null
    ): static {
        $this->orderBy[] = new Order($expression, $direction, $nulls);
        return $this;
    }

    public function limit(int $limit): static {
        if ($limit < 0) {
            throw new InvalidArgumentException('LIMIT must be zero or greater.');
        }
        $this->limit = $limit;
        return $this;
    }

    public function returning(ExpressionInterface|string ...$expressions): static {
        foreach ($expressions as $expression) {
            $this->returning[] = $expression instanceof ExpressionInterface
                ? $expression
                : Expression::column($expression);
        }
        return $this;
    }

    /** {@inheritdoc}
     * @throws InvalidArgumentException When the target table or assignments are missing.
     */
    public function __toString(): string {
        return Dialect::default()->renderUpdate($this);
    }

    /**
     * Render this statement with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderUpdate($this);
    }

    /**
     * Render this statement in bind mode for the given dialect.
     *
     * @param Dialect $dialect The dialect to render with.
     * @return PreparedStatement
     */
    public function prepare(Dialect $dialect): PreparedStatement {
        $binder = $dialect->newBinder();
        $sql    = $dialect->withBinder($binder)->renderUpdate($this);
        return new PreparedStatement($sql, $binder->values());
    }
}
