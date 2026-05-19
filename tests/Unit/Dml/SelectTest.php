<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dml;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\NullsPlacement;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Dml\Select;

final class SelectTest extends TestCase {

    public function testEmptySelectRendersSelectStar(): void {
        $this->assertSame('SELECT *', (string) Select::create());
    }

    public function testSelectColumns(): void {
        $sql = (string) Select::create()->select('id', 'name', 'email');

        $this->assertSame('SELECT `id`, `name`, `email`', $sql);
    }

    public function testSelectPassesThroughExpressionArguments(): void {
        $sql = (string) Select::create()->select(Expression::count(), Expression::ref('country'));

        $this->assertSame('SELECT COUNT(*) AS `COUNT`, `country`', $sql);
    }

    public function testDistinct(): void {
        $sql = (string) Select::create()->distinct()->select('country');

        $this->assertSame('SELECT DISTINCT `country`', $sql);
    }

    public function testFromTable(): void {
        $sql = (string) Select::create()->from('users');

        $this->assertSame('SELECT * FROM `users`', $sql);
    }

    public function testFromTableWithAlias(): void {
        $sql = (string) Select::create()->select('u.id')->from('users', 'u');

        $this->assertSame('SELECT `u`.`id` FROM `users` AS `u`', $sql);
    }

    public function testFromSubqueryRequiresAlias(): void {
        $this->expectException(InvalidArgumentException::class);

        Select::create()->from(Select::create()->select('1'));
    }

    public function testFromSubqueryWithAlias(): void {
        $inner = Select::create()->select('id')->from('users');
        $outer = Select::create()->select('id')->from($inner, 't');

        $this->assertSame("SELECT `id` FROM ($inner) AS `t`", (string) $outer);
    }

    public function testInnerJoin(): void {
        $on  = Expression::binary('u.role_id', BinaryOperator::Equal, Expression::ref('r.id'));
        $sql = (string) Select::create()
            ->select('u.name', 'r.role')
            ->from('users', 'u')
            ->join('roles', 'r', $on);

        $this->assertSame(
            'SELECT `u`.`name`, `r`.`role` FROM `users` AS `u` INNER JOIN `roles` AS `r` ON (`u`.`role_id` = `r`.`id`)',
            $sql
        );
    }

    public function testLeftRightFullCrossHelpersEmitCorrectKeywords(): void {
        $on = Expression::binary('a.id', BinaryOperator::Equal, Expression::ref('b.id'));

        $left  = (string) Select::create()->from('a')->leftJoin('b', null, $on);
        $right = (string) Select::create()->from('a')->rightJoin('b', null, $on);
        $full  = (string) Select::create()->from('a')->fullJoin('b', null, $on);

        $this->assertStringContainsString('LEFT JOIN `b`',  $left);
        $this->assertStringContainsString('RIGHT JOIN `b`', $right);
        $this->assertStringContainsString('FULL JOIN `b`',  $full);
    }

    public function testJoinUsing(): void {
        $sql = (string) Select::create()
            ->from('users', 'u')
            ->joinUsing('roles', ['role_id']);

        $this->assertSame('SELECT * FROM `users` AS `u` INNER JOIN `roles` USING (`role_id`)', $sql);
    }

    public function testWhereAppendsWithAndWhenAlreadySet(): void {
        $sql = (string) Select::create()
            ->from('users')
            ->where(Expression::binary('a', BinaryOperator::Equal, 1))
            ->andWhere(Expression::binary('b', BinaryOperator::Equal, 2));

        $this->assertSame('SELECT * FROM `users` WHERE ((`a` = 1) AND (`b` = 2))', $sql);
    }

    public function testOrWhere(): void {
        $sql = (string) Select::create()
            ->from('users')
            ->where(Expression::binary('a', BinaryOperator::Equal, 1))
            ->orWhere(Expression::binary('b', BinaryOperator::Equal, 2));

        $this->assertSame('SELECT * FROM `users` WHERE ((`a` = 1) OR (`b` = 2))', $sql);
    }

    public function testGroupByAndHaving(): void {
        $sql = (string) Select::create()
            ->select('country', Expression::count())
            ->from('users')
            ->groupBy('country')
            ->having(Expression::binary(Expression::count(), BinaryOperator::GreaterThan, Expression::value(10)));

        $this->assertSame(
            'SELECT `country`, COUNT(*) AS `COUNT` FROM `users` GROUP BY `country` HAVING (COUNT(*) AS `COUNT` > 10)',
            $sql
        );
    }

    public function testOrderByMultipleEntries(): void {
        $sql = (string) Select::create()
            ->from('users')
            ->orderBy('country')
            ->orderBy('name', SortDirection::DESC, NullsPlacement::LAST);

        $this->assertSame(
            'SELECT * FROM `users` ORDER BY `country` ASC, `name` DESC NULLS LAST',
            $sql
        );
    }

    public function testLimitOffset(): void {
        $sql = (string) Select::create()->from('users')->limit(20)->offset(40);

        $this->assertSame('SELECT * FROM `users` LIMIT 20 OFFSET 40', $sql);
    }

    public function testLimitRejectsNegative(): void {
        $this->expectException(InvalidArgumentException::class);
        Select::create()->limit(-1);
    }

    public function testOffsetRejectsNegative(): void {
        $this->expectException(InvalidArgumentException::class);
        Select::create()->offset(-1);
    }

    public function testFullPipeline(): void {
        $sql = (string) Select::create()
            ->distinct()
            ->select('u.id', 'u.name')
            ->from('users', 'u')
            ->join('roles', 'r', Expression::binary('u.role_id', BinaryOperator::Equal, Expression::ref('r.id')))
            ->where(Expression::binary('u.active', BinaryOperator::Equal, 1))
            ->groupBy('u.id')
            ->orderBy('u.name')
            ->limit(10);

        $this->assertSame(
            'SELECT DISTINCT `u`.`id`, `u`.`name` FROM `users` AS `u` '
            . 'INNER JOIN `roles` AS `r` ON (`u`.`role_id` = `r`.`id`) '
            . 'WHERE (`u`.`active` = 1) GROUP BY `u`.`id` ORDER BY `u`.`name` ASC LIMIT 10',
            $sql
        );
    }
}
