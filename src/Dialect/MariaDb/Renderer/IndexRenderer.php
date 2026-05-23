<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Ddl\Index;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\IndexRenderer as BaseIndexRenderer;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;

/**
 * MariaDB / MySQL Index renderer.
 *
 * `DROP INDEX` on MariaDB requires the parent table (`DROP INDEX name ON
 * table`) and accepts neither `IF EXISTS` (before 10.1.4) nor `CASCADE`.
 * `IF EXISTS` is allowed silently — it's a no-op DDL hint with valid
 * MariaDB syntax — but `CASCADE` is rejected because MariaDB drops indexes
 * unconditionally and cannot model the restrict/cascade distinction.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class IndexRenderer extends BaseIndexRenderer {

    protected function renderDrop(Index $component): string {
        if ($component->table === '') {
            throw new UnsupportedFeatureException(
                'MariaDB DROP INDEX requires the parent table; set Index::table() before dropping.'
            );
        }

        if ($component->cascade) {
            throw new UnsupportedFeatureException(
                'MariaDB DROP INDEX does not support CASCADE.'
            );
        }

        $ifExists = $component->ifExists ? ' IF EXISTS' : '';

        return sprintf(
            'DROP INDEX%s %s ON %s',
            $ifExists,
            $this->dialect->quoteIdentifier($component->name),
            $this->dialect->quoteIdentifier($this->dialect->resolveTableName($component->table))
        );
    }
}
