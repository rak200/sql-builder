<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * DDL Primary Key constraint builder.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class PrimaryKey extends Constraint {

    /**
     * @param string $name Name of the primary key constraint.
     * @param string[] $columns Column names that form the primary key.
     */
    public function __construct(string $name = '', public private(set) array $columns = []) {
        parent::__construct($name);
    }

    /** Create a primary key constraint with an optional name. */
    public static function create(?string $name = null): static {
        return new static($name ?? '');
    }

    /**
     * Set the columns that form the primary key (single column or composite).
     *
     * @param array<int, string> $columns
     */
    public function columns(array $columns): static {
        $this->columns = $columns;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderPrimaryKey($this);
    }
}
