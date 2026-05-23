<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\ColumnRenderer as BaseColumnRenderer;

/**
 * MariaDB / MySQL column renderer.
 *
 * Remaps the logical {@see DataType::Uuid} to the physical `BINARY(16)`
 * storage type — MariaDB has no native UUID type, and the standard
 * simulation stores UUIDs as 16-byte binary values converted via
 * `UUID_TO_BIN()` / `BIN_TO_UUID()` at the value boundaries.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class ColumnRenderer extends BaseColumnRenderer {

    /** {@inheritdoc} */
    protected function renderType(Column $component): string {
        if ($component->type === DataType::Uuid) {
            return 'BINARY(16)';
        }
        return parent::renderType($component);
    }
}
