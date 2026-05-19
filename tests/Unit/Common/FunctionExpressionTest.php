<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\FunctionExpression;

final class FunctionExpressionTest extends TestCase {

    public function test_uppercases_function_name(): void {
        $this->assertSame('UPPER(`name`)', (string) new FunctionExpression('upper', 'name'));
    }

    public function test_normalizes_string_arg_to_column_reference(): void {
        $this->assertSame('LENGTH(`name`)', (string) new FunctionExpression('LENGTH', 'name'));
    }

    public function test_normalizes_scalar_args_to_value_expressions(): void {
        $this->assertSame("DATE_ADD(`created_at`, 7)", (string) new FunctionExpression('DATE_ADD', 'created_at', 7));
    }

    public function test_accepts_no_arguments(): void {
        $this->assertSame('NOW()', (string) new FunctionExpression('NOW'));
    }

    public function test_appends_alias(): void {
        $expr = (new FunctionExpression('count', '*'))->as('total');

        $this->assertSame('COUNT(*) AS `total`', (string) $expr);
    }
}
