<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Expr as Expression;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDb105Dialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;
use Rak200\SqlBuilder\Dml\Delete;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Update;

final class MariaDbDialectTest extends TestCase {

    public function testInsertWithReturningRejectedOnBaseDialect(): void {
        $insert = Insert::create()
            ->into('users')
            ->columns('id')
            ->values(1)
            ->returning('id');

        $this->expectException(UnsupportedFeatureException::class);
        $insert->toSql(new MariaDbDialect());
    }

    public function testInsertWithReturningAcceptedOnMariaDb105(): void {
        $sql = Insert::create()
            ->into('users')
            ->columns('id')
            ->values(1)
            ->returning('id')
            ->toSql(new MariaDb105Dialect());

        $this->assertStringContainsString('RETURNING', $sql);
    }

    public function testUpdateWithReturningAcceptedOnMariaDb105(): void {
        $sql = Update::create()
            ->table('users')
            ->set('name', 'x')
            ->returning('id')
            ->toSql(new MariaDb105Dialect());

        $this->assertStringContainsString('RETURNING', $sql);
    }

    public function testDeleteWithReturningAcceptedOnMariaDb105(): void {
        $sql = Delete::create()
            ->from('users')
            ->returning('id')
            ->toSql(new MariaDb105Dialect());

        $this->assertStringContainsString('RETURNING', $sql);
    }

    public function testUpdateFromRejected(): void {
        $update = Update::create()
            ->table('users', 'u')
            ->set('name', 'x')
            ->from('audit', 'a');

        $this->expectException(UnsupportedFeatureException::class);
        $update->toSql(new MariaDbDialect());
    }

    public function testDeleteUsingRejected(): void {
        $delete = Delete::create()
            ->from('users', 'u')
            ->using('audit', 'a');

        $this->expectException(UnsupportedFeatureException::class);
        $delete->toSql(new MariaDbDialect());
    }

    public function testInsertOnDuplicateKeyUpdateStillSupported(): void {
        $sql = Insert::create()
            ->into('users')
            ->columns('id', 'name')
            ->values(1, 'a')
            ->onDuplicateKeyUpdate('name', Expression::raw('VALUES(name)'))
            ->toSql(new MariaDbDialect());

        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
    }
}
