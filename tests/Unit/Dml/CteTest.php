<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dml;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary as BinaryOperator;
use Rak200\SqlBuilder\Common\Expr as Expression;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Set;

final class CteTest extends TestCase {

    public function testSingleCteEmitsWithPrefix(): void {
        $totals = Select::create()
            ->select('user_id', Expression::count('*'))
            ->from('orders')
            ->groupBy('user_id');

        $sql = (string) Select::create()
            ->with('order_totals', $totals)
            ->select('user_id')
            ->from('order_totals');

        $this->assertSame(
            'WITH `order_totals` AS (SELECT `user_id`, COUNT(*) AS `COUNT` FROM `orders` GROUP BY `user_id`) SELECT `user_id` FROM `order_totals`',
            $sql
        );
    }

    public function testMultipleCtesAreCommaSeparated(): void {
        $a = Select::create()->select('id')->from('a');
        $b = Select::create()->select('id')->from('b');

        $sql = (string) Select::create()
            ->with('cte_a', $a)
            ->with('cte_b', $b)
            ->select('cte_a.id')
            ->from('cte_a');

        $this->assertStringContainsString(
            'WITH `cte_a` AS (SELECT `id` FROM `a`), `cte_b` AS (SELECT `id` FROM `b`) SELECT',
            $sql
        );
    }

    public function testCteWithExplicitColumnList(): void {
        $body = Select::create()->select('id', 'name')->from('users');

        $sql = (string) Select::create()
            ->with('renamed', $body, ['uid', 'uname'])
            ->select('*')
            ->from('renamed');

        $this->assertStringContainsString(
            'WITH `renamed` (`uid`, `uname`) AS (SELECT `id`, `name` FROM `users`)',
            $sql
        );
    }

    public function testWithRecursivePromotesKeyword(): void {
        $base = Select::create()->select('id')->from('nodes')->where(
            Expression::binary('parent_id', BinaryOperator::Is, Expression::raw('NULL'))
        );
        $step = Select::create()
            ->select('n.id')
            ->from('nodes', 'n')
            ->join('tree', 't', Expression::binary('n.parent_id', BinaryOperator::Eq, Expression::ref('t.id')));

        $body = Set::create($base)->union($step, all: true);

        $sql = (string) Select::create()
            ->withRecursive('tree', $body)
            ->select('*')
            ->from('tree');

        $this->assertStringStartsWith('WITH RECURSIVE `tree` AS (', $sql);
        $this->assertStringContainsString('UNION ALL', $sql);
        $this->assertStringEndsWith('SELECT * FROM `tree`', $sql);
    }

    public function testRecursiveFlagStaysSetEvenAfterAddingNonRecursiveCte(): void {
        $a = Select::create()->select('id')->from('a');
        $b = Set::create(Select::create()->select('1'))->union(Select::create()->select('2'));

        $sql = (string) Select::create()
            ->withRecursive('rec', $b)
            ->with('plain', $a)
            ->select('*')
            ->from('rec');

        $this->assertStringStartsWith('WITH RECURSIVE ', $sql);
    }
}
