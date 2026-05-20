<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Postgres\Renderer;

use Rak200\SqlBuilder\Dialect\Renderer\Dml\InsertRenderer as BaseInsertRenderer;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;
use Rak200\SqlBuilder\Dml\Insert;

/**
 * PostgreSQL INSERT renderer.
 *
 * Rejects `ON DUPLICATE KEY UPDATE` because PostgreSQL uses
 * `INSERT ... ON CONFLICT (...) DO UPDATE` instead.
 *
 * @package Rak200\SqlBuilder\Dialect\Postgres\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class InsertRenderer extends BaseInsertRenderer {

    protected function renderOnDuplicateKeyUpdate(Insert $component): string {
        if ($component->onDuplicateKey !== []) {
            throw new UnsupportedFeatureException(
                'PostgreSQL does not support ON DUPLICATE KEY UPDATE; use ON CONFLICT (...) DO UPDATE.'
            );
        }
        return '';
    }
}
