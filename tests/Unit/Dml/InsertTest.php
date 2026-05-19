<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dml;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;

final class InsertTest extends TestCase {

    public function testRequiresTargetTable(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Insert::create()->values('x');
    }

    public function testRequiresValuesOrSelect(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Insert::create()->into('t');
    }

    public function testValuesWithoutColumns(): void {
        $sql = (string) Insert::create()->into('users')->values(1, 'Alice');

        $this->assertSame("INSERT INTO `users` VALUES (1, 'Alice')", $sql);
    }

    public function testValuesWithColumns(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id', 'name')
            ->values(1, 'Alice');

        $this->assertSame("INSERT INTO `users` (`id`, `name`) VALUES (1, 'Alice')", $sql);
    }

    public function testMultiRowValues(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id', 'name')
            ->values(1, 'Alice')
            ->values(2, 'Bob');

        $this->assertSame(
            "INSERT INTO `users` (`id`, `name`) VALUES (1, 'Alice'), (2, 'Bob')",
            $sql
        );
    }

    public function testValueCountMustMatchDeclaredColumns(): void {
        $this->expectException(InvalidArgumentException::class);
        Insert::create()->into('users')->columns('id', 'name')->values(1);
    }

    public function testSubsequentRowsMustMatchFirstRowArityWhenColumnsUndeclared(): void {
        $this->expectException(InvalidArgumentException::class);
        Insert::create()->into('users')->values(1, 'a')->values(1, 'a', 'b');
    }

    public function testExpressionsPassThroughUnquoted(): void {
        $sql = (string) Insert::create()
            ->into('events')
            ->columns('type', 'created_at')
            ->values('login', Expression::raw('NOW()'));

        $this->assertSame(
            "INSERT INTO `events` (`type`, `created_at`) VALUES ('login', NOW())",
            $sql
        );
    }

    public function testNullAndBooleanValues(): void {
        $sql = (string) Insert::create()
            ->into('t')
            ->values(null, true, false);

        $this->assertSame('INSERT INTO `t` VALUES (NULL, TRUE, FALSE)', $sql);
    }

    public function testInsertFromSelect(): void {
        $source = Select::create()->select('id', 'name')->from('users');
        $sql    = (string) Insert::create()
            ->into('users_archive')
            ->columns('id', 'name')
            ->select($source);

        $this->assertSame("INSERT INTO `users_archive` (`id`, `name`) $source", $sql);
    }

    public function testInsertFromSelectWithoutExplicitColumns(): void {
        $source = Select::create()->select('*')->from('users');
        $sql    = (string) Insert::create()->into('users_archive')->select($source);

        $this->assertSame("INSERT INTO `users_archive` $source", $sql);
    }

    public function testValuesAfterSelectThrows(): void {
        $this->expectException(InvalidArgumentException::class);
        Insert::create()
            ->into('t')
            ->select(Select::create()->select('1'))
            ->values(1);
    }

    public function testSelectAfterValuesThrows(): void {
        $this->expectException(InvalidArgumentException::class);
        Insert::create()
            ->into('t')
            ->values(1)
            ->select(Select::create()->select('1'));
    }

    public function testOnDuplicateKeyUpdateWithScalarValue(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id', 'email')
            ->values(1, 'a@b.com')
            ->onDuplicateKeyUpdate('email', 'fallback@b.com');

        $this->assertSame(
            "INSERT INTO `users` (`id`, `email`) VALUES (1, 'a@b.com') ON DUPLICATE KEY UPDATE `email` = 'fallback@b.com'",
            $sql
        );
    }

    public function testOnDuplicateKeyUpdateWithExpression(): void {
        $sql = (string) Insert::create()
            ->into('counters')
            ->columns('id', 'count')
            ->values(1, 1)
            ->onDuplicateKeyUpdate('count', Expression::raw('count + 1'));

        $this->assertStringEndsWith('ON DUPLICATE KEY UPDATE `count` = count + 1', $sql);
    }

    public function testOnDuplicateKeyUpdateMultipleAssignments(): void {
        $sql = (string) Insert::create()
            ->into('t')
            ->values(1, 'a')
            ->onDuplicateKeyUpdate('a', 'x')
            ->onDuplicateKeyUpdate('b', Expression::raw('NOW()'));

        $this->assertStringContainsString("ON DUPLICATE KEY UPDATE `a` = 'x', `b` = NOW()", $sql);
    }

    public function testOnDuplicateKeyUpdateOverridesSameColumn(): void {
        $sql = (string) Insert::create()
            ->into('t')
            ->values(1)
            ->onDuplicateKeyUpdate('a', 1)
            ->onDuplicateKeyUpdate('a', 2);

        $this->assertStringEndsWith('ON DUPLICATE KEY UPDATE `a` = 2', $sql);
    }

    public function testReturningWithColumnNames(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('email')
            ->values('a@b.com')
            ->returning('id', 'created_at');

        $this->assertSame(
            "INSERT INTO `users` (`email`) VALUES ('a@b.com') RETURNING `id`, `created_at`",
            $sql
        );
    }

    public function testReturningWithExpressions(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->values(1)
            ->returning(Expression::raw('id'), Expression::ref('email'));

        $this->assertStringEndsWith('RETURNING id, `email`', $sql);
    }

    public function testFullPipelineWithOnDuplicateAndReturning(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id', 'email')
            ->values(1, 'a@b.com')
            ->onDuplicateKeyUpdate('email', Expression::raw('VALUES(email)'))
            ->returning('id');

        $this->assertSame(
            "INSERT INTO `users` (`id`, `email`) VALUES (1, 'a@b.com') "
            . "ON DUPLICATE KEY UPDATE `email` = VALUES(email) RETURNING `id`",
            $sql
        );
    }
}
