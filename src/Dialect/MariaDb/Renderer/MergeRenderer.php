<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Dialect\Renderer\Dml\MergeRenderer as BaseMergeRenderer;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;
use Rak200\SqlBuilder\Dml\Merge;

/**
 * MariaDB / MySQL MERGE renderer — always rejects.
 *
 * Neither MariaDB nor MySQL implements the SQL:2003 MERGE statement. Use
 * `INSERT ... ON DUPLICATE KEY UPDATE` (or the portable
 * {@see \Rak200\SqlBuilder\Dml\Insert::onConflict()}) instead.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class MergeRenderer extends BaseMergeRenderer {

    public function render(Merge $component): string {
        throw new UnsupportedFeatureException(
            'MariaDB / MySQL do not support MERGE; use Insert::onConflict() / onDuplicateKeyUpdate() instead.'
        );
    }
}
