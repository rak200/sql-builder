<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Ddl\Check;

final class CheckTest extends TestCase {

    public function test_unnamed_check_with_string_condition(): void {
        $this->assertSame('CHECK (age >= 18)', (string) Check::create()->condition('age >= 18'));
    }

    public function test_named_check_with_string_condition(): void {
        $sql = (string) Check::create('chk_age')->condition('age >= 18');

        $this->assertSame('CONSTRAINT "chk_age" CHECK (age >= 18)', $sql);
    }

    public function test_check_with_expression_condition(): void {
        $expr = Expression::binary('age', BinaryOperator::GreaterThanOrEqual, 18);
        $sql  = (string) Check::create('chk_age')->condition($expr);

        $this->assertSame('CONSTRAINT "chk_age" CHECK ((`age` >= 18))', $sql);
    }

    public function test_empty_condition_renders_keyword_only(): void {
        $this->assertSame('CHECK', (string) Check::create());
    }
}
