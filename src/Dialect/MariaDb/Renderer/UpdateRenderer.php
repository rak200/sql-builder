<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Dialect\Renderer\Dml\UpdateRenderer as BaseUpdateRenderer;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;
use Rak200\SqlBuilder\Dml\Update;

/**
 * MariaDB UPDATE renderer.
 *
 * Rejects PostgreSQL-only `UPDATE ... FROM`, and rejects `RETURNING` (added
 * to MariaDB only from 10.5 — see {@see \Rak200\SqlBuilder\Dialect\MariaDb\MariaDb105Dialect}).
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UpdateRenderer extends BaseUpdateRenderer {

    protected function renderFrom(Update $component): string {
        if ($component->from !== []) {
            throw new UnsupportedFeatureException('MariaDB does not support UPDATE ... FROM; use a JOIN-style multi-table UPDATE.');
        }
        return '';
    }

    protected function renderReturning(Update $component): string {
        if ($component->returning !== []) {
            throw new UnsupportedFeatureException('MariaDB does not support RETURNING on UPDATE.');
        }
        return '';
    }
}
