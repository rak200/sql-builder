<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\FunctionExpression;

final class FunctionExpressionTest extends TestCase {

    public function testUppercasesFunctionName(): void {
        $this->assertSame('UPPER(`name`)', (string) new FunctionExpression('upper', 'name'));
    }

    public function testNormalizesStringArgToColumnReference(): void {
        $this->assertSame('LENGTH(`name`)', (string) new FunctionExpression('LENGTH', 'name'));
    }

    public function testNormalizesScalarArgsToValueExpressions(): void {
        $this->assertSame("DATE_ADD(`created_at`, 7)", (string) new FunctionExpression('DATE_ADD', 'created_at', 7));
    }

    public function testAcceptsNoArguments(): void {
        $this->assertSame('NOW()', (string) new FunctionExpression('NOW'));
    }

    public function testAppendsAlias(): void {
        $expr = (new FunctionExpression('count', '*'))->as('total');

        $this->assertSame('COUNT(*) AS `total`', (string) $expr);
    }
}
