<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDb105Dialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\Postgres15Dialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;
use Rak200\SqlBuilder\Dml\Merge;

final class MergeDialectTest extends TestCase {

    private function sampleMerge(): Merge {
        return Merge::create()
            ->into('target', 't')
            ->using('source', 's')
            ->on(Expr::binary('t.id', Binary::Eq, Expr::ref('s.id')))
            ->whenMatchedDelete();
    }

    public function testDefaultDialectAcceptsMerge(): void {
        $sql = $this->sampleMerge()->toSql(new DefaultDialect());

        $this->assertStringStartsWith('MERGE INTO `target`', $sql);
    }

    public function testPostgres15AcceptsMerge(): void {
        $sql = $this->sampleMerge()->toSql(new Postgres15Dialect());

        $this->assertStringStartsWith('MERGE INTO "target"', $sql);
    }

    public function testPostgresBaseRejectsMerge(): void {
        $this->expectException(UnsupportedFeatureException::class);
        $this->sampleMerge()->toSql(new PostgresDialect());
    }

    public function testMariaDbRejectsMerge(): void {
        $this->expectException(UnsupportedFeatureException::class);
        $this->sampleMerge()->toSql(new MariaDbDialect());
    }

    public function testMariaDb105AlsoRejectsMerge(): void {
        $this->expectException(UnsupportedFeatureException::class);
        $this->sampleMerge()->toSql(new MariaDb105Dialect());
    }
}
