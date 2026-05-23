<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Enum\Operator;

/**
 * SQL arithmetic operators used in numeric expressions.
 *
 * Kept separate from {@see Binary} so the type system can distinguish
 * predicate-producing operators (`=`, `AND`, `IS`, …) from value-producing
 * ones (`+`, `-`, `*`, `/`, `%`).
 *
 * @package Rak200\SqlBuilder\Common\Enum\Operator
 * @author rak200 <rak.ricardo@windowslive.com>
 */
enum Math: string {

    case Add = '+';
    case Sub = '-';
    case Mul = '*';
    case Div = '/';
    case Mod = '%';
}
