<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\ColumnExpression;
use Rak200\SqlBuilder\Common\ColumnReference;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\UnaryOperator;
use Rak200\SqlBuilder\Common\ExistsExpression;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\FunctionExpression;
use Rak200\SqlBuilder\Common\RawExpression;
use Rak200\SqlBuilder\Common\SimpleIdentifier;
use Rak200\SqlBuilder\Common\SubqueryExpression;
use Rak200\SqlBuilder\Common\ValueExpression;
use Rak200\SqlBuilder\Dml\Select;

final class ExpressionTest extends TestCase {

    public static function identifierProvider(): array {
        return [
            'simple'        => ['users', '`users`'],
            'qualified'     => ['u.id', '`u`.`id`'],
            'star'          => ['*', '*'],
            'qualified star'=> ['u.*', '`u`.*'],
        ];
    }

    #[DataProvider('identifierProvider')]
    public function testQuoteIdentifier(string $input, string $expected): void {
        $this->assertSame($expected, Expression::quoteIdentifier($input));
    }

    public static function valueProvider(): array {
        return [
            'null'      => [null, 'NULL'],
            'int'       => [42, '42'],
            'negative'  => [-7, '-7'],
            'float'     => [1.5, '1.5'],
            'true'      => [true, 'TRUE'],
            'false'     => [false, 'FALSE'],
            'string'    => ['hello', "'hello'"],
            'apostrophe'=> ["it's", "'it''s'"],
            'backslash' => ['a\\b', "'a\\\\b'"],
        ];
    }

    #[DataProvider('valueProvider')]
    public function testQuoteValue(mixed $input, string $expected): void {
        $this->assertSame($expected, Expression::quoteValue($input));
    }

    public function testRawReturnsRawExpression(): void {
        $raw = Expression::raw('NOW()');

        $this->assertInstanceOf(RawExpression::class, $raw);
        $this->assertSame('NOW()', (string) $raw);
    }

    public function testColumnReturnsColumnExpressionWithAlias(): void {
        $col = Expression::column('users.id', 'uid');

        $this->assertInstanceOf(ColumnExpression::class, $col);
        $this->assertSame('`users`.`id` AS `uid`', (string) $col);
    }

    public function testRefReturnsColumnReferenceWithoutAlias(): void {
        $ref = Expression::ref('u.name');

        $this->assertInstanceOf(ColumnReference::class, $ref);
        $this->assertSame('`u`.`name`', (string) $ref);
    }

    public function testIdentifierReturnsSimpleIdentifier(): void {
        $id = Expression::identifier('id');

        $this->assertInstanceOf(SimpleIdentifier::class, $id);
        $this->assertSame('`id`', (string) $id);
    }

    public function testIdentifierRejectsQualifiedNames(): void {
        $this->expectException(InvalidArgumentException::class);

        Expression::identifier('u.id');
    }

    public function testValueReturnsValueExpression(): void {
        $value = Expression::value('foo');

        $this->assertInstanceOf(ValueExpression::class, $value);
        $this->assertSame("'foo'", (string) $value);
    }

    public function testFuncUppercasesNameAndNormalizesArgs(): void {
        $fn = Expression::func('coalesce', 'name', null);

        $this->assertInstanceOf(FunctionExpression::class, $fn);
        $this->assertSame("COALESCE(`name`, NULL)", (string) $fn);
    }

    public function testSubqueryWrapsASelect(): void {
        $select  = Select::create()->select('1');
        $subquery = Expression::subquery($select, 's');

        $this->assertInstanceOf(SubqueryExpression::class, $subquery);
        $this->assertSame('(' . $select . ') AS `s`', (string) $subquery);
    }

    public function testBinaryNormalizesStringToColumnReference(): void {
        $expr = Expression::binary('age', BinaryOperator::Ge, 18);

        $this->assertSame('(`age` >= 18)', (string) $expr);
    }

    public function testUnaryNormalizesOperand(): void {
        $expr = Expression::unary(UnaryOperator::Not, 'active');

        $this->assertSame('NOT (`active`)', (string) $expr);
    }

