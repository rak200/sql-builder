<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

/**
 * SQL column or table-qualified column reference for use in expressions.
 *
 * Represents an identifier (e.g. `column` or `table.column`) that cannot carry
 * an alias. Use this in binary conditions, unary operators, ORDER BY, GROUP BY,
 * and similar non-projection contexts. For SELECT-list columns with an alias,
 * use {@see ColumnExpression} instead.
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class ColumnReference implements ExpressionInterface {

    /**
     * @param string $name Column or qualified identifier (e.g. `table.column`).
     */
    public function __construct(private string $name) {}

    /** {@inheritdoc} */
    public function __toString(): string {
        return Expression::quoteIdentifier($this->name);
    }
}
