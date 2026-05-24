<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\Expr as Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * DDL Column builder.
 *
 * Builds SQL column definitions with support for types, lengths, constraints,
 * and default values. Used in CREATE TABLE statements with a fluent interface.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Column implements ExpressionInterface {

    /**
     * @param string $name Column name.
     * @param DataType $type Column data type.
     * @param int|null $length Length of the column (if applicable).
     * @param bool $nullable Whether the column allows NULL values.
     * @param ExpressionInterface|null $default Default value expression.
     * @param bool $autoIncrement Whether the column is AUTO_INCREMENT.
     * @param bool $primaryKey Whether the column is a primary key.
     */
    public function __construct(
        public private(set) string $name,
        public private(set) DataType $type,
        public private(set) ?int $length = null,
        public private(set) bool $nullable = true,
        public private(set) ?ExpressionInterface $default = null,
        public private(set) bool $autoIncrement = false,
        public private(set) bool $primaryKey = false
    ) {}

    /** Create a column definition with the given name and data type. */
    public static function create(string $name, DataType $type): static {
        return new static($name, $type);
    }

    /** Rename the column. */
    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    /** Change the column data type. */
    public function type(DataType $type): static {
        $this->type = $type;
        return $this;
    }

    /** Set the explicit length for variable-width types (e.g. `VARCHAR(255)`). */
    public function length(?int $length): static {
        $this->length = $length;
        return $this;
    }

    /** Mark the column nullable (default true). Pass false to emit `NOT NULL`. */
    public function nullable(bool $nullable = true): static {
        $this->nullable = $nullable;
        return $this;
    }

    /**
     * Set the column DEFAULT value.
     *
     * Scalars are wrapped in {@see Expression::val()}; expressions (e.g.
     * `Expr::raw('NOW()')`, sequence references) pass through verbatim.
     */
    public function default(mixed $default): static {
        $this->default = $default instanceof ExpressionInterface
            ? $default
            : Expression::val($default);
        return $this;
    }

    /** Mark the column `AUTO_INCREMENT` (MySQL / MariaDB) / `SERIAL` (Postgres). */
    public function autoIncrement(bool $autoIncrement = true): static {
        $this->autoIncrement = $autoIncrement;
        return $this;
    }

    /**
     * Set a sequence as the value source for this column's DEFAULT.
     */
    public function sequence(Sequence $sequence): static {
        $this->default = $sequence->nextVal();
        return $this;
    }

    /** Mark the column as the inline `PRIMARY KEY`. */
    public function primaryKey(bool $primaryKey = true): static {
        $this->primaryKey = $primaryKey;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderColumn($this);
    }

    /**
     * Render this column with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderColumn($this);
    }
}
