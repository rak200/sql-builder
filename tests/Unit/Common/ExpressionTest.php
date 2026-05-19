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
    public function test_quote_identifier(string $input, string $expected): void {
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
    public function test_quote_value(mixed $input, string $expected): void {
        $this->assertSame($expected, Expression::quoteValue($input));
    }

    public function test_raw_returns_raw_expression(): void {
        $raw = Expression::raw('NOW()');

        $this->assertInstanceOf(RawExpression::class, $raw);
        $this->assertSame('NOW()', (string) $raw);
    }

    public function test_column_returns_column_expression_with_alias(): void {
        $col = Expression::column('users.id', 'uid');

        $this->assertInstanceOf(ColumnExpression::class, $col);
        $this->assertSame('`users`.`id` AS `uid`', (string) $col);
    }

    public function test_ref_returns_column_reference_without_alias(): void {
        $ref = Expression::ref('u.name');

        $this->assertInstanceOf(ColumnReference::class, $ref);
        $this->assertSame('`u`.`name`', (string) $ref);
    }

    public function test_identifier_returns_simple_identifier(): void {
        $id = Expression::identifier('id');

        $this->assertInstanceOf(SimpleIdentifier::class, $id);
        $this->assertSame('`id`', (string) $id);
    }

    public function test_identifier_rejects_qualified_names(): void {
        $this->expectException(InvalidArgumentException::class);

        Expression::identifier('u.id');
    }

    public function test_value_returns_value_expression(): void {
        $value = Expression::value('foo');

        $this->assertInstanceOf(ValueExpression::class, $value);
        $this->assertSame("'foo'", (string) $value);
    }

    public function test_func_uppercases_name_and_normalizes_args(): void {
        $fn = Expression::func('coalesce', 'name', null);

        $this->assertInstanceOf(FunctionExpression::class, $fn);
        $this->assertSame("COALESCE(`name`, NULL)", (string) $fn);
    }

    public function test_subquery_wraps_a_select(): void {
        $select  = Select::create()->select('1');
        $subquery = Expression::subquery($select, 's');

        $this->assertInstanceOf(SubqueryExpression::class, $subquery);
        $this->assertSame('(' . $select . ') AS `s`', (string) $subquery);
    }

    public function test_binary_normalizes_string_to_column_reference(): void {
        $expr = Expression::binary('age', BinaryOperator::GreaterThanOrEqual, 18);

        $this->assertSame('(`age` >= 18)', (string) $expr);
    }

    public function test_unary_normalizes_operand(): void {
        $expr = Expression::unary(UnaryOperator::Not, 'active');

        $this->assertSame('NOT (`active`)', (string) $expr);
    }

    public function test_and_combines_multiple_expressions_left_to_right(): void {
        $a = Expression::binary('x', BinaryOperator::Equal, 1);
        $b = Expression::binary('y', BinaryOperator::Equal, 2);
        $c = Expression::binary('z', BinaryOperator::Equal, 3);

        $this->assertSame('(((`x` = 1) AND (`y` = 2)) AND (`z` = 3))', (string) Expression::and($a, $b, $c));
    }

    public function test_or_combines_multiple_expressions(): void {
        $a = Expression::binary('x', BinaryOperator::Equal, 1);
        $b = Expression::binary('y', BinaryOperator::Equal, 2);

        $this->assertSame('((`x` = 1) OR (`y` = 2))', (string) Expression::or($a, $b));
    }

    public function test_and_requires_at_least_one_expression(): void {
        $this->expectException(InvalidArgumentException::class);

        Expression::and();
    }

    public function test_or_requires_at_least_one_expression(): void {
        $this->expectException(InvalidArgumentException::class);

        Expression::or();
    }

    public function test_exists_wraps_subquery(): void {
        $sub = Select::create()->select('1')->from('users');
        $expr = Expression::exists($sub);

        $this->assertInstanceOf(ExistsExpression::class, $expr);
        $this->assertSame("EXISTS (($sub))", (string) $expr);
    }

    public function test_not_negates_a_normalized_expression(): void {
        $expr = Expression::not('active');

        $this->assertSame('NOT (`active`)', (string) $expr);
    }

    public function test_count_defaults_to_star_and_count_alias(): void {
        $this->assertSame('COUNT(*) AS `COUNT`', (string) Expression::count());
    }

    public function test_count_accepts_custom_alias(): void {
        $this->assertSame('COUNT(`id`) AS `total`', (string) Expression::count('id', 'total'));
    }

    public function test_sum_avg_min_max_render_as_aggregates(): void {
        $this->assertSame('SUM(`amount`) AS `SUM`',  (string) Expression::sum('amount'));
        $this->assertSame('AVG(`amount`) AS `AVG`',  (string) Expression::avg('amount'));
        $this->assertSame('MIN(`amount`) AS `MIN`',  (string) Expression::min('amount'));
        $this->assertSame('MAX(`amount`) AS `MAX`',  (string) Expression::max('amount'));
    }
}
