<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\UuidInputExpression;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Default pass-through renderer for {@see UuidInputExpression}.
 *
 * The default dialect is unopinionated about UUID encoding — the inner
 * expression is emitted verbatim. Vendor dialects override to apply
 * `::uuid` casts (PostgreSQL) or `UUID_TO_BIN(...)` wrappers (MariaDB).
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UuidInputExpressionRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(UuidInputExpression $component): string {
        $sql = $this->dialect->renderExpression($component->inner);

        if ($component->alias !== null) {
            $sql .= ' AS ' . $this->dialect->quoteIdentifier($component->alias);
        }

        return $sql;
    }
}
