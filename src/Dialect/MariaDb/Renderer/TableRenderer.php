<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Ddl\Table;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\TableRenderer as BaseTableRenderer;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;

/**
 * MariaDB / MySQL Table renderer.
 *
 * Inherits CREATE / ALTER / DROP unchanged and overrides TRUNCATE to reject
 * PostgreSQL-only modifiers: `RESTART IDENTITY` / `CONTINUE IDENTITY` and
 * `CASCADE` / `RESTRICT` are not part of the MariaDB `TRUNCATE TABLE`
 * syntax.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class TableRenderer extends BaseTableRenderer {

    protected function renderTruncate(Table $component): string {
        if ($component->restartIdentity || $component->continueIdentity) {
            throw new UnsupportedFeatureException(
                'MariaDB TRUNCATE TABLE does not support RESTART IDENTITY / CONTINUE IDENTITY; '
                . 'AUTO_INCREMENT is always reset by TRUNCATE.'
            );
        }

        if ($component->cascade || $component->restrict) {
            throw new UnsupportedFeatureException(
                'MariaDB TRUNCATE TABLE does not support CASCADE / RESTRICT.'
            );
        }

        return sprintf(
            'TRUNCATE TABLE %s',
            $this->dialect->quoteIdentifier($this->dialect->resolveTableName($component->name))
        );
    }
}
