<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Utils\StringUtils;

/**
 * DDL Unique constraint builder.
 *
 * Builds SQL UNIQUE constraint definitions for ensuring column value uniqueness.
 * Supports single and composite unique constraints.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UniqueKey extends Constraint {

    /** @var string[] $columns Column names that must be unique */
    private array $columns;

    /**
     * @param string $name Name of the unique constraint.
     * @param string[] $columns Column names that must be unique.
     */
    public function __construct(string $name = '', array $columns = []) {
        parent::__construct($name);
        $this->columns = $columns;
    }

    /**
     * Create a new unique constraint builder.
     *
     * @param string|null $name Constraint name (optional).
     * @return static
     */
    public static function create(?string $name = null): static {
        return new static($name ?? '');
    }

    /**
     * Set the columns that must be unique.
     *
     * @param array $columns List of column names.
     * @return static
     */
    public function columns(array $columns): static {
        $this->columns = $columns;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        $sql = 'UNIQUE';

        if ($this->name) {
            $sql = sprintf('CONSTRAINT "%s" UNIQUE', $this->name);
        }

        $sql .= StringUtils::join(
            array_map(fn(string $column) => sprintf('"%s"', $column), $this->columns),
            ', ', ' (', ')'
        );

        return $sql;
    }
}
