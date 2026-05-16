<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use InvalidArgumentException;

/**
 * A simple unqualified SQL identifier for use in USING clauses.
 *
 * Only accepts plain column names without a table qualifier (no dots).
 * Use in JOIN ... USING (...) where SQL requires bare column names.
 * For table-qualified references use {@see ColumnReference};
 * for projection columns with aliases use {@see ColumnExpression}.
 *
 * @package Rak200\SqlBuilder\Common
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
final class SimpleIdentifier implements ExpressionInterface {

    /**
     * @param string $name Unqualified column name.
     * @throws InvalidArgumentException If the name contains a dot (table qualifier).
     */
    public function __construct(private string $name) {
        if (preg_match('/[^a-zA-Z0-9_]/', $name)) {
            throw new InvalidArgumentException("USING column must be unqualified: '$name'");
        }
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Expression::quoteIdentifier($this->name);
    }
}
