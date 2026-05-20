<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\BinaryExpression;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders a {@see BinaryExpression} as `(left op right)` with optional alias.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class BinaryExpressionRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(BinaryExpression $component): string {
        $sql = sprintf(
            '(%s %s %s)',
            $this->dialect->renderExpression($component->left),
            $component->operator->value,
            $this->dialect->renderExpression($component->right)
        );

        if ($component->alias !== null) {
            $sql .= ' AS ' . $this->dialect->quoteIdentifier($component->alias);
        }

        return $sql;
    }
}
