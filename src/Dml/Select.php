<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dml;

use Rak200\SqlBuilder\Common\Enum\JoinType;
use Rak200\SqlBuilder\Common\Enum\NullsPlacement;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\Join;
use Rak200\SqlBuilder\Common\Order;
use Rak200\SqlBuilder\Common\TableReference;
use Rak200\Collections\Collection;
use Rak200\SqlBuilder\Utils\StringUtils;
use InvalidArgumentException;

/**
 * SQL SELECT statement builder.
 *
 * Builds SELECT queries using a fluent interface with support for:
 * - DISTINCT modifier
 * - Multiple columns and expressions
 * - FROM clause with table references and subqueries
 * - Multiple JOIN types (INNER, LEFT, RIGHT, FULL, CROSS, NATURAL, USING)
 * - WHERE conditions with AND/OR logic
 * - GROUP BY and HAVING clauses
 * - ORDER BY with direction and NULL placement
 * - LIMIT and OFFSET pagination
 *
 * @package Rak200\SqlBuilder\Dml
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
final class Select implements ExpressionInterface {
    /** @var bool $distinct Whether to apply the DISTINCT modifier */
    private bool $distinct = false;
    /** @var Collection<ExpressionInterface> $columns Selected columns or expressions */
    private Collection $columns;
    /** @var TableReference|null $from The FROM table or subquery */
    private ?TableReference $from = null;
    /** @var Collection<Join> $joins Registered JOIN clauses */
    private Collection $joins;
    /** @var ExpressionInterface|null $where The WHERE condition */
    private ?ExpressionInterface $where = null;
    /** @var Collection<ExpressionInterface> $groupBy GROUP BY expressions */
    private Collection $groupBy;
    /** @var ExpressionInterface|null $having The HAVING condition */
    private ?ExpressionInterface $having = null;
    /** @var Collection<Order> $orderBy ORDER BY entries */
    private Collection $orderBy;
    /** @var int|null $limit Row limit */
    private ?int $limit = null;
    /** @var int|null $offset Row offset */
    private ?int $offset = null;

    public function __construct() {
        $this->columns = new Collection(ExpressionInterface::class);
        $this->joins   = new Collection(Join::class);
        $this->groupBy = new Collection(ExpressionInterface::class);
        $this->orderBy = new Collection(Order::class);
    }

    /**
     * Create a new SELECT query builder.
     *
     * @return self
     */
    public static function create(): self {
        return new self();
    }

    /**
     * Add the DISTINCT modifier to the SELECT clause.
     *
     * @return static
     */
    public function distinct(): static {
        $this->distinct = true;
        return $this;
    }

    /**
     * Add columns or expressions to the SELECT clause.
     *
     * @param mixed ...$expressions Column names, expressions, or ExpressionInterface objects.
     * @return static
     */
    public function select(mixed ...$expressions): static {
        foreach ($expressions as $expression) {
            $this->columns[] = $expression instanceof ExpressionInterface ? $expression : Expression::column((string) $expression);
        }

        return $this;
    }

    /**
     * Set the FROM clause with a table or subquery.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param string|null $alias Optional alias for the table/subquery.
     * @return static
     */
    public function from(string|Select $table, ?string $alias = null): static {
        $this->from = new TableReference($table, $alias);
        return $this;
    }

    /**
     * Add an INNER JOIN clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param string|null $alias Optional alias for the joined table.
     * @param ExpressionInterface|null $on Optional ON condition.
     * @return static
     */
    public function join(string|Select $table, ?string $alias = null, ?ExpressionInterface $on = null): static {
        $this->joins[] = new Join(JoinType::INNER, $table, $alias, $on);
        return $this;
    }

    /**
     * Add a LEFT JOIN clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param string|null $alias Optional alias for the joined table.
     * @param ExpressionInterface|null $on Optional ON condition.
     * @return static
     */
    public function leftJoin(string|Select $table, ?string $alias = null, ?ExpressionInterface $on = null): static {
        $this->joins[] = new Join(JoinType::LEFT, $table, $alias, $on);
        return $this;
    }

    /**
     * Add a RIGHT JOIN clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param string|null $alias Optional alias for the joined table.
     * @param ExpressionInterface|null $on Optional ON condition.
     * @return static
     */
    public function rightJoin(string|Select $table, ?string $alias = null, ?ExpressionInterface $on = null): static {
        $this->joins[] = new Join(JoinType::RIGHT, $table, $alias, $on);
        return $this;
    }

    /**
     * Add a FULL OUTER JOIN clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param string|null $alias Optional alias for the joined table.
     * @param ExpressionInterface|null $on Optional ON condition.
     * @return static
     */
    public function fullJoin(string|Select $table, ?string $alias = null, ?ExpressionInterface $on = null): static {
        $this->joins[] = new Join(JoinType::FULL, $table, $alias, $on);
        return $this;
    }

    /**
     * Add a CROSS JOIN clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param string|null $alias Optional alias for the joined table.
     * @return static
     */
    public function crossJoin(string|Select $table, ?string $alias = null): static {
        $this->joins[] = new Join(JoinType::CROSS, $table, $alias);
        return $this;
    }

    /**
     * Add a NATURAL INNER JOIN clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param string|null $alias Optional alias for the joined table.
     * @return static
     */
    public function naturalJoin(string|Select $table, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::INNER, $table, $alias))->natural();
        return $this;
    }

    /**
     * Add a NATURAL LEFT JOIN clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param string|null $alias Optional alias for the joined table.
     * @return static
     */
    public function naturalLeftJoin(string|Select $table, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::LEFT, $table, $alias))->natural();
        return $this;
    }

    /**
     * Add a NATURAL RIGHT JOIN clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param string|null $alias Optional alias for the joined table.
     * @return static
     */
    public function naturalRightJoin(string|Select $table, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::RIGHT, $table, $alias))->natural();
        return $this;
    }

    /**
     * Add a NATURAL FULL JOIN clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param string|null $alias Optional alias for the joined table.
     * @return static
     */
    public function naturalFullJoin(string|Select $table, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::FULL, $table, $alias))->natural();
        return $this;
    }

    /**
     * Add an INNER JOIN with a USING clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param array<mixed> $columns Columns for the USING clause.
     * @param string|null $alias Optional alias for the joined table.
     * @return static
     */
    public function joinUsing(string|Select $table, array $columns, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::INNER, $table, $alias))->using(...$columns);
        return $this;
    }

    /**
     * Add a LEFT JOIN with a USING clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param array<mixed> $columns Columns for the USING clause.
     * @param string|null $alias Optional alias for the joined table.
     * @return static
     */
    public function leftJoinUsing(string|Select $table, array $columns, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::LEFT, $table, $alias))->using(...$columns);
        return $this;
    }

    /**
     * Add a RIGHT JOIN with a USING clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param array<mixed> $columns Columns for the USING clause.
     * @param string|null $alias Optional alias for the joined table.
     * @return static
     */
    public function rightJoinUsing(string|Select $table, array $columns, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::RIGHT, $table, $alias))->using(...$columns);
        return $this;
    }

    /**
     * Add a FULL JOIN with a USING clause.
     *
     * @param string|Select $table Table name or SELECT subquery.
     * @param array<mixed> $columns Columns for the USING clause.
     * @param string|null $alias Optional alias for the joined table.
     * @return static
     */
    public function fullJoinUsing(string|Select $table, array $columns, ?string $alias = null): static {
        $this->joins[] = (new Join(JoinType::FULL, $table, $alias))->using(...$columns);
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
     * Add columns or expressions to the GROUP BY clause.
     *
     * @param mixed ...$expressions Column names or expressions.
     * @return static
     */
    public function groupBy(mixed ...$expressions): static {
        foreach ($expressions as $expression) {
            $this->groupBy[] = $expression instanceof ExpressionInterface ? $expression : Expression::ref((string) $expression);
        }
        return $this;
    }

    /**
     * Set or append an AND condition to the HAVING clause.
     *
     * @param ExpressionInterface $condition Condition expression.
     * @return static
     */
    public function having(ExpressionInterface $condition): static {
        $this->having = $this->having === null ? $condition : Expression::and($this->having, $condition);
        return $this;
    }

    /**
     * Add an ORDER BY entry.
     *
     * @param mixed $expression Column name or expression to sort by.
     * @param SortDirection $direction Sort direction; defaults to ASC.
     * @param NullsPlacement|null $nulls Optional NULL placement.
     * @return static
     */
    public function orderBy(mixed $expression, SortDirection $direction = SortDirection::ASC, ?NullsPlacement $nulls = null): static {
        $this->orderBy[] = new Order($expression, $direction, $nulls);
        return $this;
    }

    /**
     * Set the maximum number of rows to return.
     *
     * @param int $limit Non-negative row count.
     * @throws \InvalidArgumentException If limit is negative.
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
     * Set the number of rows to skip before returning results.
     *
     * @param int $offset Non-negative row offset.
     * @throws \InvalidArgumentException If offset is negative.
     * @return static
     */
    public function offset(int $offset): static {
        if ($offset < 0) {
            throw new InvalidArgumentException('OFFSET must be zero or greater.');
        }
        $this->offset = $offset;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        $sql = $this->buildSelect();
        $sql .= $this->buildFrom();
        $sql .= $this->buildJoins();
        $sql .= $this->buildWhere();
        $sql .= $this->buildGroupBy();
        $sql .= $this->buildHaving();
        $sql .= $this->buildOrderBy();
        $sql .= $this->buildLimitOffset();
        return trim($sql);
    }

    /** Build the SELECT [DISTINCT] <columns> fragment. */
    private function buildSelect(): string {
        $distinct = $this->distinct ? ' DISTINCT' : '';
        $columns  = StringUtils::join($this->columns, ', ') ?: '*';
        return sprintf('SELECT%s %s', $distinct, $columns);
    }

    /** Build the FROM <table> fragment, or empty string if no FROM is set. */
    private function buildFrom(): string {
        return StringUtils::wrap((string) $this->from, ' FROM ');
    }

    /** Build all JOIN fragments joined by spaces, or empty string if none. */
    private function buildJoins(): string {
        return StringUtils::join($this->joins, ' ', ' ');
    }

    /** Build the WHERE <condition> fragment, or empty string if no condition is set. */
    private function buildWhere(): string {
        return StringUtils::wrap((string) $this->where, ' WHERE ');
    }

    /** Build the GROUP BY <expressions> fragment, or empty string if none. */
    private function buildGroupBy(): string {
        return StringUtils::join($this->groupBy, ', ', ' GROUP BY ');
    }

    /** Build the HAVING <condition> fragment, or empty string if no condition is set. */
    private function buildHaving(): string {
        return StringUtils::wrap((string) $this->having, ' HAVING ');
    }

    /** Build the ORDER BY <entries> fragment, or empty string if none. */
    private function buildOrderBy(): string {
        return StringUtils::join($this->orderBy, ', ', ' ORDER BY ');
    }

    /** Build the LIMIT and OFFSET fragments, or empty string if neither is set. */
    private function buildLimitOffset(): string {
        return StringUtils::wrap((string) $this->limit,  ' LIMIT ')
             . StringUtils::wrap((string) $this->offset, ' OFFSET ');
    }
}
