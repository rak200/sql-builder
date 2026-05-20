<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Dialect\Dialect;

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
    public function __construct(public readonly string $name) {}

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderColumnReference($this);
    }

    /**
     * Render this expression with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderColumnReference($this);
    }
}
