<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Dml;

use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Utils\StringUtils;

/**
 * Renders a {@see Select} statement: SELECT, FROM, JOIN, WHERE, GROUP BY,
 * HAVING, ORDER BY, LIMIT and OFFSET.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class SelectRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Select $component): string {
        $sql  = $this->renderWith($component);
        $sql .= $this->renderSelectClause($component);
        $sql .= $this->renderFrom($component);
        $sql .= $this->renderJoins($component);
        $sql .= $this->renderWhere($component);
        $sql .= $this->renderGroupBy($component);
        $sql .= $this->renderHaving($component);
        $sql .= $this->renderOrderBy($component);
        $sql .= $this->renderLimitOffset($component);

        return trim($sql);
    }

    protected function renderWith(Select $component): string {
        if (count($component->ctes) === 0) {
            return '';
        }

        $keyword = $component->recursive ? 'WITH RECURSIVE ' : 'WITH ';
        $entries = [];
        foreach ($component->ctes as $cte) {
            $entries[] = $this->dialect->renderCte($cte);
        }

        return $keyword . implode(', ', $entries) . ' ';
    }

    protected function renderSelectClause(Select $component): string {
        $distinct = $component->distinct ? ' DISTINCT' : '';
        $columns  = $this->joinRendered($component->columns->toArray(), ', ') ?: '*';
        return sprintf('SELECT%s %s', $distinct, $columns);
    }

    protected function renderFrom(Select $component): string {
        if ($component->from === null) {
            return '';
        }
        return ' FROM ' . $this->dialect->renderTableReference($component->from);
    }

    protected function renderJoins(Select $component): string {
        $rendered = [];
        foreach ($component->joins as $join) {
            $rendered[] = $this->dialect->renderJoin($join);
        }
        return StringUtils::join($rendered, ' ', ' ');
    }

    protected function renderWhere(Select $component): string {
        if ($component->where === null) {
            return '';
        }
        return ' WHERE ' . $this->dialect->renderExpression($component->where);
    }

    protected function renderGroupBy(Select $component): string {
        $rendered = [];
        foreach ($component->groupBy as $expression) {
            $rendered[] = $this->dialect->renderExpression($expression);
        }
        return StringUtils::join($rendered, ', ', ' GROUP BY ');
    }

    protected function renderHaving(Select $component): string {
        if ($component->having === null) {
            return '';
        }
        return ' HAVING ' . $this->dialect->renderExpression($component->having);
    }

    protected function renderOrderBy(Select $component): string {
        $rendered = [];
        foreach ($component->orderBy as $order) {
            $rendered[] = $this->dialect->renderOrder($order);
        }
        return StringUtils::join($rendered, ', ', ' ORDER BY ');
    }

    protected function renderLimitOffset(Select $component): string {
        return StringUtils::wrap((string) $component->limit,  ' LIMIT ')
             . StringUtils::wrap((string) $component->offset, ' OFFSET ');
    }

    /**
     * @param iterable<\Rak200\SqlBuilder\Common\ExpressionInterface> $expressions
     */
    private function joinRendered(iterable $expressions, string $separator): string {
        $parts = [];
        foreach ($expressions as $expression) {
            $parts[] = $this->dialect->renderExpression($expression);
        }
        return implode($separator, $parts);
    }
}
