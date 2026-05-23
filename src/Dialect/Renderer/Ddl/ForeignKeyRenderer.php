<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Ddl;

use Rak200\SqlBuilder\Ddl\ForeignKey;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\Utils\Str;

/**
 * Renders a {@see ForeignKey} as
 * `[CONSTRAINT "name"] FOREIGN KEY (cols) REFERENCES "table" (refs) [ON DELETE ...] [ON UPDATE ...]`.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class ForeignKeyRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(ForeignKey $component): string {
        $sql = 'FOREIGN KEY';

        if ($component->name !== '') {
            $sql = sprintf('CONSTRAINT %s FOREIGN KEY', $this->dialect->quoteIdentifier($component->name));
        }

        $sql .= Str::join(
            array_map(fn(string $column) => $this->dialect->quoteIdentifier($column), $component->columns),
            ', ',
            ' (',
            ')'
        );

        if ($component->referenceTable !== '') {
            $sql .= Str::join(
                array_map(fn(string $column) => $this->dialect->quoteIdentifier($column), $component->referenceColumns),
                ', ',
                sprintf(
                    ' REFERENCES %s (',
                    $this->dialect->quoteIdentifier($this->dialect->resolveTableName($component->referenceTable))
                ),
                ')'
            );
        }

        if ($component->onDelete !== null) {
            $sql .= ' ON DELETE ' . $component->onDelete->value;
        }

        if ($component->onUpdate !== null) {
            $sql .= ' ON UPDATE ' . $component->onUpdate->value;
        }

        return $sql;
    }
}
