<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Enum;

/**
 * Grouping extension keyword used in `GROUP BY <mode> (...)`.
 *
 * Maps directly to the SQL keyword emitted by
 * {@see \Rak200\SqlBuilder\Common\Expression\Grouping}.
 *
 * @package Rak200\SqlBuilder\Common\Enum
 * @author rak200 <rak.ricardo@windowslive.com>
 */
enum GroupingMode: string {
    case Sets   = 'GROUPING SETS';
    case Rollup = 'ROLLUP';
    case Cube   = 'CUBE';
}
