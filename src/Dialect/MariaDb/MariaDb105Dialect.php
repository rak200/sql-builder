<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb;

use Rak200\SqlBuilder\Dialect\Renderer\Dml\DeleteRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\InsertRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\UpdateRenderer;

/**
 * MariaDB 10.5+ dialect.
 *
 * Re-enables `RETURNING` on INSERT, UPDATE and DELETE by reverting to the
 * permissive default renderers from {@see \Rak200\SqlBuilder\Dialect\DefaultDialect}.
 * Postgres-only multi-table forms (`UPDATE ... FROM`, `DELETE ... USING`)
 * remain rejected via the {@see MariaDbDialect} overrides for those clauses.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class MariaDb105Dialect extends MariaDbDialect {

    protected function insertRenderer(): InsertRenderer {
        return $this->insertRenderer ??= new Renderer\InsertRenderer105($this);
    }

    protected function updateRenderer(): UpdateRenderer {
        return $this->updateRenderer ??= new Renderer\UpdateRenderer105($this);
    }

    protected function deleteRenderer(): DeleteRenderer {
        return $this->deleteRenderer ??= new Renderer\DeleteRenderer105($this);
    }
}
