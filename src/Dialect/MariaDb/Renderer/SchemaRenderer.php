<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Ddl\Schema;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\SchemaRenderer as BaseSchemaRenderer;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;

/**
 * MariaDB Schema renderer.
 *
 * MariaDB has no first-class schema namespace inside a database — `SCHEMA`
 * is an alias for `DATABASE`. To keep the schema/database boundary intact,
 * this dialect refuses to emit DDL that would create, drop or rename a
 * physical database. Schemas are still honoured at the *table* level: any
 * `schema.table` reference is flattened to `schema_table` by
 * {@see \Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect::resolveTableName()}.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class SchemaRenderer extends BaseSchemaRenderer {

    protected function renderCreate(Schema $component): string {
        throw new UnsupportedFeatureException(
            'MariaDB has no first-class schemas; this dialect simulates them as table-name prefixes. '
            . 'CREATE SCHEMA would either be a no-op or modify the database — neither is desired. '
            . 'Create tables with a `schema.table` name and the prefix will be applied automatically.'
        );
    }

    protected function renderDrop(Schema $component): string {
        throw new UnsupportedFeatureException(
            'MariaDB has no first-class schemas; this dialect simulates them as table-name prefixes. '
            . 'DROP SCHEMA cannot be expressed without dropping the database itself. '
            . 'Drop the individual `schema_table` tables instead.'
        );
    }

    protected function renderAlter(Schema $component): string {
        throw new UnsupportedFeatureException(
            'MariaDB has no first-class schemas; this dialect simulates them as table-name prefixes. '
            . 'ALTER SCHEMA ... RENAME TO is not expressible — rename each `oldschema_table` '
            . 'to `newschema_table` individually via ALTER TABLE.'
        );
    }
}
