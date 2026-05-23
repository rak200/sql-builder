<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use Rak200\SqlBuilder\Common\Expr;

/**
 * SQL column reference expression with optional alias.
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Column extends Expr {

    /**
     * @param string $name Column or qualified identifier (e.g. `table.column`).
     * @param string|null $alias Optional column alias.
     */
    public function __construct(public readonly string $name, ?string $alias = null) {
        $this->as($alias);
    }

    /**
     * Create an array of Column expressions from an associative map.
     *
     * Integer keys produce a column without alias.
     * String keys are paired with the value according to $aliasIsKey.
     *
     * @param array<int|string, string> $columns
     * @param bool $aliasIsKey When false (default): key = column name, value = alias.
     *                         When true: key = alias, value = column name.
     * @return static[]
     */
    public static function fromArray(array $columns, bool $aliasIsKey = false): array {
        $result = [];
        foreach ($columns as $key => $value) {
            if (is_int($key)) {
                $result[] = new static($value);
            } elseif ($aliasIsKey) {
                $result[] = new static($value, $key);
            } else {
                $result[] = new static($key, $value);
            }
        }
        return $result;
    }
}
