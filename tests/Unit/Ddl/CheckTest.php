<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Ddl\Check;

final class CheckTest extends TestCase {

    public function testUnnamedCheckWithStringCondition(): void {
        $this->assertSame('CHECK (age >= 18)', (string) Check::create()->condition('age >= 18'));
    }

    public function testNamedCheckWithStringCondition(): void {
        $sql = (string) Check::create('chk_age')->condition('age >= 18');

        $this->assertSame('CONSTRAINT "chk_age" CHECK (age >= 18)', $sql);
    }

    public function testCheckWithExpressionCondition(): void {
        $expr = Expression::binary('age', BinaryOperator::Ge, 18);
        $sql  = (string) Check::create('chk_age')->condition($expr);

        $this->assertSame('CONSTRAINT "chk_age" CHECK ((`age` >= 18))', $sql);
    }

    public function testEmptyConditionRendersKeywordOnly(): void {
        $this->assertSame('CHECK', (string) Check::create());
    }
}
