<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Dml;

use InvalidArgumentException;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\SqlBuilder\Dml\Delete;
use Rak200\Utils\Str;

/**
 * Renders a {@see Delete} statement: DELETE FROM, USING, WHERE, ORDER BY,
 * LIMIT and RETURNING.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class DeleteRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Delete $component): string {
        if ($component->table === null) {
            throw new InvalidArgumentException('DELETE requires a target table; call from().');
        }

        $sql  = 'DELETE FROM ' . $this->dialect->renderTableReference($component->table);
        $sql .= $this->renderUsing($component);
        $sql .= $this->renderWhere($component);
        $sql .= $this->renderOrderBy($component);
        $sql .= Str::wrap((string) $component->limit, ' LIMIT ');
        $sql .= $this->renderReturning($component);

        return $sql;
    }

    protected function renderUsing(Delete $component): string {
        $rendered = array_map(
            fn($table) => $this->dialect->renderTableReference($table),
            $component->using
        );
        return Str::join($rendered, ', ', ' USING ');
    }

    protected function renderWhere(Delete $component): string {
        if ($component->where === null) {
            return '';
        }
        return ' WHERE ' . $this->dialect->renderExpression($component->where);
    }

    protected function renderOrderBy(Delete $component): string {
        $rendered = array_map(
            fn($order) => $this->dialect->renderOrder($order),
            $component->orderBy
        );
        return Str::join($rendered, ', ', ' ORDER BY ');
    }

    protected function renderReturning(Delete $component): string {
        $rendered = array_map(
            fn($expression) => $this->dialect->renderExpression($expression),
            $component->returning
        );
        return Str::join($rendered, ', ', ' RETURNING ');
    }
}
