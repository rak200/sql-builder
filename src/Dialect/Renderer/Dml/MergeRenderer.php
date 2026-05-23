<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Dml;

use InvalidArgumentException;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\SqlBuilder\Dml\Merge;
use Rak200\SqlBuilder\Dml\MergeClause;
use Rak200\Utils\Str;

/**
 * Renders a SQL:2003 {@see Merge} statement.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class MergeRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Merge $component): string {
        if ($component->target === null) {
            throw new InvalidArgumentException('MERGE requires a target; call into().');
        }
        if ($component->source === null) {
            throw new InvalidArgumentException('MERGE requires a source; call using().');
        }
        if ($component->on === null) {
            throw new InvalidArgumentException('MERGE requires an ON condition.');
        }
        if ($component->clauses === []) {
            throw new InvalidArgumentException('MERGE requires at least one WHEN clause.');
        }

        $sql = 'MERGE INTO ' . $this->dialect->renderTableReference($component->target);
        $sql .= ' USING ' . $this->dialect->renderTableReference($component->source);
        $sql .= ' ON ' . $this->dialect->renderExpression($component->on);

        foreach ($component->clauses as $clause) {
            $sql .= ' ' . $this->renderClause($clause);
        }

        $sql .= $this->renderReturning($component);
        return $sql;
    }

    protected function renderClause(MergeClause $clause): string {
        $head      = $clause->matched ? 'WHEN MATCHED' : 'WHEN NOT MATCHED';
        $predicate = $clause->predicate !== null
            ? ' AND ' . $this->dialect->renderExpression($clause->predicate)
            : '';

        $body = match ($clause->kind) {
            'update'  => 'THEN UPDATE SET ' . $this->renderUpdateAssignments($clause),
            'delete'  => 'THEN DELETE',
            'insert'  => 'THEN ' . $this->renderInsertAction($clause),
            'nothing' => 'THEN DO NOTHING',
            default   => throw new InvalidArgumentException("Unknown MERGE clause kind: {$clause->kind}"),
        };

        return $head . $predicate . ' ' . $body;
    }

    protected function renderUpdateAssignments(MergeClause $clause): string {
        $parts = [];
        foreach ($clause->updates as $column => $value) {
            $parts[] = sprintf(
                '%s = %s',
                $this->dialect->quoteIdentifier($column),
                $this->dialect->renderExpression($value)
            );
        }
        return implode(', ', $parts);
    }

    protected function renderInsertAction(MergeClause $clause): string {
        if ($clause->insertColumns === [] && $clause->insertValues === []) {
            return 'INSERT DEFAULT VALUES';
        }

        $columns = '(' . implode(', ', array_map(
            fn(string $name) => $this->dialect->quoteIdentifier($name),
            $clause->insertColumns
        )) . ')';

        $values = '(' . implode(', ', array_map(
            fn($value) => $this->dialect->renderExpression($value),
            $clause->insertValues
        )) . ')';

        return 'INSERT ' . $columns . ' VALUES ' . $values;
    }

    protected function renderReturning(Merge $component): string {
        $rendered = array_map(
            fn($expression) => $this->dialect->renderExpression($expression),
            $component->returning
        );
        return Str::join($rendered, ', ', ' RETURNING ');
    }
}
