<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\ColumnReference;
use Rak200\SqlBuilder\Common\Enum\UnaryOperator;
use Rak200\SqlBuilder\Common\UnaryExpression;

final class UnaryExpressionTest extends TestCase {

    public function test_renders_operator_before_parenthesized_operand(): void {
        $expr = new UnaryExpression(UnaryOperator::Not, new ColumnReference('active'));

        $this->assertSame('NOT (`active`)', (string) $expr);
    }

    public function test_appends_alias_when_set(): void {
        $expr = (new UnaryExpression(UnaryOperator::Minus, new ColumnReference('amount')))->as('neg');

        $this->assertSame('- (`amount`) AS `neg`', (string) $expr);
    }

    public function test_distinct_modifier(): void {
        $expr = new UnaryExpression(UnaryOperator::Distinct, new ColumnReference('country'));

        $this->assertSame('DISTINCT (`country`)', (string) $expr);
    }
}
