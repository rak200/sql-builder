<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Utils;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Utils\StringUtils;

final class StringUtilsTest extends TestCase {

    public static function blankProvider(): array {
        return [
            'empty'         => ['', true],
            'spaces'        => ['   ', true],
            'tab and nl'    => ["\t\n", true],
            'non-blank'     => ['x', false],
            'with content'  => ['  x  ', false],
        ];
    }

    #[DataProvider('blankProvider')]
    public function testIsBlank(string $input, bool $expected): void {
        $this->assertSame($expected, StringUtils::isBlank($input));
        $this->assertSame(!$expected, StringUtils::isNotBlank($input));
    }

    public function testToCamelCaseDefaultUpperFirst(): void {
        $this->assertSame('FooBar', StringUtils::toCamelCase('foo_bar'));
        $this->assertSame('FooBar', StringUtils::toCamelCase('foo-bar'));
    }

    public function testToCamelCaseLowerFirst(): void {
        $this->assertSame('fooBar', StringUtils::toCamelCase('foo_bar', firstUpper: false));
    }

    public function testToSnakeCase(): void {
        $this->assertSame('_foo_bar', StringUtils::toSnakeCase('FooBar'));
        $this->assertSame('my_camel_case', StringUtils::toSnakeCase('myCamelCase'));
    }

    public function testJoinWithOnlyBlankItemsReturnsEmptyString(): void {
        $this->assertSame('', StringUtils::join(['', '  ', ''], ', ', '[', ']'));
    }

    public function testJoinSkipsBlankItems(): void {
        $this->assertSame('[a, b]', StringUtils::join(['a', '', 'b'], ', ', '[', ']'));
    }

    public function testJoinAppliesPrefixSuffixWhenItemsPresent(): void {
        $this->assertSame('[a, b, c]', StringUtils::join(['a', 'b', 'c'], ', ', '[', ']'));
    }

    public function testJoinUsesLastSeparatorForFinalItem(): void {
        $this->assertSame('a, b and c', StringUtils::join(['a', 'b', 'c'], ', ', lastSeparator: ' and '));
    }

    public function testJoinLastSeparatorWithTwoItems(): void {
        $this->assertSame('a and b', StringUtils::join(['a', 'b'], ', ', lastSeparator: ' and '));
    }

    public function testWrapReturnsEmptyWhenBlank(): void {
        $this->assertSame('', StringUtils::wrap('', '(', ')'));
        $this->assertSame('', StringUtils::wrap('   ', '(', ')'));
    }

    public function testWrapWrapsWhenNotBlank(): void {
        $this->assertSame('(x)', StringUtils::wrap('x', '(', ')'));
    }
}
