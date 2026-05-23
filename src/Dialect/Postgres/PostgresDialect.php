<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Postgres;

use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\Postgres\Renderer\InsertRenderer;
use Rak200\SqlBuilder\Dialect\Postgres\Renderer\MergeRenderer;
use Rak200\SqlBuilder\Dialect\Postgres\Renderer\UniqueKeyRenderer;
use Rak200\SqlBuilder\Dialect\Postgres\Renderer\UuidInputExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\UuidInputExpressionRenderer as DefaultUuidInputExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\UniqueKeyRenderer as DefaultUniqueKeyRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\InsertRenderer as DefaultInsertRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\MergeRenderer as DefaultMergeRenderer;
use Rak200\SqlBuilder\Prepared\Binder;

/**
 * PostgreSQL dialect.
 *
 * Differs from the {@see DefaultDialect} in:
 * - **Identifier quoting** uses double quotes instead of backticks.
 * - **String escaping** only doubles single quotes; backslashes are literal
 *   (PostgreSQL standard-conforming strings).
 * - **ON DUPLICATE KEY UPDATE** is rejected — PostgreSQL uses `ON CONFLICT`.
 *
 * Postgres-only multi-table forms (`UPDATE ... FROM`, `DELETE ... USING`) and
 * `RETURNING` are inherited from the permissive default.
 *
 * @package Rak200\SqlBuilder\Dialect\Postgres
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class PostgresDialect extends DefaultDialect {

    /** {@inheritdoc} */
    public function quoteIdentifier(string $identifier): string {
        $quoted = '"' . str_replace('.', '"."', $identifier) . '"';
        return str_replace(['"*"', '""'], ['*', '"'], $quoted);
    }

    /** {@inheritdoc} */
    public function quoteValue(mixed $value): string {
        return match (true) {
            $value === null  => 'NULL',
            is_int($value)   => (string) $value,
            is_float($value) => (string) $value,
            is_bool($value)  => $value ? 'TRUE' : 'FALSE',
            default          => "'" . str_replace(
                "'",
                "''",
                mb_convert_encoding((string) $value, 'UTF-8', 'UTF-8')
            ) . "'",
        };
    }

    protected function insertRenderer(): DefaultInsertRenderer {
        return $this->insertRenderer ??= new InsertRenderer($this);
    }

    protected function uniqueKeyRenderer(): DefaultUniqueKeyRenderer {
        return $this->uniqueKeyRenderer ??= new UniqueKeyRenderer($this);
    }

    protected function mergeRenderer(): DefaultMergeRenderer {
        return $this->mergeRenderer ??= new MergeRenderer($this);
    }

    protected function uuidInputExpressionRenderer(): DefaultUuidInputExpressionRenderer {
        return $this->uuidInputExpressionRenderer ??= new UuidInputExpressionRenderer($this);
    }

    /** {@inheritdoc} */
    public function newBinder(): Binder {
        return new PostgresBinder();
    }
}
