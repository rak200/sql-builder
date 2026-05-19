<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dml;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Dml\Update;

final class UpdateTest extends TestCase {

    public function test_requires_target_table(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Update::create()->set('a', 1);
    }

    public function test_requires_at_least_one_set(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Update::create()->table('t');
    }

    public function test_basic_update(): void {
        $sql = (string) Update::create()->table('users')->set('name', 'Alice');

        $this->assertSame("UPDATE `users` SET `name` = 'Alice'", $sql);
    }

    public function test_update_with_alias(): void {
        $sql = (string) Update::create()->table('users', 'u')->set('name', 'Alice');

        $this->assertSame("UPDATE `users` AS `u` SET `name` = 'Alice'", $sql);
    }

    public function test_multiple_sets(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('name', 'Alice')
            ->set('active', true);

        $this->assertSame("UPDATE `users` SET `name` = 'Alice', `active` = TRUE", $sql);
    }

    public function test_set_with_expression_value(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('updated_at', Expression::raw('NOW()'));

        $this->assertSame('UPDATE `users` SET `updated_at` = NOW()', $sql);
    }

    public function test_set_overrides_previous_value_for_same_column(): void {
        $sql = (string) Update::create()
            ->table('t')
            ->set('a', 1)
            ->set('a', 2);

        $this->assertSame('UPDATE `t` SET `a` = 2', $sql);
    }

    public function test_with_where(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('active', false)
            ->where(Expression::binary('id', BinaryOperator::Equal, 1));

        $this->assertSame('UPDATE `users` SET `active` = FALSE WHERE (`id` = 1)', $sql);
    }

    public function test_and_where_combines_with_and(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('active', false)
            ->where(Expression::binary('a', BinaryOperator::Equal, 1))
            ->andWhere(Expression::binary('b', BinaryOperator::Equal, 2));

        $this->assertSame(
            'UPDATE `users` SET `active` = FALSE WHERE ((`a` = 1) AND (`b` = 2))',
            $sql
        );
    }

    public function test_or_where_combines_with_or(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('active', false)
            ->where(Expression::binary('a', BinaryOperator::Equal, 1))
            ->orWhere(Expression::binary('b', BinaryOperator::Equal, 2));

        $this->assertSame(
            'UPDATE `users` SET `active` = FALSE WHERE ((`a` = 1) OR (`b` = 2))',
            $sql
        );
    }

    public function test_null_value(): void {
        $sql = (string) Update::create()->table('t')->set('deleted_at', null);

        $this->assertSame('UPDATE `t` SET `deleted_at` = NULL', $sql);
    }

    public function test_multi_table_from(): void {
        $sql = (string) Update::create()
            ->table('users', 'u')
            ->set('name', Expression::ref('a.new_name'))
            ->from('audit', 'a')
            ->where(Expression::binary('u.id', BinaryOperator::Equal, Expression::ref('a.user_id')));

        $this->assertSame(
            'UPDATE `users` AS `u` SET `name` = `a`.`new_name` FROM `audit` AS `a` '
            . 'WHERE (`u`.`id` = `a`.`user_id`)',
            $sql
        );
    }

    public function test_multiple_from_tables(): void {
        $sql = (string) Update::create()
            ->table('t')
            ->set('x', 1)
            ->from('a')
            ->from('b', 'b2');

        $this->assertStringContainsString('FROM `a`, `b` AS `b2`', $sql);
    }

    public function test_order_by(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('active', false)
            ->orderBy('id', SortDirection::DESC);

        $this->assertSame('UPDATE `users` SET `active` = FALSE ORDER BY `id` DESC', $sql);
    }

    public function test_limit(): void {
        $sql = (string) Update::create()->table('users')->set('x', 1)->limit(10);

        $this->assertSame('UPDATE `users` SET `x` = 1 LIMIT 10', $sql);
    }

    public function test_limit_rejects_negative(): void {
        $this->expectException(InvalidArgumentException::class);
        Update::create()->limit(-1);
    }

    public function test_returning_with_column_names(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('x', 1)
            ->returning('id', 'x');

        $this->assertSame('UPDATE `users` SET `x` = 1 RETURNING `id`, `x`', $sql);
    }

    public function test_returning_with_expressions(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('x', 1)
            ->returning(Expression::ref('u.id'));

        $this->assertStringEndsWith('RETURNING `u`.`id`', $sql);
    }

    public function test_full_pipeline_clause_order(): void {
        $sql = (string) Update::create()
            ->table('users', 'u')
            ->set('name', Expression::ref('a.name'))
            ->from('audit', 'a')
            ->where(Expression::binary('u.id', BinaryOperator::Equal, Expression::ref('a.user_id')))
            ->orderBy('u.id')
            ->limit(50)
            ->returning('u.id');

        $this->assertSame(
            'UPDATE `users` AS `u` SET `name` = `a`.`name` FROM `audit` AS `a` '
            . 'WHERE (`u`.`id` = `a`.`user_id`) ORDER BY `u`.`id` ASC LIMIT 50 RETURNING `u`.`id`',
            $sql
        );
    }
}
