<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\ColumnReference;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders a {@see ColumnReference} (qualified or unqualified, no alias).
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class ColumnReferenceRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(ColumnReference $component): string {
        return $this->dialect->quoteIdentifier($component->name);
    }
}
