<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\Join;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\Utils\Str;

/**
 * Renders a {@see Join} clause (INNER/LEFT/RIGHT/FULL/CROSS/NATURAL with ON or USING).
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class JoinRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Join $component): string {
        $component->validate();

        $type = $component->natural ? "NATURAL {$component->type->value}" : $component->type->value;
        $sql  = $type . ' ' . $this->dialect->renderTableReference($component->table);

        if ($component->on !== null) {
            return $sql . ' ON ' . $this->dialect->renderExpression($component->on);
        }

        if ($component->using === null) {
            return $sql;
        }

        $rendered = array_map(
            fn($column) => $this->dialect->renderExpression($column),
            $component->using
        );

        return Str::join($rendered, ', ', "$sql USING (", ')');
    }
}
