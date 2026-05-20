<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Ddl;

use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\SqlBuilder\Utils\StringUtils;

/**
 * Renders a {@see Column} definition: `"name" TYPE[(length)] NULL|NOT NULL
 * [DEFAULT expr] [AUTO_INCREMENT] [PRIMARY KEY]`.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class ColumnRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Column $component): string {
        $name = $this->dialect->quoteIdentifier($component->name);

        $sql = "$name {$component->type->value}";
        $sql .= StringUtils::wrap((string) $component->length, '(', ')');
        $sql .= $component->nullable ? ' NULL' : ' NOT NULL';

        if ($component->default !== null) {
            $sql .= ' DEFAULT ' . $this->dialect->renderExpression($component->default);
        }

        if ($component->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($component->primaryKey) {
            $sql .= ' PRIMARY KEY';
        }

        return $sql;
    }
}
