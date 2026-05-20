<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\Window;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders a {@see Window} as `(PARTITION BY ... ORDER BY ... <frame>)`.
 *
 * Empty windows render as `()` — valid SQL meaning "the whole result set
 * partitioned trivially".
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class WindowRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Window $component): string {
        $parts = [];

        if ($component->partitionBy !== []) {
            $rendered = array_map(
                fn($expression) => $this->dialect->renderExpression($expression),
                $component->partitionBy
            );
            $parts[] = 'PARTITION BY ' . implode(', ', $rendered);
        }

        if ($component->orderBy !== []) {
            $rendered = array_map(
                fn($order) => $this->dialect->renderOrder($order),
                $component->orderBy
            );
            $parts[] = 'ORDER BY ' . implode(', ', $rendered);
        }

        if ($component->frame !== null) {
            $parts[] = $component->frame;
        }

        return '(' . implode(' ', $parts) . ')';
    }
}
