<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Dml\Update;

/**
 * MariaDB 10.5+ UPDATE renderer.
 *
 * Inherits the FROM rejection from {@see UpdateRenderer} but re-enables
 * RETURNING by delegating to the permissive default behaviour.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UpdateRenderer105 extends UpdateRenderer {

    protected function renderReturning(Update $component): string {
        $rendered = array_map(
            fn($expression) => $this->dialect->renderExpression($expression),
            $component->returning
        );
        return \Rak200\SqlBuilder\Utils\Str::join($rendered, ', ', ' RETURNING ');
    }
}
