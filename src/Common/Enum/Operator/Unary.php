<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Enum\Operator;

/**
 * SQL prefix unary operators for use in query expressions.
 *
 * @package Rak200\SqlBuilder\Common\Enum\Operator
 * @author rak200 <rak.ricardo@windowslive.com>
 */
enum Unary: string {

    // --- Logical ---
    case Not    = 'NOT';
    case Exists = 'EXISTS';
    case All    = 'ALL';
    case Any    = 'ANY';
    case Some   = 'SOME';

    // --- Arithmetic ---
    case Minus = '-';
    case Plus  = '+';

    // --- Bitwise ---
    case BitwiseNot = '~';

    // --- Modifier ---
    case Distinct = 'DISTINCT';
}
