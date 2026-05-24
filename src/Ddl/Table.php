<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use InvalidArgumentException;
use Rak200\Collections\Vector;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * DDL Table builder.
 *
 * Builds SQL `CREATE TABLE`, `ALTER TABLE`, `DROP TABLE` and `TRUNCATE TABLE`
 * statements using a fluent interface. CREATE-mode columns, indexes, and
 * constraints are stored in vectors; ALTER mode collects an ordered list of
 * operations; DROP and TRUNCATE carry the relevant SQL modifiers
 * (`IF EXISTS`, `CASCADE`/`RESTRICT`, `RESTART IDENTITY`).
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Table implements ExpressionInterface {

    public const string MODE_CREATE   = 'CREATE';
    public const string MODE_ALTER    = 'ALTER';
    public const string MODE_DROP     = 'DROP';
    public const string MODE_TRUNCATE = 'TRUNCATE';

    /** @var string The statement mode (CREATE/ALTER/DROP/TRUNCATE). */
    public private(set) string $mode = self::MODE_CREATE;

    /** @var array<int, array<string, mixed>> ALTER TABLE operations. */
    public private(set) array $alterOperations = [];

    /** @var Vector<Column> Table columns (CREATE mode). */
    public readonly Vector $columns;

    /** @var Vector<Index> Table indexes (CREATE mode). */
    public readonly Vector $indexes;

    /** @var Vector<ExpressionInterface> Table constraints (CREATE mode). */
    public readonly Vector $constraints;

    /** @var bool `IF EXISTS` modifier (DROP) / `IF NOT EXISTS` (reserved for CREATE). */
    public private(set) bool $ifExists = false;

    /** @var bool `CASCADE` modifier (DROP / TRUNCATE). */
    public private(set) bool $cascade = false;

    /** @var bool `RESTRICT` modifier (DROP / TRUNCATE). */
    public private(set) bool $restrict = false;

    /** @var bool `RESTART IDENTITY` modifier (TRUNCATE). */
    public private(set) bool $restartIdentity = false;

    /** @var bool `CONTINUE IDENTITY` modifier (TRUNCATE). */
    public private(set) bool $continueIdentity = false;

    /**
     * @param string $name Table name.
     * @param Vector<Column>|null $columns Initial columns.
     * @param Vector<Index>|null $indexes Initial indexes.
     * @param Vector<ExpressionInterface>|null $constraints Initial constraints.
     */
    public function __construct(
        public private(set) string $name,
        ?Vector $columns = null,
        ?Vector $indexes = null,
        ?Vector $constraints = null
    ) {
        $this->columns     = $columns     ?? new Vector(Column::class);
        $this->indexes     = $indexes     ?? new Vector(Index::class);
        $this->constraints = $constraints ?? new Vector(ExpressionInterface::class);
    }

    /** Start a `CREATE TABLE` statement. */
    public static function create(string $name): static {
        return new static($name);
    }

    /** Start an `ALTER TABLE` statement. */
    public static function alter(string $name): static {
        $table = new static($name);
        $table->mode = self::MODE_ALTER;
        return $table;
    }

    /** Start a `DROP TABLE` statement. */
    public static function drop(string $name): static {
        $table = new static($name);
        $table->mode = self::MODE_DROP;
        return $table;
    }

    /** Start a `TRUNCATE TABLE` statement. */
    public static function truncate(string $name): static {
        $table = new static($name);
        $table->mode = self::MODE_TRUNCATE;
        return $table;
    }

    /** Rename the table (does not change the rendered statement type). */
    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    /** Add a column. In `CREATE` mode it is appended to the column list; in `ALTER` mode it queues an `ADD COLUMN`. */
    public function column(Column $column): static {
        if ($this->mode === self::MODE_ALTER) {
            $this->alterOperations[] = ['type' => 'ADD COLUMN', 'definition' => $column];
            return $this;
        }

        $this->columns[] = $column;
        return $this;
    }

    /** Add multiple columns at once (see {@see column()}). */
    public function columns(Column ...$columns): static {
        foreach ($columns as $column) {
            $this->column($column);
        }
        return $this;
    }

    /** Add an inline index. In `ALTER` mode it queues `ADD INDEX`. */
    public function index(Index $index): static {
        if ($this->mode === self::MODE_ALTER) {
            $this->alterOperations[] = ['type' => 'ADD INDEX', 'definition' => $index];
            return $this;
        }

        $this->indexes[] = $index;
        return $this;
    }

    /** Add multiple indexes at once. */
    public function indexes(Index ...$indexes): static {
        foreach ($indexes as $index) {
            $this->index($index);
        }
        return $this;
    }

    /** Queue an `ADD COLUMN` (ALTER mode only). */
    public function addColumn(Column $column): static {
        $this->ensureMode(self::MODE_ALTER, 'alter');
        $this->alterOperations[] = ['type' => 'ADD COLUMN', 'definition' => $column];
        return $this;
    }

    /** Queue a `DROP COLUMN` (ALTER mode only). */
    public function dropColumn(string $columnName): static {
        $this->ensureMode(self::MODE_ALTER, 'alter');
        $this->alterOperations[] = ['type' => 'DROP COLUMN', 'name' => $columnName];
        return $this;
    }

    /** Queue a `MODIFY COLUMN` with a new column definition (ALTER mode only). */
    public function modifyColumn(Column $column): static {
        $this->ensureMode(self::MODE_ALTER, 'alter');
        $this->alterOperations[] = ['type' => 'MODIFY COLUMN', 'definition' => $column];
        return $this;
    }

    /** Queue a `RENAME COLUMN old TO new` (ALTER mode only). */
    public function renameColumn(string $oldName, string $newName): static {
        $this->ensureMode(self::MODE_ALTER, 'alter');
        $this->alterOperations[] = ['type' => 'RENAME COLUMN', 'old' => $oldName, 'new' => $newName];
        return $this;
    }

    /** Queue a `RENAME TO new_name` for the whole table (ALTER mode only). */
    public function renameTo(string $newName): static {
        $this->ensureMode(self::MODE_ALTER, 'alter');
        $this->alterOperations[] = ['type' => 'RENAME TO', 'name' => $newName];
        return $this;
    }

    /** Add a constraint (PK/UK/FK/CHECK). In ALTER mode queues `ADD CONSTRAINT`. */
    public function constraint(ExpressionInterface $constraint): static {
        if ($this->mode === self::MODE_ALTER) {
            $this->alterOperations[] = ['type' => 'ADD CONSTRAINT', 'definition' => $constraint];
            return $this;
        }

        $this->constraints[] = $constraint;
        return $this;
    }

    /** Add multiple constraints at once. */
    public function constraints(ExpressionInterface ...$constraints): static {
        foreach ($constraints as $constraint) {
            $this->constraint($constraint);
        }
        return $this;
    }

    /** Queue a `DROP CONSTRAINT name` (ALTER mode only). */
    public function dropConstraint(string $constraintName): static {
        $this->ensureMode(self::MODE_ALTER, 'alter');
        $this->alterOperations[] = ['type' => 'DROP CONSTRAINT', 'name' => $constraintName];
        return $this;
    }

    /**
     * `IF EXISTS` guard for `DROP TABLE`.
     */
    public function ifExists(bool $ifExists = true): static {
        $this->ifExists = $ifExists;
        return $this;
    }

    /**
     * `CASCADE` modifier (DROP / TRUNCATE). Clears any prior `RESTRICT`.
     */
    public function cascade(): static {
        $this->cascade  = true;
        $this->restrict = false;
        return $this;
    }

    /**
     * `RESTRICT` modifier (DROP / TRUNCATE). Clears any prior `CASCADE`.
     */
    public function restrict(): static {
        $this->restrict = true;
        $this->cascade  = false;
        return $this;
    }

    /**
     * Append `RESTART IDENTITY` to a `TRUNCATE TABLE` statement.
     * Mutually exclusive with {@see continueIdentity()}.
     */
    public function restartIdentity(): static {
        $this->restartIdentity  = true;
        $this->continueIdentity = false;
        return $this;
    }

    /**
     * Append `CONTINUE IDENTITY` to a `TRUNCATE TABLE` statement.
     * Mutually exclusive with {@see restartIdentity()}.
     */
    public function continueIdentity(): static {
        $this->continueIdentity = true;
        $this->restartIdentity  = false;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderTable($this);
    }

    /** Render this statement with a specific dialect. */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderTable($this);
    }

    private function ensureMode(string $mode, string $factoryHint): void {
        if ($this->mode !== $mode) {
            throw new InvalidArgumentException(
                "This method is only available in $mode mode. Use Table::$factoryHint()."
            );
        }
    }
}
