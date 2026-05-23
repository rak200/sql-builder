<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Postgres\Renderer;

use Rak200\SqlBuilder\Ddl\UniqueKey;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\UniqueKeyRenderer as BaseUniqueKeyRenderer;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;

/**
 * PostgreSQL <15 UNIQUE renderer.
 *
 * Rejects `NULLS [NOT] DISTINCT`: the modifier was introduced in PostgreSQL 15.
 * The {@see \Rak200\SqlBuilder\Dialect\Postgres\Postgres15Dialect} reverts to
 * the permissive base renderer to accept it.
 *
 * @package Rak200\SqlBuilder\Dialect\Postgres\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UniqueKeyRenderer extends BaseUniqueKeyRenderer {

    protected function renderNullsDistinct(UniqueKey $component): string {
        if ($component->nullsDistinct !== null) {
            throw new UnsupportedFeatureException(
                'PostgreSQL <15 does not support NULLS [NOT] DISTINCT on UNIQUE constraints.'
            );
        }
        return '';
    }
}
