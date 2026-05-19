<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dml;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;

final class InsertTest extends TestCase {

    public function test_requires_target_table(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Insert::create()->values('x');
    }

    public function test_requires_values_or_select(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Insert::create()->into('t');
    }

    public function test_values_without_columns(): void {
        $sql = (string) Insert::create()->into('users')->values(1, 'Alice');

        $this->assertSame("INSERT INTO `users` VALUES (1, 'Alice')", $sql);
    }

    public function test_values_with_columns(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id', 'name')
            ->values(1, 'Alice');

        $this->assertSame("INSERT INTO `users` (`id`, `name`) VALUES (1, 'Alice')", $sql);
    }

    public function test_multi_row_values(): void {
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

    public function test_value_count_must_match_declared_columns(): void {
        $this->expectException(InvalidArgumentException::class);
        Insert::create()->into('users')->columns('id', 'name')->values(1);
    }

    public function test_subsequent_rows_must_match_first_row_arity_when_columns_undeclared(): void {
        $this->expectException(InvalidArgumentException::class);
        Insert::create()->into('users')->values(1, 'a')->values(1, 'a', 'b');
    }

    public function test_expressions_pass_through_unquoted(): void {
        $sql = (string) Insert::create()
            ->into('events')
            ->columns('type', 'created_at')
            ->values('login', Expression::raw('NOW()'));

        $this->assertSame(
            "INSERT INTO `events` (`type`, `created_at`) VALUES ('login', NOW())",
            $sql
        );
    }

    public function test_null_and_boolean_values(): void {
        $sql = (string) Insert::create()
            ->into('t')
            ->values(null, true, false);

        $this->assertSame('INSERT INTO `t` VALUES (NULL, TRUE, FALSE)', $sql);
    }

    public function test_insert_from_select(): void {
        $source = Select::create()->select('id', 'name')->from('users');
        $sql    = (string) Insert::create()
            ->into('users_archive')
            ->columns('id', 'name')
            ->select($source);

        $this->assertSame("INSERT INTO `users_archive` (`id`, `name`) $source", $sql);
    }

    public function test_insert_from_select_without_explicit_columns(): void {
        $source = Select::create()->select('*')->from('users');
        $sql    = (string) Insert::create()->into('users_archive')->select($source);

        $this->assertSame("INSERT INTO `users_archive` $source", $sql);
    }

    public function test_values_after_select_throws(): void {
        $this->expectException(InvalidArgumentException::class);
        Insert::create()
            ->into('t')
            ->select(Select::create()->select('1'))
            ->values(1);
    }

    public function test_select_after_values_throws(): void {
        $this->expectException(InvalidArgumentException::class);
        Insert::create()
            ->into('t')
            ->values(1)
            ->select(Select::create()->select('1'));
    }

    public function test_on_duplicate_key_update_with_scalar_value(): void {
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

    public function test_on_duplicate_key_update_with_expression(): void {
        $sql = (string) Insert::create()
            ->into('counters')
            ->columns('id', 'count')
            ->values(1, 1)
            ->onDuplicateKeyUpdate('count', Expression::raw('count + 1'));

        $this->assertStringEndsWith('ON DUPLICATE KEY UPDATE `count` = count + 1', $sql);
    }

    public function test_on_duplicate_key_update_multiple_assignments(): void {
        $sql = (string) Insert::create()
            ->into('t')
            ->values(1, 'a')
            ->onDuplicateKeyUpdate('a', 'x')
            ->onDuplicateKeyUpdate('b', Expression::raw('NOW()'));

        $this->assertStringContainsString("ON DUPLICATE KEY UPDATE `a` = 'x', `b` = NOW()", $sql);
    }

    public function test_on_duplicate_key_update_overrides_same_column(): void {
        $sql = (string) Insert::create()
            ->into('t')
            ->values(1)
            ->onDuplicateKeyUpdate('a', 1)
            ->onDuplicateKeyUpdate('a', 2);

        $this->assertStringEndsWith('ON DUPLICATE KEY UPDATE `a` = 2', $sql);
    }

    public function test_returning_with_column_names(): void {
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

    public function test_returning_with_expressions(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->values(1)
            ->returning(Expression::raw('id'), Expression::ref('email'));

        $this->assertStringEndsWith('RETURNING id, `email`', $sql);
    }

    public function test_full_pipeline_with_on_duplicate_and_returning(): void {
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
