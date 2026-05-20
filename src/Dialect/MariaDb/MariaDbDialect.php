<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb;

use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\MariaDb\Renderer\DeleteRenderer;
use Rak200\SqlBuilder\Dialect\MariaDb\Renderer\InsertRenderer;
use Rak200\SqlBuilder\Dialect\MariaDb\Renderer\SchemaRenderer;
use Rak200\SqlBuilder\Dialect\MariaDb\Renderer\UpdateRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\SchemaRenderer as DefaultSchemaRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\DeleteRenderer as DefaultDeleteRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\InsertRenderer as DefaultInsertRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\UpdateRenderer as DefaultUpdateRenderer;

/**
 * MariaDB / MySQL dialect.
 *
 * Inherits the permissive baseline and adds the vendor-specific overrides:
 *
 * - **Schema simulation**: MariaDB has no schema namespace independent of
 *   the database. Any `schema.table` reference is flattened to
 *   `schema_table` via {@see resolveTableName()} / {@see resolveColumnReference()},
 *   so multi-tenant code that addresses tables with a schema prefix keeps
 *   working without touching the database. The `Schema` DDL builder refuses
 *   to emit CREATE/DROP/ALTER SCHEMA (see {@see SchemaRenderer}).
 * - **Feature gates**: PostgreSQL-only multi-table forms (`UPDATE ... FROM`,
 *   `DELETE ... USING`) are rejected, and `RETURNING` on INSERT/UPDATE/DELETE
 *   is rejected. Use {@see MariaDb105Dialect} for MariaDB ≥ 10.5 where
 *   RETURNING is supported.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class MariaDbDialect extends DefaultDialect {

    /**
     * Flatten `schema.table` to `schema_table`.
     *
     * Tables with bare names pass through unchanged. Three- or more-part
     * identifiers are also flattened end-to-end (e.g. `a.b.c` → `a_b_c`)
     * because MariaDB has no namespace deeper than the database.
     */
    public function resolveTableName(string $name): string {
        if (!str_contains($name, '.')) {
            return $name;
        }
        return str_replace('.', '_', $name);
    }

    /**
     * Flatten the schema portion of a qualified column reference:
     * `schema.table.column` → `schema_table.column`. References with two or
     * fewer parts (`table.column`, `column`) pass through unchanged.
     */
    public function resolveColumnReference(string $name): string {
        $parts = explode('.', $name);
        if (count($parts) < 3) {
            return $name;
        }
        $column = array_pop($parts);
        return implode('_', $parts) . '.' . $column;
    }

    protected function insertRenderer(): DefaultInsertRenderer {
        return $this->insertRenderer ??= new InsertRenderer($this);
    }

    protected function updateRenderer(): DefaultUpdateRenderer {
        return $this->updateRenderer ??= new UpdateRenderer($this);
    }

    protected function deleteRenderer(): DefaultDeleteRenderer {
        return $this->deleteRenderer ??= new DeleteRenderer($this);
    }

    protected function schemaRenderer(): DefaultSchemaRenderer {
        return $this->schemaRenderer ??= new SchemaRenderer($this);
    }
}
