<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Dml;

use InvalidArgumentException;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\Utils\Str;

/**
 * Renders an {@see Insert} statement: INSERT INTO, columns, VALUES or SELECT,
 * ON DUPLICATE KEY UPDATE, and RETURNING.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class InsertRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Insert $component): string {
        if ($component->table === '') {
            throw new InvalidArgumentException('INSERT requires a target table; call into().');
        }
        if ($component->rows === [] && $component->select === null) {
            throw new InvalidArgumentException('INSERT requires VALUES or a SELECT source.');
        }

        $sql  = sprintf(
            'INSERT INTO %s',
            $this->dialect->quoteIdentifier($this->dialect->resolveTableName($component->table))
        );
        $sql .= $this->renderColumnList($component);
        $sql .= $component->select !== null
            ? ' ' . $this->dialect->renderSelect($component->select)
            : ' VALUES ' . $this->renderRows($component);
        $sql .= $this->renderOnDuplicateKeyUpdate($component);
        $sql .= $this->renderReturning($component);

        return $sql;
    }

    protected function renderColumnList(Insert $component): string {
        return Str::join(
            array_map(
                fn(string $name) => $this->dialect->quoteIdentifier($name),
                $component->columns
            ),
            ', ',
            ' (',
            ')'
        );
    }

    protected function renderRows(Insert $component): string {
        $tuples = array_map(
            fn(array $row): string => '(' . implode(', ', array_map(
                fn($value) => $this->dialect->renderExpression($value),
                $row
            )) . ')',
            $component->rows
        );

        return implode(', ', $tuples);
    }

    protected function renderOnDuplicateKeyUpdate(Insert $component): string {
        if ($component->onDuplicateKey === []) {
            return '';
        }

        $parts = [];
        foreach ($component->onDuplicateKey as $column => $value) {
            $parts[] = sprintf(
                '%s = %s',
                $this->dialect->quoteIdentifier($column),
                $this->dialect->renderExpression($value)
            );
        }

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $parts);
    }

    protected function renderReturning(Insert $component): string {
        $rendered = array_map(
            fn($expression) => $this->dialect->renderExpression($expression),
            $component->returning
        );
        return Str::join($rendered, ', ', ' RETURNING ');
    }
}
