<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Ddl;

use Rak200\SqlBuilder\Ddl\PrimaryKey;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\Utils\Str;

/**
 * Renders a {@see PrimaryKey} as `[CONSTRAINT "name"] PRIMARY KEY (cols)`.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class PrimaryKeyRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(PrimaryKey $component): string {
        $sql = 'PRIMARY KEY';

        if ($component->name !== '') {
            $sql = sprintf('CONSTRAINT %s PRIMARY KEY', $this->dialect->quoteIdentifier($component->name));
        }

        $sql .= Str::join(
            array_map(fn(string $column) => $this->dialect->quoteIdentifier($column), $component->columns),
            ', ',
            ' (',
            ')'
        );

        return $sql;
    }
}