    public function testAndCombinesMultipleExpressionsLeftToRight(): void {
        $a = Expression::binary('x', BinaryOperator::Eq, 1);
        $b = Expression::binary('y', BinaryOperator::Eq, 2);
        $c = Expression::binary('z', BinaryOperator::Eq, 3);

        $this->assertSame('(((`x` = 1) AND (`y` = 2)) AND (`z` = 3))', (string) Expression::and($a, $b, $c));
    }

    public function testOrCombinesMultipleExpressions(): void {
        $a = Expression::binary('x', BinaryOperator::Eq, 1);
        $b = Expression::binary('y', BinaryOperator::Eq, 2);

        $this->assertSame('((`x` = 1) OR (`y` = 2))', (string) Expression::or($a, $b));
    }

    public function testAndRequiresAtLeastOneExpression(): void {
        $this->expectException(InvalidArgumentException::class);

        Expression::and();
    }

    public function testOrRequiresAtLeastOneExpression(): void {
        $this->expectException(InvalidArgumentException::class);

        Expression::or();
    }

    public function testExistsWrapsSubquery(): void {
        $sub = Select::create()->select('1')->from('users');
        $expr = Expression::exists($sub);

        $this->assertInstanceOf(ExistsExpression::class, $expr);
        $this->assertSame("EXISTS (($sub))", (string) $expr);
    }

    public function testNotNegatesANormalizedExpression(): void {
        $expr = Expression::not('active');

        $this->assertSame('NOT (`active`)', (string) $expr);
    }

    public function testCountDefaultsToStarAndCountAlias(): void {
        $this->assertSame('COUNT(*) AS `COUNT`', (string) Expression::count());
    }

    public function testCountAcceptsCustomAlias(): void {
        $this->assertSame('COUNT(`id`) AS `total`', (string) Expression::count('id', 'total'));
    }

    public function testSumAvgMinMaxRenderAsAggregates(): void {
        $this->assertSame('SUM(`amount`) AS `SUM`',  (string) Expression::sum('amount'));
        $this->assertSame('AVG(`amount`) AS `AVG`',  (string) Expression::avg('amount'));
        $this->assertSame('MIN(`amount`) AS `MIN`',  (string) Expression::min('amount'));
        $this->assertSame('MAX(`amount`) AS `MAX`',  (string) Expression::max('amount'));
    }

    public function testAddChainsOperandsLeftToRight(): void {
        $this->assertSame('((`a` + `b`) + 3)', (string) Expression::add('a', 'b', 3));
    }

    public function testSubChainsOperandsLeftToRight(): void {
        $this->assertSame('((`total` - `discount`) - 5)', (string) Expression::sub('total', 'discount', 5));
    }

    public function testMulChainsOperandsLeftToRight(): void {
        $this->assertSame('((`price` * `qty`) * 1.1)', (string) Expression::mul('price', 'qty', 1.1));
    }

    public function testDivChainsOperandsLeftToRight(): void {
        $this->assertSame('((`amount` / `count`) / 2)', (string) Expression::div('amount', 'count', 2));
    }

    public function testModChainsOperandsLeftToRight(): void {
        $this->assertSame('((`n` % 7) % 3)', (string) Expression::mod('n', 7, 3));
    }

    public function testArithmeticFactoriesNormalizeMixedOperands(): void {
        $expr = Expression::add('subtotal', Expression::value(2.5), Expression::mul('qty', 'unit_price'));

        $this->assertSame('((`subtotal` + 2.5) + (`qty` * `unit_price`))', (string) $expr);
    }

    public function testArithmeticFactoriesPreserveNestedPrecedenceViaParens(): void {
        $expr = Expression::mul(Expression::add('a', 'b'), 'c');

        $this->assertSame('((`a` + `b`) * `c`)', (string) $expr);
    }

    public function testAddWithSingleOperandReturnsNormalized(): void {
        $this->assertSame('`a`', (string) Expression::add('a'));
    }

    public function testAddRequiresAtLeastOneOperand(): void {
        $this->expectException(InvalidArgumentException::class);

        Expression::add();
    }
}
