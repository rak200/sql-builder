<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dml;

use InvalidArgumentException;
use Rak200\Collections\Vector;
use Rak200\SqlBuilder\Common\Enum\JoinType;
use Rak200\SqlBuilder\Common\Enum\NullsPlacement;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\Join;
use Rak200\SqlBuilder\Common\Order;
use Rak200\SqlBuilder\Common\TableReference;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Prepared\PreparedStatement;
// Cte lives in this namespace; explicit use omitted to avoid self-import.

/**
 * SQL SELECT statement builder.
 *
 * Builds SELECT queries using a fluent interface. Rendering is delegated to
 * a {@see Dialect}; the builder is a thin data carrier — state, validation
 * and factory methods only.
 *
 * @package Rak200\SqlBuilder\Dml
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
final class Select implements ExpressionInterface {

    /** @var Vector<Cte> Common Table Expressions declared via with()/withRecursive(). */
    public readonly Vector $ctes;

    /** @var bool Whether any CTE was added via withRecursive(); promotes the WITH clause to WITH RECURSIVE. */
    public private(set) bool $recursive = false;

    /** @var bool Whether to apply the DISTINCT modifier. */
    public private(set) bool $distinct = false;

    /** @var Vector<ExpressionInterface> Selected columns or expressions. */
    public readonly Vector $columns;

    /** @var TableReference|null The FROM table or subquery. */
    public private(set) ?TableReference $from = null;

    /** @var Vector<Join> Registered JOIN clauses. */
    public readonly Vector $joins;

    /** @var ExpressionInterface|null The WHERE condition. */
    public private(set) ?ExpressionInterface $where = null;

    /** @var Vector<ExpressionInterface> GROUP BY expressions. */
    public readonly Vector $groupBy;

    /** @var ExpressionInterface|null The HAVING condition. */
    public private(set) ?ExpressionInterface $having = null;

    /** @var Vector<Order> ORDER BY entries. */
    public readonly Vector $orderBy;

    /** @var int|null Row limit. */
    public private(set) ?int $limit = null;

    /** @var int|null Row offset. */
    public private(set) ?int $offset = null;

    public function __construct() {
        $this->ctes    = new Vector(Cte::class);
        $this->columns = new Vector(ExpressionInterface::class);
        $this->joins   = new Vector(Join::class);
        $this->groupBy = new Vector(ExpressionInterface::class);
        $this->orderBy = new Vector(Order::class);
    }

    public static function create(): self {
        return new self();
    }

    /**
     * Append a Common Table Expression (`WITH name [(cols)] AS (query)`).
     *
     * Call multiple times to declare several CTEs in a single `WITH` clause.
     * For recursive references use {@see withRecursive()} (the clause is
     * promoted to `WITH RECURSIVE` even if only one of the CTEs is recursive).
     *
     * @param string $name CTE name (referenceable like a table inside the body).
     * @param Select|Set $query CTE body.
     * @param array<int, string>|null $columns Optional explicit column-name list.
     */
    public function with(string $name, Select|Set $query, ?array $columns = null): static {
        $this->ctes[] = new Cte($name, $query, $columns);
        return $this;
    }

    /**
     * Append a recursive CTE — promotes the surrounding `WITH` clause to
     * `WITH RECURSIVE` and registers the entry.
     *
     * @param string $name CTE name.
     * @param Select|Set $query CTE body (typically a {@see Set} with a UNION
     *                           combining the base case and recursive step).
     * @param array<int, string>|null $columns Optional explicit column-name list.
     */
    public function withRecursive(string $name, Select|Set $query, ?array $columns = null): static {
        $this->recursive = true;
        $this->ctes[] = new Cte($name, $query, $columns);
        return $this;
    }

    public function distinct(): static {
        $this->distinct = true;
        return $this;
    }

    public function select(mixed ...$expressions): static {
        foreach ($expressions as $expression) {
            $this->columns[] = $expression instanceof ExpressionInterface
                ? $expression
                : Expression::column((string) $expression);
        }
        return $this;
    }

    public function from(string|Select $table, ?string $alias = null): static {
        $this->from = new TableReference($table, $alias);
        return $this;
    }

    public function join(string|Select $table, ?string $alias = null, ?ExpressionInterface $on = null): static {
        $this->joins[] = new Join(JoinType::INNER, $table, $alias, $on);
        return $this;
    }

    public function leftJoin(string|Select $table, ?string $alias = null, ?ExpressionInterface $on = null): static {
        $this->joins[] = new Join(JoinType::LEFT, $table, $alias, $on);
        return $this;
    }

    public function rightJoin(string|Select $table, ?string $alias = null, ?ExpressionInterface $on = null): static {
        $this->joins[] = new Join(JoinType::RIGHT, $table, $alias, $on);
        return $this;
    }

    public function fullJoin(string|Select $table, ?string $alias = null, ?ExpressionInterface $on = null): static {
        $this->joins[] = new Join(JoinType::FULL, $table, $alias, $on);
        return $this;
    }

    public function crossJoin(string|Select $table, ?string $alias = null): static {
        $this->joins[] = new Join(JoinType::CROSS, $table, $alias);
        return $this;
    }

    public function naturalJoin(string|Select $table, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::INNER, $table, $alias))->natural();
        return $this;
    }

    public function naturalLeftJoin(string|Select $table, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::LEFT, $table, $alias))->natural();
        return $this;
    }

    public function naturalRightJoin(string|Select $table, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::RIGHT, $table, $alias))->natural();
        return $this;
    }

    public function naturalFullJoin(string|Select $table, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::FULL, $table, $alias))->natural();
        return $this;
    }

    public function joinUsing(string|Select $table, array $columns, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::INNER, $table, $alias))->using(...$columns);
        return $this;
    }

    public function leftJoinUsing(string|Select $table, array $columns, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::LEFT, $table, $alias))->using(...$columns);
        return $this;
    }

    public function rightJoinUsing(string|Select $table, array $columns, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::RIGHT, $table, $alias))->using(...$columns);
        return $this;
    }

    public function fullJoinUsing(string|Select $table, array $columns, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::FULL, $table, $alias))->using(...$columns);
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

    public function groupBy(mixed ...$expressions): static {
        foreach ($expressions as $expression) {
            $this->groupBy[] = $expression instanceof ExpressionInterface
                ? $expression
                : Expression::ref((string) $expression);
        }
        return $this;
    }

    public function having(ExpressionInterface $condition): static {
        $this->having = $this->having === null ? $condition : Expression::and($this->having, $condition);
        return $this;
    }

    public function orderBy(mixed $expression, SortDirection $direction = SortDirection::ASC, ?NullsPlacement $nulls = null): static {
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

    public function offset(int $offset): static {
        if ($offset < 0) {
            throw new InvalidArgumentException('OFFSET must be zero or greater.');
        }
        $this->offset = $offset;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderSelect($this);
    }

    /**
     * Render this statement with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderSelect($this);
    }

    /**
     * Render this statement in bind mode for the given dialect.
     *
     * @param Dialect $dialect The dialect to render with.
     * @return PreparedStatement
     */
    public function prepare(Dialect $dialect): PreparedStatement {
        $binder = $dialect->newBinder();
        $sql    = $dialect->withBinder($binder)->renderSelect($this);
        return new PreparedStatement($sql, $binder->values());
    }
}
