<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\SubqueryExpression;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders a {@see SubqueryExpression} as `(SELECT ...)` with optional alias.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class SubqueryExpressionRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(SubqueryExpression $component): string {
        $sql = '(' . $this->dialect->renderSelect($component->query) . ')';

        if ($component->alias !== null) {
            $sql .= ' AS ' . $this->dialect->quoteIdentifier($component->alias);
        }

        return $sql;
    }
}
