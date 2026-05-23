<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Dml;

use InvalidArgumentException;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\SqlBuilder\Dml\Update;
use Rak200\Utils\Str;

/**
 * Renders an {@see Update} statement: UPDATE, SET, FROM, WHERE, ORDER BY,
 * LIMIT and RETURNING.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UpdateRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Update $component): string {
        if ($component->table === null) {
            throw new InvalidArgumentException('UPDATE requires a target table; call table().');
        }
        if ($component->assignments === []) {
            throw new InvalidArgumentException('UPDATE requires at least one SET assignment.');
        }

        $sql  = sprintf(
            'UPDATE %s SET %s',
            $this->dialect->renderTableReference($component->table),
            $this->renderAssignments($component)
        );
        $sql .= $this->renderFrom($component);
        $sql .= $this->renderWhere($component);
        $sql .= $this->renderOrderBy($component);
        $sql .= Str::wrap((string) $component->limit, ' LIMIT ');
        $sql .= $this->renderReturning($component);

        return $sql;
    }

    protected function renderAssignments(Update $component): string {
        $parts = [];
        foreach ($component->assignments as $column => $value) {
            $parts[] = sprintf(
                '%s = %s',
                $this->dialect->quoteIdentifier($column),
                $this->dialect->renderExpression($value)
            );
        }
        return implode(', ', $parts);
    }

    protected function renderFrom(Update $component): string {
        $rendered = array_map(
            fn($table) => $this->dialect->renderTableReference($table),
            $component->from
        );
        return Str::join($rendered, ', ', ' FROM ');
    }

    protected function renderWhere(Update $component): string {
        if ($component->where === null) {
            return '';
        }
        return ' WHERE ' . $this->dialect->renderExpression($component->where);
    }

    protected function renderOrderBy(Update $component): string {
        $rendered = array_map(
            fn($order) => $this->dialect->renderOrder($order),
            $component->orderBy
        );
        return Str::join($rendered, ', ', ' ORDER BY ');
    }

    protected function renderReturning(Update $component): string {
        $rendered = array_map(
            fn($expression) => $this->dialect->renderExpression($expression),
            $component->returning
        );
        return Str::join($rendered, ', ', ' RETURNING ');
    }
}
