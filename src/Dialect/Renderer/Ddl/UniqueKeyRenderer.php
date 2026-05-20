<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Ddl;

use Rak200\SqlBuilder\Ddl\UniqueKey;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\SqlBuilder\Utils\StringUtils;

/**
 * Renders a {@see UniqueKey} as `[CONSTRAINT "name"] UNIQUE (cols)`.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UniqueKeyRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(UniqueKey $component): string {
        $sql = 'UNIQUE';

        if ($component->name !== '') {
            $sql = sprintf('CONSTRAINT "%s" UNIQUE', $component->name);
        }

        $sql .= StringUtils::join(
            array_map(fn(string $column) => sprintf('"%s"', $column), $component->columns),
            ', ',
            ' (',
            ')'
        );

        return $sql;
    }
}
