<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\UuidOutputExpression;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Default pass-through renderer for {@see UuidOutputExpression}.
 *
 * The default dialect is unopinionated about UUID encoding — the inner
 * column expression is emitted verbatim (including its own alias).
 * Vendor dialects override to apply `BIN_TO_UUID(...)` decoding wrappers
 * (MariaDB).
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UuidOutputExpressionRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(UuidOutputExpression $component): string {
        $sql = $this->dialect->renderExpression($component->inner);

        if ($component->alias !== null) {
            $sql .= ' AS ' . $this->dialect->quoteIdentifier($component->alias);
        }

        return $sql;
    }
}
