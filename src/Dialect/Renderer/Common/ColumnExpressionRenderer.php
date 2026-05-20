<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\ColumnExpression;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders a {@see ColumnExpression} (column reference with optional alias).
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class ColumnExpressionRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(ColumnExpression $component): string {
        $sql = $this->dialect->quoteIdentifier($component->name);

        if ($component->alias !== null) {
            $sql .= ' AS ' . $this->dialect->quoteIdentifier($component->alias);
        }

        return $sql;
    }
}
