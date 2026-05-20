<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\TableReference;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\SqlBuilder\Dml\Select;

/**
 * Renders a {@see TableReference} (table name or subquery) with optional alias.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class TableReferenceRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(TableReference $component): string {
        if ($component->source instanceof Select) {
            return sprintf(
                '(%s) AS %s',
                $this->dialect->renderSelect($component->source),
                $this->dialect->quoteIdentifier($component->alias)
            );
        }

        if ($component->alias !== null) {
            return sprintf(
                '%s AS %s',
                $this->dialect->quoteIdentifier($component->source),
                $this->dialect->quoteIdentifier($component->alias)
            );
        }

        return $this->dialect->quoteIdentifier($component->source);
    }
}
