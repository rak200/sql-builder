<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use LogicException;
use Rak200\SqlBuilder\Common\Expression\Param as ParameterExpression;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders a {@see ParameterExpression} to its dialect-specific placeholder.
 *
 * A `ParameterExpression` only makes sense inside a `prepare()` render —
 * the binder must be active on the dialect. Inline rendering (`__toString`
 * or `toSql()` without `prepare()`) raises a {@see LogicException} because
 * the value is unknown at that point.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class ParameterExpressionRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(ParameterExpression $component): string {
        $binder = $this->dialect->binder;
        if ($binder === null) {
            throw new LogicException(
                'ParameterExpression can only be rendered through prepare(); '
                . 'use Expression::val() or inline scalars for __toString().'
            );
        }

        $sql = $binder->bind($component->value, $component->key);

        if ($component->alias !== null) {
            $sql .= ' AS ' . $this->dialect->quoteIdentifier($component->alias);
        }

        return $sql;
    }
}
