<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Postgres;

use Rak200\SqlBuilder\Dialect\Renderer\Ddl\UniqueKeyRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\MergeRenderer;

/**
 * PostgreSQL 15+ dialect.
 *
 * Re-enables features the base {@see PostgresDialect} rejects because they
 * landed in PostgreSQL 15: `NULLS [NOT] DISTINCT` on `UNIQUE` constraints
 * and the SQL:2003 `MERGE` statement. Both come back by reverting to the
 * permissive default renderers.
 *
 * @package Rak200\SqlBuilder\Dialect\Postgres
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Postgres15Dialect extends PostgresDialect {

    protected function uniqueKeyRenderer(): UniqueKeyRenderer {
        return $this->uniqueKeyRenderer ??= new UniqueKeyRenderer($this);
    }

    protected function mergeRenderer(): MergeRenderer {
        return $this->mergeRenderer ??= new MergeRenderer($this);
    }
}
