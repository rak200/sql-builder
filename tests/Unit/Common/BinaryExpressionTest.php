<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\BinaryExpression;
use Rak200\SqlBuilder\Common\ColumnReference;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\ValueExpression;

final class BinaryExpressionTest extends TestCase {

    public function testRendersOperatorBetweenOperandsWithOuterParens(): void {
        $expr = new BinaryExpression(
            new ColumnReference('age'),
            BinaryOperator::Gt,
            new ValueExpression(18)
        );

        $this->assertSame('(`age` > 18)', (string) $expr);
    }

    public function testAppendsAliasWhenSet(): void {
        $expr = (new BinaryExpression(
            new ColumnReference('a'),
            BinaryOperator::Eq,
            new ValueExpression(1)
        ))->as('match');

        $this->assertSame('(`a` = 1) AS `match`', (string) $expr);
    }

    public function testSupportsLogicalOperators(): void {
        $left  = Expression::binary('x', BinaryOperator::Eq, 1);
        $right = Expression::binary('y', BinaryOperator::Eq, 2);
        $expr  = new BinaryExpression($left, BinaryOperator::Or, $right);

        $this->assertSame('((`x` = 1) OR (`y` = 2))', (string) $expr);
    }

    public function testSupportsIsNull(): void {
        $expr = Expression::binary('deleted_at', BinaryOperator::Is, null);

        $this->assertSame('(`deleted_at` IS NULL)', (string) $expr);
    }

    public function testSupportsLikeWithLiteralPattern(): void {
        $expr = Expression::binary('name', BinaryOperator::Like, Expression::value('A%'));

        $this->assertSame("(`name` LIKE 'A%')", (string) $expr);
    }

    public function testStringRightOperandIsNormalizedAsColumnReference(): void {
        $expr = Expression::binary('u.name', BinaryOperator::Eq, 'r.name');

        $this->assertSame('(`u`.`name` = `r`.`name`)', (string) $expr);
    }
}
