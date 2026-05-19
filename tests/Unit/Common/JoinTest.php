<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\JoinType;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\Join;

final class JoinTest extends TestCase {

    public function testInnerJoinWithOnCondition(): void {
        $on   = Expression::binary('u.role_id', BinaryOperator::Equal, Expression::ref('r.id'));
        $join = new Join(JoinType::INNER, 'roles', 'r', $on);

        $this->assertSame('INNER JOIN `roles` AS `r` ON (`u`.`role_id` = `r`.`id`)', (string) $join);
    }

    public function testLeftJoinWithOnCondition(): void {
        $on   = Expression::binary('a.id', BinaryOperator::Equal, Expression::ref('b.id'));
        $join = (new Join(JoinType::LEFT, 'b'))->on($on);

        $this->assertSame('LEFT JOIN `b` ON (`a`.`id` = `b`.`id`)', (string) $join);
    }

    public function testNaturalLeftJoin(): void {
        $join = (new Join(JoinType::LEFT, 'b'))->natural();

        $this->assertSame('NATURAL LEFT JOIN `b`', (string) $join);
    }

    public function testCrossJoinWithoutOnOrUsing(): void {
        $join = new Join(JoinType::CROSS, 'b');

        $this->assertSame('CROSS JOIN `b`', (string) $join);
    }

    public function testUsingClause(): void {
        $join = (new Join(JoinType::INNER, 'b'))->using('id', 'tenant');

        $this->assertSame('INNER JOIN `b` USING (`id`, `tenant`)', (string) $join);
    }

    public function testNaturalJoinForbidsOnCondition(): void {
        $on   = Expression::binary('a.id', BinaryOperator::Equal, Expression::ref('b.id'));
        $join = (new Join(JoinType::INNER, 'b'))->natural()->on($on);

        $this->expectException(InvalidArgumentException::class);
        (string) $join;
    }

    public function testNaturalJoinForbidsUsing(): void {
        $join = (new Join(JoinType::INNER, 'b'))->natural()->using('id');

        $this->expectException(InvalidArgumentException::class);
        (string) $join;
    }

    public function testOnAndUsingAreMutuallyExclusive(): void {
        $on = Expression::binary('a.id', BinaryOperator::Equal, Expression::ref('b.id'));
        $join = (new Join(JoinType::INNER, 'b'))->on($on)->using('id');

        $this->expectException(InvalidArgumentException::class);
        (string) $join;
    }

    public function testCrossJoinRejectsOnCondition(): void {
        $on   = Expression::binary('a.id', BinaryOperator::Equal, Expression::ref('b.id'));
        $join = (new Join(JoinType::CROSS, 'b'))->on($on);

        $this->expectException(InvalidArgumentException::class);
        (string) $join;
    }
}
