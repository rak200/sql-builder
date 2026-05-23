<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Common\UuidInputExpression;
use Rak200\SqlBuilder\Dialect\Renderer\Common\UuidInputExpressionRenderer as BaseUuidInputExpressionRenderer;

/**
 * MariaDB / MySQL UUID input renderer.
 *
 * Wraps the inner value expression in `UUID_TO_BIN(...)` so a text UUID is
 * converted to the 16-byte binary form stored on disk. The `swap_flag`
 * second argument is intentionally omitted — it changes the byte layout
 * incompatibly with text-UUID ordering and would need a matching flag on
 * the read side.
 *
 * Placeholders flow through unchanged: in bind mode the binder emits its
 * usual `?` (or `$N`) and the wrap stays purely on the SQL side, so the
 * caller still binds the **text** UUID to the parameter.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UuidInputExpressionRenderer extends BaseUuidInputExpressionRenderer {

    /** {@inheritdoc} */
    public function render(UuidInputExpression $component): string {
        $sql = 'UUID_TO_BIN(' . $this->dialect->renderExpression($component->inner) . ')';

        if ($component->alias !== null) {
            $sql .= ' AS ' . $this->dialect->quoteIdentifier($component->alias);
        }

        return $sql;
    }
}
