<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use Rak200\SqlBuilder\Common\Enum\GroupingMode;
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\Reference\Column as ColumnRef;

/**
 * `GROUP BY` extension expression — `ROLLUP (...)`, `CUBE (...)`,
 * or `GROUPING SETS ((...), (...))`.
 *
 * Items are normalised on construction:
 * - An `ExpressionInterface` is kept as-is.
 * - A string becomes a {@see ColumnRef}.
 * - An `array` becomes a sub-tuple — rendered as `(col1, col2, ...)`.
 *   For `GROUPING SETS`, empty arrays are valid and render as `()`
 *   (the SQL grand-total grouping).
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Grouping extends Expr {

    /**
     * @var array<int, ExpressionInterface|array<int, ExpressionInterface>> Normalised items.
     *      Arrays are sub-tuples (used by GROUPING SETS); scalar items are bare expressions.
     */
    public readonly array $items;

    /**
     * @param GroupingMode $mode The grouping extension keyword.
     * @param mixed ...$items Items to group by; strings become column refs,
     *                        arrays become sub-tuples for GROUPING SETS.
     */
    public function __construct(public readonly GroupingMode $mode, mixed ...$items) {
        $this->items = array_map(
            static function (mixed $item): ExpressionInterface|array {
                if (is_array($item)) {
                    return array_map(
                        static fn($element): ExpressionInterface => $element instanceof ExpressionInterface
                            ? $element
                            : new ColumnRef((string) $element),
                        $item
                    );
                }
                if ($item instanceof ExpressionInterface) {
                    return $item;
                }
                return new ColumnRef((string) $item);
            },
            $items
        );
    }
}
