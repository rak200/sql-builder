<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Common\Enum\NullsPlacement;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * Window specification used by {@see WindowExpression} for the `OVER (...)`
 * clause of window functions.
 *
 * Holds the `PARTITION BY` columns, the `ORDER BY` entries, and an optional
 * frame clause. The frame is stored as a raw SQL fragment to keep the API
 * surface small while still permitting any standards-compliant frame
 * (`ROWS`, `RANGE`, `GROUPS`, with `BETWEEN ... AND ...`, `UNBOUNDED
 * PRECEDING`, `n PRECEDING`, `CURRENT ROW`, …). Convenience setters
 * {@see rows()}, {@see range()}, {@see groups()} prepend the keyword.
 *
 * Usage:
 * ```php
 * $win = Window::create()
 *     ->partitionBy('category_id')
 *     ->orderBy('created_at')
 *     ->rows('BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW');
 *
 * Expression::over(Expression::sum('amount'), $win);
 * // SUM(`amount`) AS `SUM` OVER (PARTITION BY `category_id` ORDER BY `created_at` ASC ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)
 * ```
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Window {

    /** @var ExpressionInterface[] PARTITION BY expressions. */
    public private(set) array $partitionBy = [];

    /** @var Order[] ORDER BY entries. */
    public private(set) array $orderBy = [];

    /** @var string|null Raw frame clause (e.g. `ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW`). */
    public private(set) ?string $frame = null;

    public static function create(): self {
        return new self();
    }

    /**
     * Append PARTITION BY expressions. Strings become {@see ColumnReference}.
     */
    public function partitionBy(mixed ...$expressions): static {
        foreach ($expressions as $expression) {
            $this->partitionBy[] = $expression instanceof ExpressionInterface
                ? $expression
                : new ColumnReference((string) $expression);
        }
        return $this;
    }

    /**
     * Append an ORDER BY entry to this window.
     */
    public function orderBy(
        mixed $expression,
        SortDirection $direction = SortDirection::ASC,
        ?NullsPlacement $nulls = null
    ): static {
        $this->orderBy[] = new Order($expression, $direction, $nulls);
        return $this;
    }

    /**
     * Set the frame clause verbatim (advanced).
     */
    public function frame(string $frame): static {
        $this->frame = $frame;
        return $this;
    }

    /**
     * `ROWS <spec>` shorthand. Example: `->rows('BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW')`.
     */
    public function rows(string $spec): static {
        $this->frame = 'ROWS ' . $spec;
        return $this;
    }

    /**
     * `RANGE <spec>` shorthand.
     */
    public function range(string $spec): static {
        $this->frame = 'RANGE ' . $spec;
        return $this;
    }

    /**
     * `GROUPS <spec>` shorthand (PostgreSQL 11+, etc.).
     */
    public function groups(string $spec): static {
        $this->frame = 'GROUPS ' . $spec;
        return $this;
    }

    public function __toString(): string {
        return Dialect::default()->renderWindow($this);
    }

    public function toSql(Dialect $dialect): string {
        return $dialect->renderWindow($this);
    }
}
