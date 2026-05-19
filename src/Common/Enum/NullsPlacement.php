<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Enum;

/**
 * NULL placement options for ORDER BY clauses.
 *
 * Controls whether NULL values appear before or after non-NULL values
 * when used as the optional third argument to {@see \Rak200\SqlBuilder\Common\Order}.
 *
 * @package Rak200\SqlBuilder\Common\Enum
 * @author rak200 <rak.ricardo@windowslive.com>
 */
enum NullsPlacement: string {
    /** Place NULL values before non-NULL values. */
    case FIRST = 'NULLS FIRST';

    /** Place NULL values after non-NULL values. */
    case LAST = 'NULLS LAST';
}
