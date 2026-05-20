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

    public function testRequiresTargetTable(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Update::create()->set('a', 1);
    }

    public function testRequiresAtLeastOneSet(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Update::create()->table('t');
    }

    public function testBasicUpdate(): void {
        $sql = (string) Update::create()->table('users')->set('name', 'Alice');

        $this->assertSame("UPDATE `users` SET `name` = 'Alice'", $sql);
    }

    public function testUpdateWithAlias(): void {
        $sql = (string) Update::create()->table('users', 'u')->set('name', 'Alice');

        $this->assertSame("UPDATE `users` AS `u` SET `name` = 'Alice'", $sql);
    }

    public function testMultipleSets(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('name', 'Alice')
            ->set('active', true);

        $this->assertSame("UPDATE `users` SET `name` = 'Alice', `active` = TRUE", $sql);
    }

    public function testSetWithExpressionValue(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('updated_at', Expression::raw('NOW()'));

        $this->assertSame('UPDATE `users` SET `updated_at` = NOW()', $sql);
    }

    public function testSetOverridesPreviousValueForSameColumn(): void {
        $sql = (string) Update::create()
            ->table('t')
            ->set('a', 1)
            ->set('a', 2);

        $this->assertSame('UPDATE `t` SET `a` = 2', $sql);
    }

    public function testWithWhere(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('active', false)
            ->where(Expression::binary('id', BinaryOperator::Eq, 1));

        $this->assertSame('UPDATE `users` SET `active` = FALSE WHERE (`id` = 1)', $sql);
    }

    public function testAndWhereCombinesWithAnd(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('active', false)
            ->where(Expression::binary('a', BinaryOperator::Eq, 1))
            ->andWhere(Expression::binary('b', BinaryOperator::Eq, 2));

        $this->assertSame(
            'UPDATE `users` SET `active` = FALSE WHERE ((`a` = 1) AND (`b` = 2))',
            $sql
        );
    }

    public function testOrWhereCombinesWithOr(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('active', false)
            ->where(Expression::binary('a', BinaryOperator::Eq, 1))
            ->orWhere(Expression::binary('b', BinaryOperator::Eq, 2));

        $this->assertSame(
            'UPDATE `users` SET `active` = FALSE WHERE ((`a` = 1) OR (`b` = 2))',
            $sql
        );
    }

    public function testNullValue(): void {
        $sql = (string) Update::create()->table('t')->set('deleted_at', null);

        $this->assertSame('UPDATE `t` SET `deleted_at` = NULL', $sql);
    }

    public function testMultiTableFrom(): void {
        $sql = (string) Update::create()
            ->table('users', 'u')
            ->set('name', Expression::ref('a.new_name'))
            ->from('audit', 'a')
            ->where(Expression::binary('u.id', BinaryOperator::Eq, Expression::ref('a.user_id')));

        $this->assertSame(
            'UPDATE `users` AS `u` SET `name` = `a`.`new_name` FROM `audit` AS `a` '
            . 'WHERE (`u`.`id` = `a`.`user_id`)',
            $sql
        );
    }

    public function testMultipleFromTables(): void {
        $sql = (string) Update::create()
            ->table('t')
            ->set('x', 1)
            ->from('a')
            ->from('b', 'b2');

        $this->assertStringContainsString('FROM `a`, `b` AS `b2`', $sql);
    }

    public function testOrderBy(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('active', false)
            ->orderBy('id', SortDirection::DESC);

        $this->assertSame('UPDATE `users` SET `active` = FALSE ORDER BY `id` DESC', $sql);
    }

    public function testLimit(): void {
        $sql = (string) Update::create()->table('users')->set('x', 1)->limit(10);

        $this->assertSame('UPDATE `users` SET `x` = 1 LIMIT 10', $sql);
    }

    public function testLimitRejectsNegative(): void {
        $this->expectException(InvalidArgumentException::class);
        Update::create()->limit(-1);
    }

    public function testReturningWithColumnNames(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('x', 1)
            ->returning('id', 'x');

        $this->assertSame('UPDATE `users` SET `x` = 1 RETURNING `id`, `x`', $sql);
    }

    public function testReturningWithExpressions(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('x', 1)
            ->returning(Expression::ref('u.id'));

        $this->assertStringEndsWith('RETURNING `u`.`id`', $sql);
    }

    public function testFullPipelineClauseOrder(): void {
        $sql = (string) Update::create()
            ->table('users', 'u')
            ->set('name', Expression::ref('a.name'))
            ->from('audit', 'a')
            ->where(Expression::binary('u.id', BinaryOperator::Eq, Expression::ref('a.user_id')))
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
