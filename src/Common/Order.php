<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Common\Enum\NullsPlacement;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * SQL ORDER BY entry with optional direction and NULL placement.
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Order {

    /** @var ExpressionInterface The column or expression to order by. */
    public readonly ExpressionInterface $expression;

    /**
     * @param ExpressionInterface|string $expression Column name or expression to sort by.
     * @param SortDirection $direction Sort direction; defaults to ASC.
     * @param NullsPlacement|null $nullsPlacement Optional NULL placement.
     */
    public function __construct(
        ExpressionInterface|string $expression,
        public readonly SortDirection $direction = SortDirection::ASC,
        public private(set) ?NullsPlacement $nullsPlacement = null
    ) {
        $this->expression = $expression instanceof ExpressionInterface
            ? $expression
            : Expression::ref($expression);
    }

    /**
     * Place NULL values before non-NULL values in the sort order.
     *
     * @return static
     */
    public function nullsFirst(): static {
        $this->nullsPlacement = NullsPlacement::FIRST;
        return $this;
    }

    /**
     * Place NULL values after non-NULL values in the sort order.
     *
     * @return static
     */
    public function nullsLast(): static {
        $this->nullsPlacement = NullsPlacement::LAST;
        return $this;
    }

    /**
     * Return the SQL representation of this ORDER BY entry.
     */
    public function __toString(): string {
        return Dialect::default()->renderOrder($this);
    }

    /**
     * Render this entry with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderOrder($this);
    }
}
