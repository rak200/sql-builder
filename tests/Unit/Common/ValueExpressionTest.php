<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Expression\Value as ValueExpression;

final class ValueExpressionTest extends TestCase {

    public static function valueProvider(): array {
        return [
            'null'    => [null, 'NULL'],
            'int'     => [10, '10'],
            'float'   => [3.14, '3.14'],
            'true'    => [true, 'TRUE'],
            'false'   => [false, 'FALSE'],
            'string'  => ['hi', "'hi'"],
            'escapes' => ["it's", "'it''s'"],
        ];
    }

    #[DataProvider('valueProvider')]
    public function testRendersQuotedValue(mixed $value, string $expected): void {
        $this->assertSame($expected, (string) new ValueExpression($value));
    }

    public function testAppendsAlias(): void {
        $expr = (new ValueExpression(1))->as('one');

        $this->assertSame('1 AS `one`', (string) $expr);
    }
}
