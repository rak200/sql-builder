<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

/**
 * Marker wrapping an expression destined for a UUID column.
 *
 * Used on the value side of INSERT / UPDATE / WHERE comparisons. Each
 * dialect's renderer decides what to wrap around the inner expression:
 * - Default dialect emits the inner verbatim.
 * - PostgreSQL appends `::uuid` when the inner is a literal or a parameter
 *   placeholder.
 * - MariaDB / MySQL wraps in `UUID_TO_BIN(<inner>)` because UUIDs are
 *   simulated as `BINARY(16)` on those engines.
 *
 * Use {@see Expression::uuid()} to construct.
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class UuidInputExpression extends Expression {

    /**
     * @param ExpressionInterface $inner The value expression to wrap.
     */
    public function __construct(public readonly ExpressionInterface $inner) {}
}
