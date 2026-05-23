<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\Expression\Grouping;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders a {@see Grouping} expression — `ROLLUP (a, b)`, `CUBE (a, b)`,
 * or `GROUPING SETS ((a, b), (c), ())`.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class GroupingExpressionRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Grouping $component): string {
        $parts = array_map(
            fn($item): string => $this->renderItem($item),
            $component->items
        );

        return sprintf('%s (%s)', $component->mode->value, implode(', ', $parts));
    }

    /** @param \Rak200\SqlBuilder\Common\ExpressionInterface|array<int, \Rak200\SqlBuilder\Common\ExpressionInterface> $item */
    private function renderItem(mixed $item): string {
        if (is_array($item)) {
            $rendered = array_map(
                fn($element) => $this->dialect->renderExpression($element),
                $item
            );
            return '(' . implode(', ', $rendered) . ')';
        }
        return $this->dialect->renderExpression($item);
    }
}
