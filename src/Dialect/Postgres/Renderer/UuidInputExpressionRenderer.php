<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Postgres\Renderer;

use Rak200\SqlBuilder\Common\ParameterExpression;
use Rak200\SqlBuilder\Common\UuidInputExpression;
use Rak200\SqlBuilder\Common\ValueExpression;
use Rak200\SqlBuilder\Dialect\Renderer\Common\UuidInputExpressionRenderer as BaseUuidInputExpressionRenderer;

/**
 * PostgreSQL UUID input renderer.
 *
 * Appends an explicit `::uuid` cast when the inner expression is a literal
 * value or a parameter placeholder — `'aaaa-…'::uuid` / `$1::uuid` — so
 * PostgreSQL knows the type in contexts where it cannot infer one from a
 * target column (e.g. `SELECT $1 AS id`). Column references and other
 * expression types pass through without the cast: in `WHERE uuid_col =
 * other_uuid_col` both sides already have a known type.
 *
 * @package Rak200\SqlBuilder\Dialect\Postgres\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UuidInputExpressionRenderer extends BaseUuidInputExpressionRenderer {

    /** {@inheritdoc} */
    public function render(UuidInputExpression $component): string {
        $inner = $component->inner;
        $sql   = $this->dialect->renderExpression($inner);

        if ($inner instanceof ValueExpression || $inner instanceof ParameterExpression) {
            $sql .= '::uuid';
        }

        if ($component->alias !== null) {
            $sql .= ' AS ' . $this->dialect->quoteIdentifier($component->alias);
        }

        return $sql;
    }
}
