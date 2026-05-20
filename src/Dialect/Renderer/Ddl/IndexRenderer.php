<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Ddl;

use Rak200\SqlBuilder\Ddl\Index;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders an {@see Index} as `CREATE [UNIQUE] INDEX "name" ON "table" (...)`.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class IndexRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Index $component): string {
        $unique  = $component->unique ? 'UNIQUE ' : '';
        $columns = implode(', ', array_map(
            fn(string $column) => sprintf('"%s"', $column),
            $component->columns
        ));

        return sprintf(
            'CREATE %sINDEX "%s" ON "%s" (%s)',
            $unique,
            $component->name,
            $component->table,
            $columns
        );
    }
}
