<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Enum\Operator;

/**
 * SQL binary operators used in query expressions.
 *
 * Comparison cases use the compact two-letter mnemonics (Eq/Ne/Gt/Lt/Ge/Le)
 * to keep call sites short. Null-safe comparisons (`NullSafeEq` /
 * `NullSafeNe`) default to the SQL standard `IS [NOT] DISTINCT FROM` form
 * (PostgreSQL); the MariaDB dialect rewrites them to `<=>` / `NOT (<=>)`.
 *
 * @package Rak200\SqlBuilder\Common\Enum\Operator
 * @author rak200 <rak.ricardo@windowslive.com>
 */
enum Binary: string {

    // --- Comparison ---
    case Eq = '=';
    case Ne = '<>';
    case Gt = '>';
    case Lt = '<';
    case Ge = '>=';
    case Le = '<=';

    // --- Null-safe comparison ---
    case NullSafeEq = 'IS NOT DISTINCT FROM';
    case NullSafeNe = 'IS DISTINCT FROM';

    // --- Pattern matching ---
    case Like    = 'LIKE';
    case NotLike = 'NOT LIKE';
    case Regexp  = 'REGEXP';

    // --- Null checks ---
    case Is    = 'IS';
    case IsNot = 'IS NOT';

    // --- Membership ---
    case In    = 'IN';
    case NotIn = 'NOT IN';

    // --- Range ---
    case Between    = 'BETWEEN';
    case NotBetween = 'NOT BETWEEN';

    // --- Logical ---
    case And = 'AND';
    case Or  = 'OR';
}
