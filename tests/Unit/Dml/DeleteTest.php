<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dml;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Dml\Delete;

final class DeleteTest extends TestCase {

    public function test_requires_target_table(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Delete::create();
    }

    public function test_delete_without_where(): void {
        $sql = (string) Delete::create()->from('users');

        $this->assertSame('DELETE FROM `users`', $sql);
    }

    public function test_delete_with_alias(): void {
        $sql = (string) Delete::create()->from('users', 'u');

        $this->assertSame('DELETE FROM `users` AS `u`', $sql);
    }

    public function test_delete_with_where(): void {
        $sql = (string) Delete::create()
            ->from('users')
            ->where(Expression::binary('active', BinaryOperator::Equal, 0));

        $this->assertSame('DELETE FROM `users` WHERE (`active` = 0)', $sql);
    }

    public function test_and_where_combines_with_and(): void {
        $sql = (string) Delete::create()
            ->from('users')
            ->where(Expression::binary('a', BinaryOperator::Equal, 1))
            ->andWhere(Expression::binary('b', BinaryOperator::Equal, 2));

        $this->assertSame(
            'DELETE FROM `users` WHERE ((`a` = 1) AND (`b` = 2))',
            $sql
        );
    }

    public function test_or_where_combines_with_or(): void {
        $sql = (string) Delete::create()
            ->from('users')
            ->where(Expression::binary('a', BinaryOperator::Equal, 1))
            ->orWhere(Expression::binary('b', BinaryOperator::Equal, 2));

        $this->assertSame(
            'DELETE FROM `users` WHERE ((`a` = 1) OR (`b` = 2))',
            $sql
        );
    }

    public function test_using_single_table(): void {
        $sql = (string) Delete::create()
            ->from('users', 'u')
            ->using('audit', 'a')
            ->where(Expression::binary('u.id', BinaryOperator::Equal, Expression::ref('a.user_id')));

        $this->assertSame(
            'DELETE FROM `users` AS `u` USING `audit` AS `a` WHERE (`u`.`id` = `a`.`user_id`)',
            $sql
        );
    }

    public function test_using_multiple_tables(): void {
        $sql = (string) Delete::create()
            ->from('t')
            ->using('a')
            ->using('b', 'b2');

        $this->assertStringContainsString('USING `a`, `b` AS `b2`', $sql);
    }

    public function test_order_by(): void {
        $sql = (string) Delete::create()->from('users')->orderBy('id', SortDirection::DESC);

        $this->assertSame('DELETE FROM `users` ORDER BY `id` DESC', $sql);
    }

    public function test_limit(): void {
        $sql = (string) Delete::create()->from('users')->limit(100);

        $this->assertSame('DELETE FROM `users` LIMIT 100', $sql);
    }

    public function test_limit_rejects_negative(): void {
        $this->expectException(InvalidArgumentException::class);
        Delete::create()->limit(-1);
    }

    public function test_returning_with_column_names(): void {
        $sql = (string) Delete::create()->from('users')->returning('id', 'email');

        $this->assertSame('DELETE FROM `users` RETURNING `id`, `email`', $sql);
    }

    public function test_returning_with_expressions(): void {
        $sql = (string) Delete::create()
            ->from('users')
            ->returning(Expression::ref('u.id'));

        $this->assertStringEndsWith('RETURNING `u`.`id`', $sql);
    }

    public function test_full_pipeline_clause_order(): void {
        $sql = (string) Delete::create()
            ->from('users', 'u')
            ->using('audit', 'a')
            ->where(Expression::binary('u.id', BinaryOperator::Equal, Expression::ref('a.user_id')))
            ->orderBy('u.id', SortDirection::DESC)
            ->limit(100)
            ->returning('u.id');

        $this->assertSame(
            'DELETE FROM `users` AS `u` USING `audit` AS `a` '
            . 'WHERE (`u`.`id` = `a`.`user_id`) ORDER BY `u`.`id` DESC LIMIT 100 RETURNING `u`.`id`',
            $sql
        );
    }
}
