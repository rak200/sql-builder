<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dml;

use Rak200\SqlBuilder\Common\ExpressionInterface;

/**
 * Internal value object for a single `WHEN [NOT] MATCHED ... THEN ...` branch
 * of a {@see Merge} statement.
 *
 * Kind discriminates between the four supported actions: `update`, `delete`,
 * `insert`, `nothing` (`DO NOTHING`). Field semantics depend on kind:
 *
 * - `matched`         — true → `WHEN MATCHED`; false → `WHEN NOT MATCHED`.
 * - `predicate`       — optional `AND predicate` filter on the branch.
 * - `updates`         — column → expression map for `kind = update`.
 * - `insertColumns`   — explicit column list for `kind = insert`; may be empty.
 * - `insertValues`    — list of expressions matching `insertColumns` for
 *                       `kind = insert`; renderers emit `DEFAULT VALUES` when
 *                       both lists are empty.
 *
 * @package Rak200\SqlBuilder\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class MergeClause {

    /**
     * @param 'update'|'delete'|'insert'|'nothing' $kind
     * @param array<string, ExpressionInterface> $updates
     * @param string[] $insertColumns
     * @param ExpressionInterface[] $insertValues
     */
    public function __construct(
        public readonly string $kind,
        public readonly bool $matched,
        public readonly ?ExpressionInterface $predicate = null,
        public readonly array $updates = [],
        public readonly array $insertColumns = [],
        public readonly array $insertValues = [],
    ) {}
}
