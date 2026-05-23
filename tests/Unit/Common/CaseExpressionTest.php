<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary as BinaryOperator;
use Rak200\SqlBuilder\Common\Expr as Expression;

final class CaseExpressionTest extends TestCase {

    public function testSearchedCaseRendersWhenThenElseEnd(): void {
        $expr = Expression::case()
            ->when(Expression::binary('amount', BinaryOperator::Gt, 100), Expression::val('high'))
            ->when(Expression::binary('amount', BinaryOperator::Gt, 10), Expression::val('medium'))
            ->else(Expression::val('low'));

        $this->assertSame(
            "CASE WHEN (`amount` > 100) THEN 'high' WHEN (`amount` > 10) THEN 'medium' ELSE 'low' END",
            (string) $expr
        );
    }

    public function testSimpleCaseWrapsScalarComparisonsAsValues(): void {
        $expr = Expression::case('status')
            ->when('active', 1)
            ->when('inactive', 0)
            ->else(-1);

        $this->assertSame(
            "CASE `status` WHEN 'active' THEN 1 WHEN 'inactive' THEN 0 ELSE -1 END",
            (string) $expr
        );
    }

    public function testSimpleCaseAcceptsExpressionComparison(): void {
        $expr = Expression::case('status')
            ->when(Expression::ref('other.status'), 1)
            ->else(0);

        $this->assertSame(
            'CASE `status` WHEN `other`.`status` THEN 1 ELSE 0 END',
            (string) $expr
        );
    }

    public function testCaseAcceptsAlias(): void {
        $expr = Expression::case()
            ->when(Expression::binary('active', BinaryOperator::Eq, 1), Expression::val('Y'))
            ->else(Expression::val('N'))
            ->as('label');

        $this->assertSame(
            "CASE WHEN (`active` = 1) THEN 'Y' ELSE 'N' END AS `label`",
            (string) $expr
        );
    }

    public function testEmptyCaseThrows(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Expression::case();
    }

    public function testSearchedCaseRejectsScalarCondition(): void {
        $this->expectException(InvalidArgumentException::class);
        Expression::case()->when('not an expression', 1);
    }
}
