<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Enum;

/**
 * Enum UnaryOperator
 * Defines SQL prefix unary operators for use in query expressions.
 * @package Rak200\SqlBuilder\Common\Enum
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
enum UnaryOperator: string {

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
