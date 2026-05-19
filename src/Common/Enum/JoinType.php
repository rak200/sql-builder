<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Enum;

/**
 * SQL JOIN type options.
 *
 * Used as the type-safe first argument to {@see \Rak200\SqlBuilder\Common\Join}.
 *
 * @package Rak200\SqlBuilder\Common\Enum
 * @author rak200 <rak.ricardo@windowslive.com>
 */
enum JoinType: string {
    /** Standard inner join — only rows with a match in both tables. */
    case INNER = 'INNER JOIN';

    /** Left outer join — all rows from the left table, matched rows from the right. */
    case LEFT = 'LEFT JOIN';

    /** Right outer join — all rows from the right table, matched rows from the left. */
    case RIGHT = 'RIGHT JOIN';

    /** Full outer join — all rows from both tables, with NULLs where there is no match. */
    case FULL = 'FULL JOIN';

    /** Cross join — cartesian product of both tables (no ON or USING allowed). */
    case CROSS = 'CROSS JOIN';
}
