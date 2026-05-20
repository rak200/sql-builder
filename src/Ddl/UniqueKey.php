<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * DDL Unique constraint builder.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UniqueKey extends Constraint {

    /**
     * @param string $name Name of the unique constraint.
     * @param string[] $columns Column names that must be unique.
     */
    public function __construct(string $name = '', public private(set) array $columns = []) {
        parent::__construct($name);
    }

    public static function create(?string $name = null): static {
        return new static($name ?? '');
    }

    public function columns(array $columns): static {
        $this->columns = $columns;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderUniqueKey($this);
    }
}
