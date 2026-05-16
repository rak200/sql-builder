<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Common\Enum\NullsPlacement;
use Rak200\SqlBuilder\Common\Enum\SortDirection;

/**
 * SQL ORDER BY entry with optional direction and NULL placement.
 *
 * @package Rak200\SqlBuilder\Common
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
final class Order {

    /** @var ExpressionInterface $expression The column or expression to order by */
    private ExpressionInterface $expression;

    /**
     * @param ExpressionInterface|string $expression Column name or expression to sort by.
     * @param SortDirection $direction Sort direction; defaults to ASC.
     * @param NullsPlacement|null $nullsPlacement Optional NULL placement.
     */
    public function __construct(
        ExpressionInterface|string $expression,
        private SortDirection $direction = SortDirection::ASC,
        private ?NullsPlacement $nullsPlacement = null
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
     *
     * @return string
     */
    public function __toString(): string {
        $sql = "{$this->expression} {$this->direction->value}";

        if ($this->nullsPlacement !== null) {
            return "$sql {$this->nullsPlacement->value}";
        }

        return $sql;
    }
}
