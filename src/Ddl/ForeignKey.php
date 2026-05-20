<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\Enum\ForeignKeyAction;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * DDL Foreign Key constraint builder.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class ForeignKey extends Constraint {

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
        public private(set) array $columns = [],
        public private(set) string $referenceTable = '',
        public private(set) array $referenceColumns = [],
        public private(set) ?ForeignKeyAction $onDelete = null,
        public private(set) ?ForeignKeyAction $onUpdate = null
    ) {
        parent::__construct($name);
    }

    public static function create(string $name): static {
        return new static($name);
    }

    public function columns(array $columns): static {
        $this->columns = $columns;
        return $this;
    }

    public function references(string $table, array $columns): static {
        $this->referenceTable = $table;
        $this->referenceColumns = $columns;
        return $this;
    }

    public function onDelete(ForeignKeyAction $action): static {
        $this->onDelete = $action;
        return $this;
    }

    public function onUpdate(ForeignKeyAction $action): static {
        $this->onUpdate = $action;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderForeignKey($this);
    }
}
