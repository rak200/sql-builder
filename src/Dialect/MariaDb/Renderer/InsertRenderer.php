<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Dialect\Renderer\Dml\InsertRenderer as BaseInsertRenderer;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;
use Rak200\SqlBuilder\Dml\Insert;

/**
 * MariaDB INSERT renderer.
 *
 * Permits the inherited `ON DUPLICATE KEY UPDATE` clause but rejects
 * `RETURNING`, which is only supported from MariaDB 10.5 onwards. The
 * {@see \Rak200\SqlBuilder\Dialect\MariaDb\MariaDb105Dialect} re-enables it.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class InsertRenderer extends BaseInsertRenderer {

    protected function renderReturning(Insert $component): string {
        if ($component->returning !== []) {
            throw new UnsupportedFeatureException('MariaDB before 10.5 does not support RETURNING on INSERT.');
        }
        return '';
    }
}
