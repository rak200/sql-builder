<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Ddl\UniqueKey;
use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDb105Dialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\Postgres15Dialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;

final class NullsDistinctDialectTest extends TestCase {

    public function testDefaultDialectAcceptsNullsDistinct(): void {
        $sql = UniqueKey::create('uq')->columns(['email'])->nullsNotDistinct()
            ->toSql(new DefaultDialect());

        $this->assertSame('CONSTRAINT `uq` UNIQUE NULLS NOT DISTINCT (`email`)', $sql);
    }

    public function testPostgres15AcceptsNullsNotDistinct(): void {
        $sql = UniqueKey::create('uq')->columns(['email'])->nullsNotDistinct()
            ->toSql(new Postgres15Dialect());

        $this->assertSame('CONSTRAINT "uq" UNIQUE NULLS NOT DISTINCT ("email")', $sql);
    }

    public function testPostgres15AcceptsNullsDistinct(): void {
        $sql = UniqueKey::create()->columns(['email'])->nullsDistinct()
            ->toSql(new Postgres15Dialect());

        $this->assertSame('UNIQUE NULLS DISTINCT ("email")', $sql);
    }

    public function testPostgresBaseRejectsNullsNotDistinct(): void {
        $uk = UniqueKey::create('uq')->columns(['email'])->nullsNotDistinct();

        $this->expectException(UnsupportedFeatureException::class);
        $uk->toSql(new PostgresDialect());
    }

    public function testPostgresBaseAcceptsUniqueWithoutModifier(): void {
        $sql = UniqueKey::create('uq')->columns(['email'])->toSql(new PostgresDialect());

        $this->assertSame('CONSTRAINT "uq" UNIQUE ("email")', $sql);
    }

    public function testMariaDbRejectsNullsNotDistinct(): void {
        $uk = UniqueKey::create('uq')->columns(['email'])->nullsNotDistinct();

        $this->expectException(UnsupportedFeatureException::class);
        $uk->toSql(new MariaDbDialect());
    }

    public function testMariaDb105RejectsNullsNotDistinct(): void {
        $uk = UniqueKey::create('uq')->columns(['email'])->nullsNotDistinct();

        $this->expectException(UnsupportedFeatureException::class);
        $uk->toSql(new MariaDb105Dialect());
    }
}
