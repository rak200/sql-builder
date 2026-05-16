<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Utils\StringUtils;

/**
 * DDL Primary Key constraint builder.
 *
 * Builds SQL PRIMARY KEY constraint definitions for table creation and modification.
 * Supports single and composite primary keys.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
class PrimaryKey extends Constraint {

    /** @var string[] $columns Column names that form the primary key */
    private array $columns;

    /**
     * @param string $name Name of the primary key constraint.
     * @param string[] $columns Column names that form the primary key.
     */
    public function __construct(string $name = '', array $columns = []) {
        parent::__construct($name);
        $this->columns = $columns;
    }

    /**
     * Create a new primary key constraint builder.
     *
     * @param string|null $name Constraint name (optional).
     * @return static
     */
    public static function create(?string $name = null): static {
        return new static($name ?? '');
    }

    /**
     * Set the columns that form the primary key.
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
        $sql = 'PRIMARY KEY';

        if ($this->name) {
            $sql = sprintf('CONSTRAINT "%s" PRIMARY KEY', $this->name);
        }

        $sql .= StringUtils::join(
            array_map(fn(string $column) => sprintf('"%s"', $column), $this->columns),
            ', ', ' (', ')'
        );

        return $sql;
    }
}
