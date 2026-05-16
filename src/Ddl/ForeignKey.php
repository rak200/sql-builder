<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\Enum\ForeignKeyAction;
use Rak200\SqlBuilder\Utils\StringUtils;

/**
 * DDL Foreign Key constraint builder.
 *
 * Builds SQL FOREIGN KEY constraint definitions with support for referential actions.
 * Use {@see ForeignKeyAction} to specify ON DELETE and ON UPDATE behaviors.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
class ForeignKey extends Constraint {

    /** @var string[] $columns Local column names */
    private array $columns;
    /** @var string $referenceTable Referenced table name */
    private string $referenceTable;
    /** @var string[] $referenceColumns Referenced column names */
    private array $referenceColumns;
    /** @var ForeignKeyAction|null $onDelete Action on delete */
    private ?ForeignKeyAction $onDelete;
    /** @var ForeignKeyAction|null $onUpdate Action on update */
    private ?ForeignKeyAction $onUpdate;

    /**
     * @param string $name Name of the foreign key constraint.
     * @param string[] $columns Local column names.
     * @param string $referenceTable Referenced table name.
     * @param string[] $referenceColumns Referenced column names.
     * @param ForeignKeyAction|null $onDelete Action on delete.
     * @param ForeignKeyAction|null $onUpdate Action on update.
     */
    public function __construct(
        string $name,
        array $columns = [],
        string $referenceTable = '',
        array $referenceColumns = [],
        ?ForeignKeyAction $onDelete = null,
        ?ForeignKeyAction $onUpdate = null
    ) {
        parent::__construct($name);
        $this->columns = $columns;
        $this->referenceTable = $referenceTable;
        $this->referenceColumns = $referenceColumns;
        $this->onDelete = $onDelete;
        $this->onUpdate = $onUpdate;
    }

    /**
     * Create a new foreign key constraint builder.
     *
     * @param string $name Constraint name.
     * @return static
     */
    public static function create(string $name): static {
        return new static($name);
    }

    /**
     * Set the local columns for the foreign key.
     *
     * @param array $columns List of column names.
     * @return static
     */
    public function columns(array $columns): static {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Set the referenced table and columns.
     *
     * @param string $table Referenced table name.
     * @param array $columns Referenced column names.
     * @return static
     */
    public function references(string $table, array $columns): static {
        $this->referenceTable = $table;
        $this->referenceColumns = $columns;
        return $this;
    }

    /**
     * Set the ON DELETE referential action.
     *
     * @param ForeignKeyAction $action The action to perform when the parent row is deleted.
     * @return static
     */
    public function onDelete(ForeignKeyAction $action): static {
        $this->onDelete = $action;
        return $this;
    }

    /**
     * Set the ON UPDATE referential action.
     *
     * @param ForeignKeyAction $action The action to perform when the parent row is updated.
     * @return static
     */
    public function onUpdate(ForeignKeyAction $action): static {
        $this->onUpdate = $action;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        $sql = 'FOREIGN KEY';

        if ($this->name) {
            $sql = sprintf('CONSTRAINT "%s" FOREIGN KEY', $this->name);
        }

        $sql .= StringUtils::join(
            array_map(fn(string $column) => sprintf('"%s"', $column), $this->columns),
            ', ', ' (', ')'
        );

        if ($this->referenceTable) {
            $sql .= StringUtils::join(
                array_map(fn(string $column) => sprintf('"%s"', $column), $this->referenceColumns),
                ', ',
                sprintf(' REFERENCES "%s" (', $this->referenceTable),
                ')'
            );
        }

        if ($this->onDelete !== null) {
            $sql .= ' ON DELETE ' . $this->onDelete->value;
        }

        if ($this->onUpdate !== null) {
            $sql .= ' ON UPDATE ' . $this->onUpdate->value;
        }

        return $sql;
    }
}
