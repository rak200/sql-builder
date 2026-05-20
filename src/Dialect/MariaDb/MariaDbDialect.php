<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb;

use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\MariaDb\Renderer\InsertRenderer;
use Rak200\SqlBuilder\Dialect\MariaDb\Renderer\UpdateRenderer;
use Rak200\SqlBuilder\Dialect\MariaDb\Renderer\DeleteRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\DeleteRenderer as DefaultDeleteRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\InsertRenderer as DefaultInsertRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\UpdateRenderer as DefaultUpdateRenderer;

/**
 * MariaDB / MySQL dialect.
 *
 * Inherits the permissive baseline and overrides the write renderers so that
 * features unsupported by the base MariaDB/MySQL platform raise
 * {@see \Rak200\SqlBuilder\Dialect\UnsupportedFeatureException}:
 *
 * - `RETURNING` in INSERT/UPDATE/DELETE is rejected (added in MariaDB 10.5
 *   for INSERT, 10.5 for DELETE on a single table, and not present in MySQL).
 *   Use {@see MariaDb105Dialect} when the target server is MariaDB ≥ 10.5.
 * - PostgreSQL-only multi-table forms (`UPDATE ... FROM`, `DELETE ... USING`)
 *   are rejected.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class MariaDbDialect extends DefaultDialect {

    protected function insertRenderer(): DefaultInsertRenderer {
        return $this->insertRenderer ??= new InsertRenderer($this);
    }

    protected function updateRenderer(): DefaultUpdateRenderer {
        return $this->updateRenderer ??= new UpdateRenderer($this);
    }

    protected function deleteRenderer(): DefaultDeleteRenderer {
        return $this->deleteRenderer ??= new DeleteRenderer($this);
    }
}
