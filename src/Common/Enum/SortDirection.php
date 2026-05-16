<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Enum;

/**
 * Sort direction options for ORDER BY clauses.
 *
 * Used as the type-safe direction argument to {@see \Rak200\SqlBuilder\Common\Order}.
 *
 * @package Rak200\SqlBuilder\Common\Enum
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
enum SortDirection: string {
    /** Sort rows in ascending order (smallest to largest). */
    case ASC = 'ASC';

    /** Sort rows in descending order (largest to smallest). */
    case DESC = 'DESC';
}
