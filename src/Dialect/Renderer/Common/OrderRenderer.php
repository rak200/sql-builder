<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\Order;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders an {@see Order} entry (`expr ASC|DESC [NULLS FIRST|LAST]`).
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class OrderRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Order $component): string {
        $sql = sprintf(
            '%s %s',
            $this->dialect->renderExpression($component->expression),
            $component->direction->value
        );

        if ($component->nullsPlacement !== null) {
            $sql .= ' ' . $component->nullsPlacement->value;
        }

        return $sql;
    }
}
