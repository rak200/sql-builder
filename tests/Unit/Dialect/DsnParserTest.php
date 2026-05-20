<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDb105Dialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\Postgres15Dialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;

final class DsnParserTest extends TestCase {

    public function testMariaDbSchemeReturnsMariaDbDialect(): void {
        $this->assertInstanceOf(MariaDbDialect::class, Dialect::fromDsn('mariadb://host/db'));
    }

    public function testMysqlSchemeReturnsMariaDbDialect(): void {
        $this->assertInstanceOf(MariaDbDialect::class, Dialect::fromDsn('mysql://host/db'));
    }

    public function testPostgresSchemeReturnsPostgresDialect(): void {
        $this->assertInstanceOf(PostgresDialect::class, Dialect::fromDsn('postgres://host/db'));
        $this->assertInstanceOf(PostgresDialect::class, Dialect::fromDsn('pgsql://host/db'));
    }

    public function testVersionHintSelectsVersionedDialect(): void {
        $this->assertInstanceOf(MariaDb105Dialect::class, Dialect::fromDsn('mariadb://h/db?version=10.5'));
        $this->assertInstanceOf(MariaDb105Dialect::class, Dialect::fromDsn('mariadb://h/db?version=10.6'));
        $this->assertInstanceOf(Postgres15Dialect::class, Dialect::fromDsn('postgres://h/db?version=15'));
        $this->assertInstanceOf(Postgres15Dialect::class, Dialect::fromDsn('postgres://h/db?version=16.2'));
    }

    public function testOlderVersionFallsBackToBaseDialect(): void {
        $mariaDb = Dialect::fromDsn('mariadb://h/db?version=10.3');
        $this->assertInstanceOf(MariaDbDialect::class, $mariaDb);
        $this->assertNotInstanceOf(MariaDb105Dialect::class, $mariaDb);

        $postgres = Dialect::fromDsn('postgres://h/db?version=14');
        $this->assertInstanceOf(PostgresDialect::class, $postgres);
        $this->assertNotInstanceOf(Postgres15Dialect::class, $postgres);
    }

    public function testUnknownSchemeFallsBackToDefault(): void {
        $this->assertInstanceOf(DefaultDialect::class, Dialect::fromDsn('sqlite://file.db'));
        $this->assertInstanceOf(DefaultDialect::class, Dialect::fromDsn('not-a-dsn'));
    }
}
