<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Dml;

use InvalidArgumentException;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\SqlBuilder\Dml\Set;

/**
 * Renders a {@see Set} (UNION/EXCEPT/INTERSECT) statement with optional
 * ORDER BY, LIMIT and OFFSET on the combined result.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class SetRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Set $component): string {
        if ($component->operations === []) {
            throw new InvalidArgumentException('Set operation must have at least one query.');
        }

        $sql = $this->renderOperations($component);
        $sql .= $this->renderOrderBy($component);
        $sql .= $this->renderLimitOffset($component);

        return trim($sql);
    }

    protected function renderOperations(Set $component): string {
        $sql = '';

        foreach ($component->operations as $index => $operation) {
            $query = '(' . $this->dialect->renderSelect($operation['query']) . ')';
            $sql .= $index === 0 ? $query : ' ' . $operation['type'] . ' ' . $query;
        }

        return $sql;
    }

    protected function renderOrderBy(Set $component): string {
        if (count($component->orderBy) === 0) {
            return '';
        }

        $rendered = [];
        foreach ($component->orderBy as $order) {
            $rendered[] = $this->dialect->renderOrder($order);
        }

        return ' ORDER BY ' . implode(', ', $rendered);
    }

    protected function renderLimitOffset(Set $component): string {
        $sql = '';

        if ($component->limit !== null) {
            $sql .= ' LIMIT ' . $component->limit;
        }

        if ($component->offset !== null) {
            $sql .= ' OFFSET ' . $component->offset;
        }

        return $sql;
    }
}
