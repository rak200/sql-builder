<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Dml\Select;

final class GroupingExpressionTest extends TestCase {

    public function testRollup(): void {
        $this->assertSame('ROLLUP (`a`, `b`)', (string) Expr::rollup('a', 'b'));
    }

    public function testCube(): void {
        $this->assertSame('CUBE (`a`, `b`, `c`)', (string) Expr::cube('a', 'b', 'c'));
    }

    public function testGroupingSetsWithTuplesAndGrandTotal(): void {
        $sql = (string) Expr::groupingSets(['a', 'b'], ['c'], []);

        $this->assertSame('GROUPING SETS ((`a`, `b`), (`c`), ())', $sql);
    }

    public function testGroupingSetsAcceptsBareColumnAlongsideTuple(): void {
        $sql = (string) Expr::groupingSets('a', ['b', 'c']);

        $this->assertSame('GROUPING SETS (`a`, (`b`, `c`))', $sql);
    }

    public function testSelectGroupByRollup(): void {
        $sql = (string) Select::create()
            ->select(Expr::count('*'))
            ->from('sales')
            ->groupBy(Expr::rollup('region', 'product'));

        $this->assertSame(
            'SELECT COUNT(*) AS `COUNT` FROM `sales` GROUP BY ROLLUP (`region`, `product`)',
            $sql
        );
    }

    public function testSelectGroupByMixedBareAndGroupingSets(): void {
        $sql = (string) Select::create()
            ->select('region')
            ->from('sales')
            ->groupBy('region', Expr::groupingSets(['product'], []));

        $this->assertSame(
            'SELECT `region` FROM `sales` GROUP BY `region`, GROUPING SETS ((`product`), ())',
            $sql
        );
    }
}
