<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Ddl;

use InvalidArgumentException;
use Rak200\SqlBuilder\Ddl\Table;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\SqlBuilder\Utils\StringUtils;

/**
 * Renders a {@see Table} as a CREATE TABLE or ALTER TABLE statement.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class TableRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Table $component): string {
        if ($component->alterMode) {
            return $this->renderAlter($component);
        }

        $parts = [];

        foreach ($component->columns as $column) {
            $parts[] = $this->dialect->renderColumn($column);
        }

        foreach ($component->constraints as $constraint) {
            $parts[] = $this->dialect->renderExpression($constraint);
        }

        $sql = sprintf(
            'CREATE TABLE "%s" (%s)',
            $this->dialect->quoteIdentifier($this->dialect->resolveTableName($component->name)),
            implode(', ', $parts)
        );

        $indexes = [];
        foreach ($component->indexes as $index) {
            $indexes[] = $this->dialect->renderIndex($index);
        }
        $sql .= StringUtils::join($indexes, ' ', ' ');

        return $sql;
    }

    protected function renderAlter(Table $component): string {
        if ($component->alterOperations === []) {
            throw new InvalidArgumentException('No ALTER TABLE operations defined.');
        }

        $sql = sprintf(
            'ALTER TABLE "%s"',
            $this->dialect->quoteIdentifier($this->dialect->resolveTableName($component->name))
        );
        $operations = array_map(
            fn(array $operation) => $this->renderAlterOperation($operation),
            $component->alterOperations
        );

        return $sql . ' ' . implode(', ', $operations);
    }

    protected function renderAlterOperation(array $operation): string {
        return match ($operation['type']) {
            'ADD COLUMN'      => sprintf('ADD COLUMN %s', $this->dialect->renderColumn($operation['definition'])),
            'DROP COLUMN'     => sprintf('DROP COLUMN "%s"', $this->dialect->quoteIdentifier($operation['name'])),
            'MODIFY COLUMN'   => sprintf('MODIFY COLUMN %s', $this->dialect->renderColumn($operation['definition'])),
            'RENAME COLUMN'   => sprintf(
                'RENAME COLUMN "%s" TO "%s"',
                $this->dialect->quoteIdentifier($operation['old']),
                $this->dialect->quoteIdentifier($operation['new'])
            ),
            'RENAME TO'       => sprintf(
                'RENAME TO %s',
                $this->dialect->quoteIdentifier($this->dialect->resolveTableName($operation['name']))
            ),
            'ADD CONSTRAINT'  => sprintf('ADD %s', $this->dialect->renderExpression($operation['definition'])),
            'DROP CONSTRAINT' => sprintf('DROP CONSTRAINT "%s"', $this->dialect->quoteIdentifier($operation['name'])),
            'ADD INDEX'       => sprintf('ADD %s', $this->dialect->renderIndex($operation['definition'])),
            default           => throw new InvalidArgumentException('Unsupported ALTER TABLE operation: ' . $operation['type']),
        };
    }
}
