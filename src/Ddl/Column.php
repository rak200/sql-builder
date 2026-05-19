<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Utils\StringUtils;

/**
 * DDL Column builder.
 *
 * Builds SQL column definitions with support for types, lengths, constraints, and default values.
 * Used in CREATE TABLE statements with a fluent interface.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Column implements ExpressionInterface {

    /**
     * Constructor for the Column class.
     *
     * @param string $name Column name.
     * @param DataType $type Column data type.
     * @param int|null $length Length of the column (if applicable).
     * @param bool $nullable Whether the column allows NULL values.
     * @param mixed $default Default value for the column.
     * @param bool $autoIncrement Whether the column is AUTO_INCREMENT.
     * @param bool $primaryKey Whether the column is a primary key.
     */
    public function __construct(
        private string $name,
        private DataType $type,
        private ?int $length = null,
        private bool $nullable = true,
        private mixed $default = null,
        private bool $autoIncrement = false,
        private bool $primaryKey = false
    ) {}

    /**
     * Create a new column builder.
     *
     * @param string $name Column name.
     * @param DataType $type Column data type.
     * @return static
     */
    public static function create(string $name, DataType $type): static {
        return new static($name, $type);
    }

    /**
     * Set the column name.
     *
     * @param string $name Column name.
     * @return static
     */
    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    /**
     * Set the column data type.
     *
     * @param DataType $type Column data type.
     * @return static
     */
    public function type(DataType $type): static {
        $this->type = $type;
        return $this;
    }

    /**
     * Set the column length.
     *
     * @param int|null $length Column length.
     * @return static
     */
    public function length(?int $length): static {
        $this->length = $length;
        return $this;
    }

    /**
     * Set whether the column allows NULL values.
     *
     * @param bool $nullable Whether to allow NULL values.
     * @return static
     */
    public function nullable(bool $nullable = true): static {
        $this->nullable = $nullable;
        return $this;
    }

    /**
     * Set the default value for the column.
     *
     * @param mixed $default Default value.
     * @return static
     */
    public function default(mixed $default): static {
        if (!$default instanceof ExpressionInterface) {
            $default = Expression::value($default);
        }
        $this->default = $default;
        return $this;
    }

    /**
     * Set the AUTO_INCREMENT attribute.
     *
     * @param bool $autoIncrement Whether to enable AUTO_INCREMENT.
     * @return static
     */
    public function autoIncrement(bool $autoIncrement = true): static {
        $this->autoIncrement = $autoIncrement;
        return $this;
    }

    /**
     * Set a sequence as the value source for this column's DEFAULT.
     *
     * Stores the sequence's NEXTVAL expression so `__toString()` emits
     * `DEFAULT NEXTVAL('sequence_name')` instead of a quoted literal.
     *
     * @param Sequence $sequence The sequence to draw values from.
     * @return static
     */
    public function sequence(Sequence $sequence): static {
        $this->default = $sequence->nextVal();
        return $this;
    }

    /**
     * Set the PRIMARY KEY constraint.
     *
     * @param bool $primaryKey Whether this column is a primary key.
     * @return static
     */
    public function primaryKey(bool $primaryKey = true): static {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    /**
     * Convert the column definition to SQL string representation.
     *
     * @return string The SQL representation of the column.
     */
    public function __toString(): string {
        $name = Expression::quoteIdentifier($this->name);

        $sql = "$name {$this->type->value}";
        $sql .= StringUtils::wrap((string) $this->length, '(', ')');
        $sql .= $this->nullable ? ' NULL' : ' NOT NULL';

        if ($this->default !== null) {
            $sql .= " DEFAULT {$this->default}";
        }

        if ($this->autoIncrement) {
            $sql .= ' AUTO_INCREMENT';
        }

        if ($this->primaryKey) {
            $sql .= ' PRIMARY KEY';
        }

        return $sql;
    }
}
