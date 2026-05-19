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

    public function test_inner_join_with_on_condition(): void {
        $on   = Expression::binary('u.role_id', BinaryOperator::Equal, Expression::ref('r.id'));
        $join = new Join(JoinType::INNER, 'roles', 'r', $on);

        $this->assertSame('INNER JOIN `roles` AS `r` ON (`u`.`role_id` = `r`.`id`)', (string) $join);
    }

    public function test_left_join_with_on_condition(): void {
        $on   = Expression::binary('a.id', BinaryOperator::Equal, Expression::ref('b.id'));
        $join = (new Join(JoinType::LEFT, 'b'))->on($on);

        $this->assertSame('LEFT JOIN `b` ON (`a`.`id` = `b`.`id`)', (string) $join);
    }

    public function test_natural_left_join(): void {
        $join = (new Join(JoinType::LEFT, 'b'))->natural();

        $this->assertSame('NATURAL LEFT JOIN `b`', (string) $join);
    }

    public function test_cross_join_without_on_or_using(): void {
        $join = new Join(JoinType::CROSS, 'b');

        $this->assertSame('CROSS JOIN `b`', (string) $join);
    }

    public function test_using_clause(): void {
        $join = (new Join(JoinType::INNER, 'b'))->using('id', 'tenant');

        $this->assertSame('INNER JOIN `b` USING (`id`, `tenant`)', (string) $join);
    }

    public function test_natural_join_forbids_on_condition(): void {
        $on   = Expression::binary('a.id', BinaryOperator::Equal, Expression::ref('b.id'));
        $join = (new Join(JoinType::INNER, 'b'))->natural()->on($on);

        $this->expectException(InvalidArgumentException::class);
        (string) $join;
    }

    public function test_natural_join_forbids_using(): void {
        $join = (new Join(JoinType::INNER, 'b'))->natural()->using('id');

        $this->expectException(InvalidArgumentException::class);
        (string) $join;
    }

    public function test_on_and_using_are_mutually_exclusive(): void {
        $on = Expression::binary('a.id', BinaryOperator::Equal, Expression::ref('b.id'));
        $join = (new Join(JoinType::INNER, 'b'))->on($on)->using('id');

        $this->expectException(InvalidArgumentException::class);
        (string) $join;
    }

    public function test_cross_join_rejects_on_condition(): void {
        $on   = Expression::binary('a.id', BinaryOperator::Equal, Expression::ref('b.id'));
        $join = (new Join(JoinType::CROSS, 'b'))->on($on);

        $this->expectException(InvalidArgumentException::class);
        (string) $join;
    }
}
