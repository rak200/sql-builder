<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\RawExpression;

final class RawExpressionTest extends TestCase {

    public function test_emits_input_verbatim(): void {
        $this->assertSame('NOW()', (string) new RawExpression('NOW()'));
    }

    public function test_does_not_escape_or_quote_anything(): void {
        $sql = "raw 'value' with \\ slash";
        $this->assertSame($sql, (string) new RawExpression($sql));
    }

    public function test_appends_alias(): void {
        $expr = (new RawExpression('NOW()'))->as('now');

        $this->assertSame('NOW() AS `now`', (string) $expr);
    }
}
