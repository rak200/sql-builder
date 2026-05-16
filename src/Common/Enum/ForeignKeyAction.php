<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Enum;

/**
 * Referential action options for FOREIGN KEY ON DELETE and ON UPDATE clauses.
 *
 * Used as the type-safe argument to {@see \Rak200\SqlBuilder\Ddl\ForeignKey::onDelete()} and
 * {@see \Rak200\SqlBuilder\Ddl\ForeignKey::onUpdate()} to prevent invalid SQL from being generated.
 *
 * @package Rak200\SqlBuilder\Common\Enum
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
enum ForeignKeyAction: string {
    /** Delete or update the child row together with the parent row. */
    case CASCADE = 'CASCADE';

    /** Set the foreign key columns to NULL when the parent row is deleted or updated. */
    case SET_NULL = 'SET NULL';

    /** Set the foreign key columns to their column default when the parent row is deleted or updated. */
    case SET_DEFAULT = 'SET DEFAULT';

    /** Reject the operation immediately if a child row exists. */
    case RESTRICT = 'RESTRICT';

    /** Defer the referential integrity check; reject the transaction if a child row exists at commit time. */
    case NO_ACTION = 'NO ACTION';
}
