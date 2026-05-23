<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Ddl\UniqueKey;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\UniqueKeyRenderer as BaseUniqueKeyRenderer;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;

/**
 * MariaDB / MySQL UNIQUE renderer.
 *
 * Rejects `NULLS [NOT] DISTINCT`: MariaDB and MySQL have no equivalent.
 * Multiple NULLs are always considered distinct.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UniqueKeyRenderer extends BaseUniqueKeyRenderer {

    protected function renderNullsDistinct(UniqueKey $component): string {
        if ($component->nullsDistinct !== null) {
            throw new UnsupportedFeatureException(
                'MariaDB / MySQL do not support NULLS [NOT] DISTINCT on UNIQUE constraints.'
            );
        }
        return '';
    }
}
