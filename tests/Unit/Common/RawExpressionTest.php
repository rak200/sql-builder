<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Expression\Raw as RawExpression;

final class RawExpressionTest extends TestCase {

    public function testEmitsInputVerbatim(): void {
        $this->assertSame('NOW()', (string) new RawExpression('NOW()'));
    }

    public function testDoesNotEscapeOrQuoteAnything(): void {
        $sql = "raw 'value' with \\ slash";
        $this->assertSame($sql, (string) new RawExpression($sql));
    }

    public function testAppendsAlias(): void {
        $expr = (new RawExpression('NOW()'))->as('now');

        $this->assertSame('NOW() AS `now`', (string) $expr);
    }
}
