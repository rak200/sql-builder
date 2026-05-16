<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\Collections\Collection;
use Rak200\SqlBuilder\Utils\StringUtils;
use InvalidArgumentException;

/**
 * DDL Table builder.
 *
 * Builds SQL CREATE TABLE and ALTER TABLE statements using a fluent interface.
 * Supports columns, indexes, and various constraints.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
class Table implements ExpressionInterface {
    private bool $alterMode = false;
    private array $alterOperations = [];

    /**
     * Constructor for the Table class.
     *
     * @param string $name Table name.
     * @param Collection<Column>|null $columns Collection of columns.
     * @param Collection<Index>|null $indexes Collection of indexes.
     * @param Collection<Constraint>|null $constraints Collection of constraints.
     */
    public function __construct(
        private string $name,
        private ?Collection $columns = null,
        private ?Collection $indexes = null,
        private ?Collection $constraints = null
    ) {
        $this->columns ??= new Collection(Column::class);
        $this->indexes ??= new Collection(Index::class);
        $this->constraints ??= new Collection(Constraint::class);
    }

    /**
     * Create a new CREATE TABLE statement builder.
     *
     * @param string $name Table name.
     * @return static
     */
    public static function create(string $name): static {
        return new static($name);
    }

    /**
     * Create a new ALTER TABLE statement builder.
     *
     * @param string $name Table name.
     * @return static
     */
    public static function alter(string $name): static {
        $table = new static($name);
        $table->alterMode = true;
        return $table;
    }

    /**
     * Set the table name.
     *
     * @param string $name Table name.
     * @return static
     */
    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    /**
     * Add a column to the table.
     *
     * @param Column $column Column definition.
     * @return static
     */
    public function column(Column $column): static {
        if ($this->alterMode) {
            $this->alterOperations[] = ['type' => 'ADD COLUMN', 'definition' => $column];
            return $this;
        }

        $this->columns[] = $column;
        return $this;
    }

    /**
     * Add multiple columns to the table.
     *
     * @param Column ...$columns Column definitions.
     * @return static
     */
    public function columns(Column ...$columns): static {
        foreach ($columns as $column) {
            if ($this->alterMode) {
                $this->alterOperations[] = ['type' => 'ADD COLUMN', 'definition' => $column];
            } else {
                $this->columns[] = $column;
            }
        }
        return $this;
    }

    /**
     * Add an index to the table.
     *
     * @param Index $index Index definition.
     * @return static
     */
    public function index(Index $index): static {
        if ($this->alterMode) {
            $this->alterOperations[] = ['type' => 'ADD INDEX', 'definition' => $index];
            return $this;
        }

        $this->indexes[] = $index;
        return $this;
    }

    /**
     * Add multiple indexes to the table.
     *
     * @param Index ...$indexes Index definitions.
     * @return static
     */
    public function indexes(Index ...$indexes): static {
        foreach ($indexes as $index) {
            if ($this->alterMode) {
                $this->alterOperations[] = ['type' => 'ADD INDEX', 'definition' => $index];
            } else {
                $this->indexes[] = $index;
            }
        }
        return $this;
    }

    /**
     * Add a column in ALTER mode.
     *
     * @param Column $column Column definition.
     * @return static
     * @throws InvalidArgumentException If not in ALTER mode.
     */
    public function addColumn(Column $column): static {
        $this->ensureAlterMode();
        $this->alterOperations[] = ['type' => 'ADD COLUMN', 'definition' => $column];
        return $this;
    }

    /**
     * Drop a column in ALTER mode.
     *
     * @param string $columnName Name of the column to drop.
     * @return static
     * @throws InvalidArgumentException If not in ALTER mode.
     */
    public function dropColumn(string $columnName): static {
        $this->ensureAlterMode();
        $this->alterOperations[] = ['type' => 'DROP COLUMN', 'name' => $columnName];
        return $this;
    }

    /**
     * Modify a column definition in ALTER mode.
     *
     * @param Column $column Modified column definition.
     * @return static
     * @throws InvalidArgumentException If not in ALTER mode.
     */
    public function modifyColumn(Column $column): static {
        $this->ensureAlterMode();
        $this->alterOperations[] = ['type' => 'MODIFY COLUMN', 'definition' => $column];
        return $this;
    }

    /**
     * Rename a column in ALTER mode.
     *
     * @param string $oldName Current column name.
     * @param string $newName New column name.
     * @return static
     * @throws InvalidArgumentException If not in ALTER mode.
     */
    public function renameColumn(string $oldName, string $newName): static {
        $this->ensureAlterMode();
        $this->alterOperations[] = ['type' => 'RENAME COLUMN', 'old' => $oldName, 'new' => $newName];
        return $this;
    }

    /**
     * Rename the table in ALTER mode.
     *
     * @param string $newName New table name.
     * @return static
     * @throws InvalidArgumentException If not in ALTER mode.
     */
    public function renameTo(string $newName): static {
        $this->ensureAlterMode();
        $this->alterOperations[] = ['type' => 'RENAME TO', 'name' => $newName];
        return $this;
    }

    /**
     * Add a constraint to the table.
     *
     * @param ExpressionInterface $constraint Constraint definition.
     * @return static
     */
    public function constraint(ExpressionInterface $constraint): static {
        if ($this->alterMode) {
            $this->alterOperations[] = ['type' => 'ADD CONSTRAINT', 'definition' => $constraint];
            return $this;
        }

        $this->constraints[] = $constraint;
        return $this;
    }

    /**
     * Add multiple constraints to the table.
     *
     * @param ExpressionInterface ...$constraints Constraint definitions.
     * @return static
     */
    public function constraints(ExpressionInterface ...$constraints): static {
        foreach ($constraints as $constraint) {
            if ($this->alterMode) {
                $this->alterOperations[] = ['type' => 'ADD CONSTRAINT', 'definition' => $constraint];
            } else {
                $this->constraints[] = $constraint;
            }
        }
        return $this;
    }

    /**
     * Drop a constraint in ALTER mode.
     *
     * @param string $constraintName Name of the constraint to drop.
     * @return static
     * @throws InvalidArgumentException If not in ALTER mode.
     */
    public function dropConstraint(string $constraintName): static {
        $this->ensureAlterMode();
        $this->alterOperations[] = ['type' => 'DROP CONSTRAINT', 'name' => $constraintName];
        return $this;
    }

    /**
     * Convert the table definition to SQL string representation.
     *
     * @return string The SQL representation of the table.
     */
    public function __toString(): string {
        if ($this->alterMode) {
            return $this->buildAlterSql();
        }

        $parts = [];

        foreach ($this->columns as $column) {
            $parts[] = (string) $column;
        }

        foreach ($this->constraints as $constraint) {
            $parts[] = (string) $constraint;
        }

        $sql = sprintf('CREATE TABLE "%s" (%s)', Expression::quoteIdentifier($this->name), implode(', ', $parts));
        $sql .= StringUtils::join($this->indexes->toArray(), ' ', ' ');

        return $sql;
    }

    /**
     * Build the ALTER TABLE SQL statement.
     *
     * @return string The ALTER TABLE SQL statement.
     * @throws InvalidArgumentException If no ALTER TABLE operations are defined.
     */
    private function buildAlterSql(): string {
        if (empty($this->alterOperations)) {
            throw new InvalidArgumentException('No ALTER TABLE operations defined.');
        }

        $sql = sprintf('ALTER TABLE "%s"', Expression::quoteIdentifier($this->name));
        $operations = array_map(fn(array $operation) => $this->buildAlterOperation($operation), $this->alterOperations);
        return $sql . ' ' . implode(', ', $operations);
    }

    /**
     * Build a single ALTER TABLE operation.
     *
     * @param array $operation The operation details.
     * @return string The SQL representation of the operation.
     * @throws InvalidArgumentException If the operation type is unsupported.
     * @todo string interpolation with Expression::quoteIdentifier() for column and constraint names and remove quotes.
     */
    private function buildAlterOperation(array $operation): string {
        return match ($operation['type']) {
            'ADD COLUMN'     => sprintf('ADD COLUMN %s', $operation['definition']),
            'DROP COLUMN'    => sprintf('DROP COLUMN "%s"', Expression::quoteIdentifier($operation['name'])),
            'MODIFY COLUMN'  => sprintf('MODIFY COLUMN %s', $operation['definition']),
            'RENAME COLUMN'  => sprintf('RENAME COLUMN "%s" TO "%s"', Expression::quoteIdentifier($operation['old']), Expression::quoteIdentifier($operation['new'])),
            'RENAME TO'      => sprintf('RENAME TO %s', Expression::quoteIdentifier($operation['name'])),
            'ADD CONSTRAINT' => sprintf('ADD %s', $operation['definition']),
            'DROP CONSTRAINT'=> sprintf('DROP CONSTRAINT "%s"', Expression::quoteIdentifier($operation['name'])),
            'ADD INDEX'      => sprintf('ADD %s', $operation['definition']),
            default          => throw new InvalidArgumentException('Unsupported ALTER TABLE operation: ' . $operation['type']),
        };
    }

    /**
     * Ensure the table is in ALTER mode.
     *
     * @return void
     * @throws InvalidArgumentException If not in ALTER mode.
     */
    private function ensureAlterMode(): void {
        if (!$this->alterMode) {
            throw new InvalidArgumentException('This method is only available in alter mode. Use Table::alter().');
        }
    }
}
