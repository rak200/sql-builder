<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Enum;

/**
 * WITH CHECK OPTION qualifier for CREATE VIEW and ALTER VIEW statements.
 *
 * Controls how the check option cascades through dependent views.
 * Used as the optional argument to {@see \Rak200\SqlBuilder\Ddl\View::withCheckOption()}.
 *
 * @package Rak200\SqlBuilder\Common\Enum
 * @author rak200 <rak.ricardo@windowslive.com>
 */
enum CheckOption: string {
    /**
     * Check option cascades to all underlying views.
     * This is the SQL standard default when no qualifier is specified.
     */
    case CASCADED = 'CASCADED';

    /** Check option applies only to the current view, not to underlying views. */
    case LOCAL = 'LOCAL';
}
