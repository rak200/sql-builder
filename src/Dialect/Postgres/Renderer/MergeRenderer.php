<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Postgres\Renderer;

use Rak200\SqlBuilder\Dialect\Renderer\Dml\MergeRenderer as BaseMergeRenderer;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;
use Rak200\SqlBuilder\Dml\Merge;

/**
 * PostgreSQL <15 MERGE renderer.
 *
 * MERGE landed in PostgreSQL 15; older versions emulate it with
 * `INSERT ... ON CONFLICT`. {@see \Rak200\SqlBuilder\Dialect\Postgres\Postgres15Dialect}
 * reverts to the permissive base renderer to accept the statement.
 *
 * @package Rak200\SqlBuilder\Dialect\Postgres\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class MergeRenderer extends BaseMergeRenderer {

    public function render(Merge $component): string {
        throw new UnsupportedFeatureException(
            'PostgreSQL <15 does not support MERGE; use Insert::onConflict() instead.'
        );
    }
}
