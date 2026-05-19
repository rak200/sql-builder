<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\ExpressionInterface;

/**
 * DDL Index builder.
 *
 * Builds SQL index definitions for table columns with support for unique indexes.
 * Used in CREATE TABLE statements with a fluent interface.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Index implements ExpressionInterface {
    /**
     * Constructor for the Index class.
     *
     * @param string $name Name of the index.
     * @param string $table Name of the table the index belongs to.
     * @param array $columns List of column names included in the index.
     * @param bool $unique Whether the index is unique.
     */
    public function __construct(
        private string $name,
        private string $table = '',
        private array $columns = [],
        private bool $unique = false
    ) {}

    /**
     * Create a new index builder.
     *
     * @param string $name Index name.
     * @return static
     */
    public static function create(string $name): static {
        return new static($name);
    }

    /**
     * Set the index name.
     *
     * @param string $name Index name.
     * @return static
     */
    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the table name for the index.
     *
     * @param string $table Table name.
     * @return static
     */
    public function table(string $table): static {
        $this->table = $table;
        return $this;
    }

    /**
     * Set the columns included in the index.
     *
     * @param array $columns List of column names.
     * @return static
     */
    public function columns(array $columns): static {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Mark the index as unique.
     *
     * @return static
     */
    public function unique(): static {
        $this->unique = true;
        return $this;
    }

    /**
     * Convert the index definition to SQL string representation.
     *
     * @return string The SQL representation of the index.
     */
    public function __toString(): string {
        $unique = $this->unique ? 'UNIQUE ' : '';
        $columns = implode(', ', array_map(fn(string $column) => sprintf('"%s"', $column), $this->columns));
        return sprintf('CREATE %sINDEX "%s" ON "%s" (%s)', $unique, $this->name, $this->table, $columns);
    }
}
