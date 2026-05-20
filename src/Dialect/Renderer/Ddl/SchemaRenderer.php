<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Ddl;

use InvalidArgumentException;
use Rak200\SqlBuilder\Ddl\Schema;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Default renderer for {@see Schema}: emits `CREATE SCHEMA`, `DROP SCHEMA`
 * or `ALTER SCHEMA ... RENAME TO`.
 *
 * Vendor dialects that lack first-class schemas (MariaDB/MySQL) override
 * this renderer to throw and rely on table-name prefixing via
 * {@see Dialect::resolveTableName()}.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class SchemaRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Schema $component): string {
        return match ($component->mode) {
            Schema::MODE_CREATE => $this->renderCreate($component),
            Schema::MODE_DROP   => $this->renderDrop($component),
            Schema::MODE_ALTER  => $this->renderAlter($component),
            default             => throw new InvalidArgumentException(
                "Unsupported schema operation: {$component->mode}"
            ),
        };
    }

    protected function renderCreate(Schema $component): string {
        $ifNotExists = $component->ifNotExists ? ' IF NOT EXISTS' : '';
        $sql = sprintf(
            'CREATE SCHEMA%s %s',
            $ifNotExists,
            $this->dialect->quoteIdentifier($component->name)
        );

        if ($component->authorization !== null) {
            $sql .= ' AUTHORIZATION ' . $this->dialect->quoteIdentifier($component->authorization);
        }

        return $sql;
    }

    protected function renderDrop(Schema $component): string {
        $ifExists = $component->ifExists ? ' IF EXISTS' : '';
        $sql = sprintf(
            'DROP SCHEMA%s %s',
            $ifExists,
            $this->dialect->quoteIdentifier($component->name)
        );

        if ($component->cascade) {
            $sql .= ' CASCADE';
        } elseif ($component->restrict) {
            $sql .= ' RESTRICT';
        }

        return $sql;
    }

    protected function renderAlter(Schema $component): string {
        if ($component->renameTo === null) {
            throw new InvalidArgumentException('ALTER SCHEMA requires an operation; call renameTo().');
        }

        return sprintf(
            'ALTER SCHEMA %s RENAME TO %s',
            $this->dialect->quoteIdentifier($component->name),
            $this->dialect->quoteIdentifier($component->renameTo)
        );
    }
}
