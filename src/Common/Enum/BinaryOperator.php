<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Enum;

/**
 * Enum BinaryOperator
 * Defines SQL binary operators for use in query expressions.
 * @package Rak200\SqlBuilder\Common\Enum
 * @author rak200 <rak.ricardo@windowslive.com>
 */
enum BinaryOperator: string {

    // --- Comparison ---
    case Equal              = '=';
    case NotEqual           = '<>';
    case GreaterThan        = '>';
    case LessThan           = '<';
    case GreaterThanOrEqual = '>=';
    case LessThanOrEqual    = '<=';

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
