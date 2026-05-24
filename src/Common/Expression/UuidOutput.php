<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\ExpressionInterface;

/**
 * Marker wrapping a column reference whose stored value should be decoded
 * back to a text UUID when projected by `SELECT`.
 *
 * Each dialect's renderer decides what to wrap around the inner expression:
 * - Default / PostgreSQL emit the inner verbatim (UUIDs are stored as text
 *   natively).
 * - MariaDB / MySQL wraps in `BIN_TO_UUID(<inner>)` because UUIDs are
 *   simulated as `BINARY(16)` on those engines. The alias of the wrapped
 *   `Column`, if any, is hoisted onto the outer SQL so the projected column
 *   name stays consistent.
 *
 * Use {@see Expr::uuidColumn()} to construct.
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class UuidOutput extends Expr {

    /**
     * @param ExpressionInterface $inner Column expression whose UUID-typed value
     *                                   should be decoded back to text on projection.
     */
    public function __construct(public readonly ExpressionInterface $inner) {}
}
