<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\RawExpression;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders a {@see RawExpression} verbatim (no quoting), with optional alias.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class RawExpressionRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(RawExpression $component): string {
        $sql = $component->sql;

        if ($component->alias !== null) {
            $sql .= ' AS ' . $this->dialect->quoteIdentifier($component->alias);
        }

        return $sql;
    }
}
