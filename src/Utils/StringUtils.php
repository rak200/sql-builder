<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Utils;

use function count;

/**
 * StringUtils is a utility class for string manipulation.
 * It provides methods to check if a string is blank, convert strings to camel case, and more.
 * @package Rak200\SqlBuilder\Utils
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
class StringUtils {

    /**
     * Checks if a string is blank (empty or contains only whitespace).
     * @param string $val The string to check.
     * @return bool True if the string is blank, false otherwise.
     */
    public static function isBlank(string $val): bool {
        return empty($val) || trim($val) === '';
    }

    /**
     * Checks if a string is not blank (not empty and not just whitespace).
     * @param string $val The string to check.
     * @return bool True if the string is not blank, false otherwise.
     */
    public static function isNotBlank(string $val): bool {
        return !self::isBlank($val);
    }

    /**
     * Converts a string to camel case.
     * @param string $val The string to convert.
     * @param bool $firstUpper Whether to capitalize the first letter of the result.
     * @return string The camel case version of the input string.
     */
    public static function toCamelCase(string $val, bool $firstUpper = true): string {
        $res = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $val)));
        if (!$firstUpper) {
            return lcfirst($res);
        }
        return $res;
    }

    /**
     * Joins iterable items into a string ignoring empty elements with optional prefix, suffix, and last separator.
     *
     * @param iterable<mixed> $items Items to join; each is cast to string.
     * @param string $separator Separator placed between consecutive items.
     * @param string $prefix String prepended to the result if has items.
     * @param string $suffix String appended to the result if has items.
     * @param string|null $lastSeparator Separator used between the penultimate and last item
     *                                  instead of $separator (e.g. ' and ', ' or ').
     *                                  When null, $separator is used everywhere.
     * @return string
     */
    public static function join(
        iterable $items,
        string $separator,
        string $prefix = '',
        string $suffix = '',
        ?string $lastSeparator = null
    ): string {
        $parts = [];
        foreach ($items as $item) {
            $str = (string) $item;
            if (!self::isBlank($str)) {
                $parts[] = $str;
            }
        }

        if (empty($parts)) {
            return '';
        }

        if ($lastSeparator === null || count($parts) < 2) {
            return $prefix . implode($separator, $parts) . $suffix;
        }

        $last = array_pop($parts);
        return $prefix . implode($separator, $parts) . $lastSeparator . $last . $suffix;
    }

    /**
     * Wraps a string with a prefix and suffix, returning empty string if the value is blank.
     *
     * @param string $val The value to wrap.
     * @param string $prefix String prepended to $val.
     * @param string $suffix String appended to $val.
     * @return string Wrapped string, or empty string when $val is blank.
     */
    public static function wrap(string $val, string $prefix = '', string $suffix = ''): string {
        return self::isBlank($val) ? '' : "{$prefix}{$val}{$suffix}";
    }

    /**
     * Converts a string to pascal case.
     * @param string $val The string to convert.
     * @return string The pascal case version of the input string.
     */
    public static function toSnakeCase(string $val): string {
        return strtolower(preg_replace('/[A-Z]/', '_$0', $val));
    }

}
