<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use InvalidArgumentException;
use Rak200\Collections\Vector;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * DDL Table builder.
 *
 * Builds SQL CREATE TABLE and ALTER TABLE statements using a fluent interface.
 * Supports columns, indexes, and various constraints.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Table implements ExpressionInterface {

    /** @var bool Whether the builder is in ALTER TABLE mode. */
    public private(set) bool $alterMode = false;

    /** @var array<int, array<string, mixed>> ALTER TABLE operations. */
    public private(set) array $alterOperations = [];

    /** @var Vector<Column> Table columns (CREATE mode). */
    public readonly Vector $columns;

    /** @var Vector<Index> Table indexes (CREATE mode). */
    public readonly Vector $indexes;

    /** @var Vector<ExpressionInterface> Table constraints (CREATE mode). */
    public readonly Vector $constraints;

    /**
     * @param string $name Table name.
     * @param Vector<Column>|null $columns Initial columns.
     * @param Vector<Index>|null $indexes Initial indexes.
     * @param Vector<ExpressionInterface>|null $constraints Initial constraints.
     */
    public function __construct(
        public private(set) string $name,
        ?Vector $columns = null,
        ?Vector $indexes = null,
        ?Vector $constraints = null
    ) {
        $this->columns     = $columns     ?? new Vector(Column::class);
        $this->indexes     = $indexes     ?? new Vector(Index::class);
        $this->constraints = $constraints ?? new Vector(ExpressionInterface::class);
    }

    public static function create(string $name): static {
        return new static($name);
    }

    public static function alter(string $name): static {
        $table = new static($name);
        $table->alterMode = true;
        return $table;
    }

    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    public function column(Column $column): static {
        if ($this->alterMode) {
            $this->alterOperations[] = ['type' => 'ADD COLUMN', 'definition' => $column];
            return $this;
        }

        $this->columns[] = $column;
        return $this;
    }

    public function columns(Column ...$columns): static {
        foreach ($columns as $column) {
            $this->column($column);
        }
        return $this;
    }

    public function index(Index $index): static {
        if ($this->alterMode) {
            $this->alterOperations[] = ['type' => 'ADD INDEX', 'definition' => $index];
            return $this;
        }

        $this->indexes[] = $index;
        return $this;
    }

    public function indexes(Index ...$indexes): static {
        foreach ($indexes as $index) {
            $this->index($index);
        }
        return $this;
    }

    public function addColumn(Column $column): static {
        $this->ensureAlterMode();
        $this->alterOperations[] = ['type' => 'ADD COLUMN', 'definition' => $column];
        return $this;
    }

    public function dropColumn(string $columnName): static {
        $this->ensureAlterMode();
        $this->alterOperations[] = ['type' => 'DROP COLUMN', 'name' => $columnName];
        return $this;
    }

    public function modifyColumn(Column $column): static {
        $this->ensureAlterMode();
        $this->alterOperations[] = ['type' => 'MODIFY COLUMN', 'definition' => $column];
        return $this;
    }

    public function renameColumn(string $oldName, string $newName): static {
        $this->ensureAlterMode();
        $this->alterOperations[] = ['type' => 'RENAME COLUMN', 'old' => $oldName, 'new' => $newName];
        return $this;
    }

    public function renameTo(string $newName): static {
        $this->ensureAlterMode();
        $this->alterOperations[] = ['type' => 'RENAME TO', 'name' => $newName];
        return $this;
    }

    public function constraint(ExpressionInterface $constraint): static {
        if ($this->alterMode) {
            $this->alterOperations[] = ['type' => 'ADD CONSTRAINT', 'definition' => $constraint];
            return $this;
        }

        $this->constraints[] = $constraint;
        return $this;
    }

    public function constraints(ExpressionInterface ...$constraints): static {
        foreach ($constraints as $constraint) {
            $this->constraint($constraint);
        }
        return $this;
    }

    public function dropConstraint(string $constraintName): static {
        $this->ensureAlterMode();
        $this->alterOperations[] = ['type' => 'DROP CONSTRAINT', 'name' => $constraintName];
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderTable($this);
    }

    /**
     * Render this table with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderTable($this);
    }

    private function ensureAlterMode(): void {
        if (!$this->alterMode) {
            throw new InvalidArgumentException('This method is only available in alter mode. Use Table::alter().');
        }
    }
}
