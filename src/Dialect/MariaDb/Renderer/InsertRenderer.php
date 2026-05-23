<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Dialect\Renderer\Dml\InsertRenderer as BaseInsertRenderer;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;
use Rak200\SqlBuilder\Dml\Insert;

/**
 * MariaDB INSERT renderer.
 *
 * - Permits the inherited `ON DUPLICATE KEY UPDATE` clause and translates
 *   `onConflict()->doUpdate(...)` to it (so portable upsert code works).
 * - Rejects `onConflict()->doNothing()` and `onConflictWhere()` because
 *   MariaDB / MySQL have no direct equivalent.
 * - Rejects `RETURNING`, which is only supported from MariaDB 10.5 onwards
 *   (the {@see \Rak200\SqlBuilder\Dialect\MariaDb\MariaDb105Dialect} re-enables it).
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class InsertRenderer extends BaseInsertRenderer {

    protected function renderOnConflict(Insert $component): string {
        if ($component->conflictColumns === null) {
            return '';
        }

        if ($component->conflictDoNothing) {
            throw new UnsupportedFeatureException(
                'MariaDB / MySQL do not support ON CONFLICT ... DO NOTHING; use INSERT IGNORE instead.'
            );
        }
        if ($component->conflictWhere !== null) {
            throw new UnsupportedFeatureException(
                'MariaDB / MySQL do not support a WHERE clause on conflict resolution.'
            );
        }

        $assignments = [];
        foreach ($component->conflictUpdates ?? [] as $column => $value) {
            $assignments[] = sprintf(
                '%s = %s',
                $this->dialect->quoteIdentifier($column),
                $this->dialect->renderExpression($value)
            );
        }

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $assignments);
    }

    protected function renderReturning(Insert $component): string {
        if ($component->returning !== []) {
            throw new UnsupportedFeatureException('MariaDB before 10.5 does not support RETURNING on INSERT.');
        }
        return '';
    }
}
