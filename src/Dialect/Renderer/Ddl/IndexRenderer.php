<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Ddl;

use InvalidArgumentException;
use Rak200\SqlBuilder\Ddl\Index;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders an {@see Index} as `CREATE [UNIQUE] INDEX` or `DROP INDEX`.
 *
 * The default DROP INDEX form follows the PostgreSQL syntax — only the
 * index name (plus optional `IF EXISTS` and `CASCADE`). Engines that
 * require the parent table on DROP (e.g. MariaDB / MySQL) override
 * {@see renderDrop()}.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class IndexRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Index $component): string {
        return match ($component->mode) {
            Index::MODE_CREATE => $this->renderCreate($component),
            Index::MODE_DROP   => $this->renderDrop($component),
            default            => throw new InvalidArgumentException(
                "Unsupported index mode: {$component->mode}"
            ),
        };
    }

    protected function renderCreate(Index $component): string {
        $unique  = $component->unique ? 'UNIQUE ' : '';
        $columns = implode(', ', array_map(
            fn(string $column) => $this->dialect->quoteIdentifier($column),
            $component->columns
        ));

        return sprintf(
            'CREATE %sINDEX %s ON %s (%s)',
            $unique,
            $this->dialect->quoteIdentifier($component->name),
            $this->dialect->quoteIdentifier($this->dialect->resolveTableName($component->table)),
            $columns
        );
    }

    protected function renderDrop(Index $component): string {
        $ifExists = $component->ifExists ? ' IF EXISTS' : '';
        $sql = sprintf('DROP INDEX%s %s', $ifExists, $this->dialect->quoteIdentifier($component->name));

        if ($component->cascade) {
            $sql .= ' CASCADE';
        }

        return $sql;
    }
}
