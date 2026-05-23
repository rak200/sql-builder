<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;

final class UuidColumnTest extends TestCase {

    public function testDefaultDialectEmitsUuidType(): void {
        $column = Column::create('id', DataType::Uuid);

        $this->assertSame('`id` UUID NULL', $column->toSql(new DefaultDialect()));
    }

    public function testPostgresDialectEmitsUuidType(): void {
        $column = Column::create('id', DataType::Uuid);

        $this->assertSame('"id" UUID NULL', $column->toSql(new PostgresDialect()));
    }

    public function testMariaDbDialectRewritesUuidToBinary16(): void {
        $column = Column::create('id', DataType::Uuid);

        $this->assertSame('`id` BINARY(16) NULL', $column->toSql(new MariaDbDialect()));
    }

    public function testMariaDbPreservesNotNullAndPrimaryKeyOnUuidColumn(): void {
        $column = Column::create('id', DataType::Uuid)
            ->nullable(false)
            ->primaryKey();

        $this->assertSame(
            '`id` BINARY(16) NOT NULL PRIMARY KEY',
            $column->toSql(new MariaDbDialect())
        );
    }

    public function testMariaDbDoesNotRemapOtherTypes(): void {
        $column = Column::create('email', DataType::VarChar)->length(255);

        $this->assertSame(
            '`email` VARCHAR(255) NULL',
            $column->toSql(new MariaDbDialect())
        );
    }
}
