<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Dml;

use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\SqlBuilder\Dml\Cte;
use Rak200\SqlBuilder\Dml\Set;

/**
 * Renders a single {@see Cte} entry as `name [(col, ...)] AS (query)`.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class CteRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Cte $component): string {
        $sql = $this->dialect->quoteIdentifier($component->name);

        if ($component->columns !== null && $component->columns !== []) {
            $sql .= ' (' . implode(', ', array_map(
                fn(string $column) => $this->dialect->quoteIdentifier($column),
                $component->columns
            )) . ')';
        }

        $body = $component->query instanceof Set
            ? $this->dialect->renderSet($component->query)
            : $this->dialect->renderSelect($component->query);

        return $sql . ' AS (' . $body . ')';
    }
}
