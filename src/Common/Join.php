<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use InvalidArgumentException;
use Rak200\SqlBuilder\Common\Enum\JoinType;
use Rak200\SqlBuilder\Common\Expr as Expression;
use Rak200\SqlBuilder\Common\Reference\Table as TableReference;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dml\Select;

/**
 * SQL JOIN clause builder supporting INNER, LEFT, RIGHT, FULL, CROSS and NATURAL variants.
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Join implements ExpressionInterface {

    /** @var TableReference The joined table or subquery. */
    public readonly TableReference $table;

    /** @var bool Whether this is a NATURAL JOIN. */
    public private(set) bool $natural;

    /** @var ExpressionInterface|null Optional ON condition. */
    public private(set) ?ExpressionInterface $on;

    /** @var array<int, ExpressionInterface>|null USING column list. */
    public private(set) ?array $using;

    /**
     * @param JoinType $type JOIN type.
     * @param string|Select $table Table name or SELECT subquery to join.
     * @param string|null $alias Optional alias for the joined table.
     * @param ExpressionInterface|null $on Optional ON condition.
     * @param bool $natural Whether this is a NATURAL JOIN.
     * @param array<string>|null $using Column list for USING clause.
     */
    public function __construct(
        public readonly JoinType $type,
        string|Select $table,
        ?string $alias = null,
        ?ExpressionInterface $on = null,
        bool $natural = false,
        ?array $using = null
    ) {
        $this->table   = new TableReference($table, $alias);
        $this->on      = $on;
        $this->natural = $natural;
        $this->using   = $using;
    }

    /**
     * Set the ON condition for the join.
     */
    public function on(ExpressionInterface $condition): static {
        $this->on = $condition;
        return $this;
    }

    /**
     * Mark this join as a NATURAL JOIN.
     */
    public function natural(): static {
        $this->natural = true;
        return $this;
    }

    /**
     * Set the USING column list for the join.
     */
    public function using(mixed ...$columns): static {
        $this->using = array_map(
            static fn($column): ExpressionInterface => $column instanceof ExpressionInterface
                ? $column
                : Expression::identifier((string) $column),
            $columns
        );
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderJoin($this);
    }

    /**
     * Render this join with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderJoin($this);
    }

    /**
     * Validate that the join configuration is consistent.
     *
     * Called by renderers before producing SQL.
     *
     * @throws InvalidArgumentException On conflicting join options.
     */
    public function validate(): void {
        if ($this->natural && $this->on !== null) {
            throw new InvalidArgumentException('NATURAL JOIN cannot have an ON condition.');
        }

        if ($this->natural && $this->using !== null && count($this->using) > 0) {
            throw new InvalidArgumentException('NATURAL JOIN cannot have a USING clause.');
        }

        if ($this->using !== null && $this->on !== null) {
            throw new InvalidArgumentException('JOIN cannot have both ON and USING.');
        }

        if ($this->type === JoinType::CROSS && $this->on !== null) {
            throw new InvalidArgumentException('CROSS JOIN cannot have an ON condition.');
        }

        if ($this->type === JoinType::CROSS && $this->using !== null && count($this->using) > 0) {
            throw new InvalidArgumentException('CROSS JOIN cannot use USING.');
        }

        if ($this->using !== null && count($this->using) === 0) {
            throw new InvalidArgumentException('USING must have at least one column.');
        }
    }
}
