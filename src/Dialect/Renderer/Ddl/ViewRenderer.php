<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Ddl;

use InvalidArgumentException;
use Rak200\SqlBuilder\Ddl\View;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\Utils\Str;

/**
 * Renders a {@see View} as `CREATE VIEW` or `DROP VIEW` based on mode.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class ViewRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(View $component): string {
        return match ($component->mode) {
            View::MODE_CREATE => $this->renderCreate($component),
            View::MODE_DROP   => $this->renderDrop($component),
            default           => throw new InvalidArgumentException(
                "Unsupported view mode: {$component->mode}"
            ),
        };
    }

    protected function renderCreate(View $component): string {
        if ($component->query === null) {
            throw new InvalidArgumentException('A SELECT query must be provided via query() for CREATE VIEW.');
        }

        if ($component->orReplace && $component->ifNotExists) {
            throw new InvalidArgumentException('OR REPLACE and IF NOT EXISTS are mutually exclusive.');
        }

        $orReplace   = $component->orReplace   ? ' OR REPLACE'   : '';
        $temporary   = $component->temporary   ? ' TEMPORARY'    : '';
        $ifNotExists = $component->ifNotExists ? ' IF NOT EXISTS' : '';

        $sql = sprintf(
            'CREATE%s%s VIEW%s %s',
            $orReplace,
            $temporary,
            $ifNotExists,
            $this->dialect->quoteIdentifier($this->dialect->resolveTableName($component->name))
        );

        $sql .= $this->renderColumnList($component);
        $sql .= ' AS ' . $this->dialect->renderSelect($component->query);
        $sql .= $this->renderCheckOption($component);

        return $sql;
    }

    protected function renderDrop(View $component): string {
        $ifExists = $component->ifExists ? ' IF EXISTS' : '';
        $sql = sprintf(
            'DROP VIEW%s %s',
            $ifExists,
            $this->dialect->quoteIdentifier($this->dialect->resolveTableName($component->name))
        );

        if ($component->cascade) {
            $sql .= ' CASCADE';
        } elseif ($component->restrict) {
            $sql .= ' RESTRICT';
        }

        return $sql;
    }

    protected function renderColumnList(View $component): string {
        return Str::join(
            array_map(fn(string $column) => $this->dialect->quoteIdentifier($column), $component->columns),
            ', ',
            ' (',
            ')'
        );
    }

    protected function renderCheckOption(View $component): string {
        if (!$component->withCheckOption) {
            return '';
        }

        $qualifier = $component->checkOption !== null ? ' ' . $component->checkOption->value : '';
        return sprintf(' WITH%s CHECK OPTION', $qualifier);
    }
}
