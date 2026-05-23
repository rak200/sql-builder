<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Reference\Column as ColumnReference;
use Rak200\SqlBuilder\Common\Enum\Operator\Unary as UnaryOperator;
use Rak200\SqlBuilder\Common\Expression\Unary as UnaryExpression;

final class UnaryExpressionTest extends TestCase {

    public function testRendersOperatorBeforeParenthesizedOperand(): void {
        $expr = new UnaryExpression(UnaryOperator::Not, new ColumnReference('active'));

        $this->assertSame('NOT (`active`)', (string) $expr);
    }

    public function testAppendsAliasWhenSet(): void {
        $expr = (new UnaryExpression(UnaryOperator::Minus, new ColumnReference('amount')))->as('neg');

        $this->assertSame('- (`amount`) AS `neg`', (string) $expr);
    }

    public function testDistinctModifier(): void {
        $expr = new UnaryExpression(UnaryOperator::Distinct, new ColumnReference('country'));

        $this->assertSame('DISTINCT (`country`)', (string) $expr);
    }
}
