<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dml;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dml\Select;

final class LateralJoinTest extends TestCase {

    public function testInnerLateralJoin(): void {
        $sub = Select::create()
            ->select('order_id')
            ->from('orders')
            ->where(Expr::binary('orders.user_id', Binary::Eq, Expr::ref('u.id')))
            ->limit(5);

        $sql = (string) Select::create()
            ->select('u.id', 'recent.order_id')
            ->from('users', 'u')
            ->lateralJoin($sub, 'recent', Expr::raw('TRUE'));

        $this->assertStringContainsString('JOIN LATERAL (SELECT', $sql);
        $this->assertStringContainsString('ON TRUE', $sql);
    }

    public function testLeftLateralJoinPostgres(): void {
        $sub = Select::create()
            ->select(Expr::raw('AVG(price)'))
            ->from('orders')
            ->where(Expr::binary('orders.user_id', Binary::Eq, Expr::ref('u.id')));

        $sql = Select::create()
            ->select('u.id')
            ->from('users', 'u')
            ->leftLateralJoin($sub, 'avg_orders', Expr::raw('TRUE'))
            ->toSql(new PostgresDialect());

        $this->assertStringContainsString('LEFT JOIN LATERAL', $sql);
        $this->assertStringContainsString('"avg_orders"', $sql);
    }

    public function testCrossLateralJoinForgetsOn(): void {
        $sub = Select::create()->select('x')->from('t');

        $sql = (string) Select::create()
            ->select('*')
            ->from('users', 'u')
            ->crossLateralJoin($sub, 'r');

        $this->assertStringContainsString('CROSS JOIN LATERAL (SELECT', $sql);
        $this->assertStringNotContainsString('ON', $sql);
    }

    public function testLateralRejectsNatural(): void {
        $sub = Select::create()->select('x')->from('t');
        $select = Select::create()->from('users', 'u');

        $select->joins[] = (new \Rak200\SqlBuilder\Common\Join(
            \Rak200\SqlBuilder\Common\Enum\JoinType::INNER,
            $sub,
            'r'
        ))->natural()->lateral();

        $this->expectException(InvalidArgumentException::class);
        (string) $select;
    }
}
