<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dml;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDb105Dialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;
use Rak200\SqlBuilder\Dml\Insert;

final class OnConflictTest extends TestCase {

    public function testDoUpdateOnDefaultDialect(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id', 'email')
            ->values(1, 'a@example.com')
            ->onConflict('id')
            ->doUpdate(['email' => Expr::raw('EXCLUDED.email')]);

        $this->assertSame(
            'INSERT INTO `users` (`id`, `email`) VALUES (1, \'a@example.com\') '
            . 'ON CONFLICT (`id`) DO UPDATE SET `email` = EXCLUDED.email',
            $sql
        );
    }

    public function testDoUpdateMultiColumnTarget(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('a', 'b')
            ->values(1, 2)
            ->onConflict(['a', 'b'])
            ->doUpdate(['b' => 3]);

        $this->assertStringContainsString('ON CONFLICT (`a`, `b`) DO UPDATE SET `b` = 3', $sql);
    }

    public function testDoNothing(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id')
            ->values(1)
            ->onConflict('id')
            ->doNothing();

        $this->assertStringContainsString('ON CONFLICT (`id`) DO NOTHING', $sql);
    }

    public function testEmptyConflictTargetOmitsParens(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id')
            ->values(1)
            ->onConflict()
            ->doNothing();

        $this->assertStringContainsString('ON CONFLICT DO NOTHING', $sql);
        $this->assertStringNotContainsString('ON CONFLICT (', $sql);
    }

    public function testOnConflictWhereOnUpdate(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id', 'count')
            ->values(1, 1)
            ->onConflict('id')
            ->doUpdate(['count' => Expr::raw('users.count + 1')])
            ->onConflictWhere(Expr::binary('users.locked', Binary::Eq, false));

        $this->assertStringContainsString('DO UPDATE SET `count` = users.count + 1 WHERE', $sql);
    }

    public function testPostgresEmitsOnConflict(): void {
        $sql = Insert::create()
            ->into('users')
            ->columns('id', 'email')
            ->values(1, 'a@example.com')
            ->onConflict('id')
            ->doUpdate(['email' => Expr::raw('EXCLUDED.email')])
            ->toSql(new PostgresDialect());

        $this->assertStringContainsString('ON CONFLICT ("id") DO UPDATE SET "email" = EXCLUDED.email', $sql);
    }

    public function testMariaDbTranslatesToOnDuplicateKeyUpdate(): void {
        $sql = Insert::create()
            ->into('users')
            ->columns('id', 'email')
            ->values(1, 'a@example.com')
            ->onConflict('id')
            ->doUpdate(['email' => Expr::raw('VALUES(email)')])
            ->toSql(new MariaDbDialect());

        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE `email` = VALUES(email)', $sql);
        $this->assertStringNotContainsString('ON CONFLICT', $sql);
    }

    public function testMariaDb105AlsoTranslatesOnConflict(): void {
        $sql = Insert::create()
            ->into('users')
            ->columns('id', 'email')
            ->values(1, 'a@example.com')
            ->onConflict('id')
            ->doUpdate(['email' => Expr::raw('VALUES(email)')])
            ->returning('id')
            ->toSql(new MariaDb105Dialect());

        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE `email` = VALUES(email)', $sql);
        $this->assertStringContainsString('RETURNING', $sql);
    }

    public function testMariaDbRejectsDoNothing(): void {
        $insert = Insert::create()
            ->into('users')->columns('id')->values(1)
            ->onConflict('id')->doNothing();

        $this->expectException(UnsupportedFeatureException::class);
        $insert->toSql(new MariaDbDialect());
    }

    public function testMariaDbRejectsConflictWhere(): void {
        $insert = Insert::create()
            ->into('users')->columns('id', 'c')->values(1, 1)
            ->onConflict('id')->doUpdate(['c' => 2])
            ->onConflictWhere(Expr::raw('1=1'));

        $this->expectException(UnsupportedFeatureException::class);
        $insert->toSql(new MariaDbDialect());
    }

    public function testDoUpdateBeforeOnConflictRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        Insert::create()->doUpdate(['a' => 1]);
    }

    public function testDoNothingBeforeOnConflictRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        Insert::create()->doNothing();
    }

    public function testOnConflictWhereOutsideDoUpdateRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        Insert::create()->onConflict('id')->doNothing()->onConflictWhere(Expr::raw('1=1'));
    }

    public function testMixingOnConflictAndOnDuplicateKeyUpdateRejected(): void {
        $insert = Insert::create()
            ->into('users')->columns('id')->values(1)
            ->onConflict('id')->doNothing()
            ->onDuplicateKeyUpdate('id', 1);

        $this->expectException(InvalidArgumentException::class);
        (string) $insert;
    }
}
