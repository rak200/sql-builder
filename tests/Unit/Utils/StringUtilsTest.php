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
    public function test_is_blank(string $input, bool $expected): void {
        $this->assertSame($expected, StringUtils::isBlank($input));
        $this->assertSame(!$expected, StringUtils::isNotBlank($input));
    }

    public function test_to_camel_case_default_upper_first(): void {
        $this->assertSame('FooBar', StringUtils::toCamelCase('foo_bar'));
        $this->assertSame('FooBar', StringUtils::toCamelCase('foo-bar'));
    }

    public function test_to_camel_case_lower_first(): void {
        $this->assertSame('fooBar', StringUtils::toCamelCase('foo_bar', firstUpper: false));
    }

    public function test_to_snake_case(): void {
        $this->assertSame('_foo_bar', StringUtils::toSnakeCase('FooBar'));
        $this->assertSame('my_camel_case', StringUtils::toSnakeCase('myCamelCase'));
    }

    public function test_join_with_only_blank_items_returns_empty_string(): void {
        $this->assertSame('', StringUtils::join(['', '  ', ''], ', ', '[', ']'));
    }

    public function test_join_skips_blank_items(): void {
        $this->assertSame('[a, b]', StringUtils::join(['a', '', 'b'], ', ', '[', ']'));
    }

    public function test_join_applies_prefix_suffix_when_items_present(): void {
        $this->assertSame('[a, b, c]', StringUtils::join(['a', 'b', 'c'], ', ', '[', ']'));
    }

    public function test_join_uses_last_separator_for_final_item(): void {
        $this->assertSame('a, b and c', StringUtils::join(['a', 'b', 'c'], ', ', lastSeparator: ' and '));
    }

    public function test_join_last_separator_with_two_items(): void {
        $this->assertSame('a and b', StringUtils::join(['a', 'b'], ', ', lastSeparator: ' and '));
    }

    public function test_wrap_returns_empty_when_blank(): void {
        $this->assertSame('', StringUtils::wrap('', '(', ')'));
        $this->assertSame('', StringUtils::wrap('   ', '(', ')'));
    }

    public function test_wrap_wraps_when_not_blank(): void {
        $this->assertSame('(x)', StringUtils::wrap('x', '(', ')'));
    }
}
