<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Dialect\Renderer\Dml\DeleteRenderer as BaseDeleteRenderer;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;
use Rak200\SqlBuilder\Dml\Delete;

/**
 * MariaDB DELETE renderer.
 *
 * Rejects PostgreSQL-only `DELETE ... USING` and the `RETURNING` clause
 * (re-enabled by {@see \Rak200\SqlBuilder\Dialect\MariaDb\MariaDb105Dialect}).
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class DeleteRenderer extends BaseDeleteRenderer {

    protected function renderUsing(Delete $component): string {
        if ($component->using !== []) {
            throw new UnsupportedFeatureException('MariaDB does not support DELETE ... USING; use a JOIN-style multi-table DELETE.');
        }
        return '';
    }

    protected function renderReturning(Delete $component): string {
        if ($component->returning !== []) {
            throw new UnsupportedFeatureException('MariaDB before 10.5 does not support RETURNING on DELETE.');
        }
        return '';
    }
}
