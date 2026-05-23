<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Common\Expression\Column as ColumnExpression;
use Rak200\SqlBuilder\Common\Expression\UuidOutput as UuidOutputExpression;
use Rak200\SqlBuilder\Dialect\Renderer\Common\UuidOutputExpressionRenderer as BaseUuidOutputExpressionRenderer;

/**
 * MariaDB / MySQL UUID output renderer.
 *
 * Wraps the inner column reference in `BIN_TO_UUID(...)` so the 16-byte
 * binary value stored on disk is decoded back to a text UUID at the
 * projection boundary. When the inner is a {@see ColumnExpression} with
 * an alias, the alias is hoisted **outside** the `BIN_TO_UUID(...)` call
 * so the projected column keeps the expected name.
 *
 * The `swap_flag` second argument is intentionally omitted — see
 * {@see UuidInputExpressionRenderer} for the reasoning.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UuidOutputExpressionRenderer extends BaseUuidOutputExpressionRenderer {

    /** {@inheritdoc} */
    public function render(UuidOutputExpression $component): string {
        $inner = $component->inner;

        if ($inner instanceof ColumnExpression) {
            $name = $this->dialect->quoteIdentifier(
                $this->dialect->resolveColumnReference($inner->name)
            );
            $sql = 'BIN_TO_UUID(' . $name . ')';
            if ($inner->alias !== null) {
                $sql .= ' AS ' . $this->dialect->quoteIdentifier($inner->alias);
            }
        } else {
            $sql = 'BIN_TO_UUID(' . $this->dialect->renderExpression($inner) . ')';
        }

        if ($component->alias !== null) {
            $sql .= ' AS ' . $this->dialect->quoteIdentifier($component->alias);
        }

        return $sql;
    }
}
