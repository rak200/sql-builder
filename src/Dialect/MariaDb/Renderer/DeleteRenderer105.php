<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Dml\Delete;
use Rak200\Utils\Str;

/**
 * MariaDB 10.5+ DELETE renderer.
 *
 * Inherits the USING rejection from {@see DeleteRenderer} and re-enables
 * RETURNING for single-table DELETE.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class DeleteRenderer105 extends DeleteRenderer {

    protected function renderReturning(Delete $component): string {
        $rendered = array_map(
            fn($expression) => $this->dialect->renderExpression($expression),
            $component->returning
        );
        return Str::join($rendered, ', ', ' RETURNING ');
    }
}
