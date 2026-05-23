<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Dml\Insert;
use Rak200\Utils\Str;

/**
 * MariaDB 10.5+ INSERT renderer.
 *
 * Inherits the `ON CONFLICT → ON DUPLICATE KEY UPDATE` translation from
 * {@see InsertRenderer} and re-enables RETURNING.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class InsertRenderer105 extends InsertRenderer {

    protected function renderReturning(Insert $component): string {
        $rendered = array_map(
            fn($expression) => $this->dialect->renderExpression($expression),
            $component->returning
        );
        return Str::join($rendered, ', ', ' RETURNING ');
    }
}
