<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dml;

use InvalidArgumentException;
use Rak200\SqlBuilder\Common\Expr as Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Prepared\PreparedStatement;

/**
 * SQL INSERT statement builder.
 *
 * Supports literal VALUES (single or multi-row), `INSERT ... SELECT`, plus
 * dialect extensions:
 * - **ON DUPLICATE KEY UPDATE** (MySQL / MariaDB) for upserts
 * - **RETURNING** (PostgreSQL, MariaDB, SQLite) to read inserted rows
 *
 * Rendering is delegated to a {@see Dialect}; per-vendor variations (e.g.
 * PostgreSQL rejecting `ON DUPLICATE KEY UPDATE`) live in dialect overrides.
 *
 * @package Rak200\SqlBuilder\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Insert implements ExpressionInterface {

    /** @var string Target table name. */
    public private(set) string $table = '';

    /** @var string[] Optional explicit column list. */
    public private(set) array $columns = [];

    /** @var array<int, ExpressionInterface[]> VALUES rows; each row is a list of normalised expressions. */
    public private(set) array $rows = [];

    /** @var Select|null Source SELECT for INSERT ... SELECT (mutually exclusive with $rows). */
    public private(set) ?Select $select = null;

    /** @var array<string, ExpressionInterface> ON DUPLICATE KEY UPDATE assignments. */
    public private(set) array $onDuplicateKey = [];

    /**
     * Portable upsert target columns, or null when no `onConflict()` call was made.
     *
     * Empty array means "no conflict target" (Postgres allows it for a
     * primary-key implicit target; MariaDB ignores the target list entirely).
     *
     * @var string[]|null
     */
    public private(set) ?array $conflictColumns = null;

    /** @var array<string, ExpressionInterface>|null DO UPDATE SET assignments, or null for DO NOTHING. */
    public private(set) ?array $conflictUpdates = null;

    /** @var bool Whether onConflict() resolved to DO NOTHING. */
    public private(set) bool $conflictDoNothing = false;

    /** @var ExpressionInterface|null Optional WHERE predicate on the DO UPDATE action (Postgres-only). */
    public private(set) ?ExpressionInterface $conflictWhere = null;

    /** @var ExpressionInterface[] RETURNING expressions. */
    public private(set) array $returning = [];

    public static function create(): self {
        return new self();
    }

    public function into(string $table): static {
        $this->table = $table;
        return $this;
    }

    public function columns(string ...$names): static {
        $this->columns = $names;
        return $this;
    }

    public function values(mixed ...$row): static {
        if ($this->select !== null) {
            throw new InvalidArgumentException('INSERT cannot mix VALUES and SELECT.');
        }

        $expected = $this->expectedRowSize();
        if ($expected !== null && count($row) !== $expected) {
            throw new InvalidArgumentException(sprintf(
                'INSERT row has %d values, expected %d.',
                count($row),
                $expected
            ));
        }

        $this->rows[] = array_map(
            static fn(mixed $value): ExpressionInterface
                => $value instanceof ExpressionInterface ? $value : Expression::val($value),
            $row
        );

        return $this;
    }

    public function select(Select $query): static {
        if ($this->rows !== []) {
            throw new InvalidArgumentException('INSERT cannot mix VALUES and SELECT.');
        }

        $this->select = $query;
        return $this;
    }

    public function onDuplicateKeyUpdate(string $column, mixed $value): static {
        $this->onDuplicateKey[$column] = $value instanceof ExpressionInterface
            ? $value
            : Expression::val($value);
        return $this;
    }

    /**
     * Declare the conflict target for a portable upsert.
     *
     * Follow with {@see doUpdate()} or {@see doNothing()} to specify the
     * resolution. Render-time the dialect translates to its native form:
     * `ON CONFLICT (...) DO UPDATE` on Postgres/default, `ON DUPLICATE KEY
     * UPDATE` on MariaDB / MySQL.
     *
     * @param string|string[] $columns Conflict target column(s); pass `[]` to
     *                                  omit the target list (Postgres infers a
     *                                  primary-key conflict).
     */
    public function onConflict(string|array $columns = []): static {
        $this->conflictColumns = is_array($columns) ? array_values($columns) : [$columns];
        return $this;
    }

    /**
     * Resolve a previously-declared `onConflict()` to a SET-based update.
     *
     * @param array<string, mixed> $assignments Column-to-value map; scalars
     *                                          are wrapped in {@see Expression::val()}.
     * @throws InvalidArgumentException When called before {@see onConflict()}.
     */
    public function doUpdate(array $assignments): static {
        if ($this->conflictColumns === null) {
            throw new InvalidArgumentException('doUpdate() requires a prior onConflict() call.');
        }
        $this->conflictDoNothing = false;
        $this->conflictUpdates   = [];
        foreach ($assignments as $column => $value) {
            $this->conflictUpdates[$column] = $value instanceof ExpressionInterface
                ? $value
                : Expression::val($value);
        }
        return $this;
    }

    /**
     * Resolve a previously-declared `onConflict()` to ignore the conflicting row.
     *
     * Translates to `ON CONFLICT ... DO NOTHING` on Postgres; rejected on
     * MariaDB / MySQL (use the engine-specific `INSERT IGNORE` instead).
     *
     * @throws InvalidArgumentException When called before {@see onConflict()}.
     */
    public function doNothing(): static {
        if ($this->conflictColumns === null) {
            throw new InvalidArgumentException('doNothing() requires a prior onConflict() call.');
        }
        $this->conflictDoNothing = true;
        $this->conflictUpdates   = null;
        return $this;
    }

    /**
     * Filter the `DO UPDATE` action by a predicate (Postgres-only).
     *
     * @throws InvalidArgumentException When called outside a `doUpdate()` chain.
     */
    public function onConflictWhere(ExpressionInterface $condition): static {
        if ($this->conflictUpdates === null || $this->conflictDoNothing) {
            throw new InvalidArgumentException('onConflictWhere() applies only to doUpdate() resolutions.');
        }
        $this->conflictWhere = $condition;
        return $this;
    }

    public function returning(ExpressionInterface|string ...$expressions): static {
        foreach ($expressions as $expression) {
            $this->returning[] = $expression instanceof ExpressionInterface
                ? $expression
                : Expression::col($expression);
        }
        return $this;
    }

    /** {@inheritdoc}
     * @throws InvalidArgumentException When the target table or value source is missing.
     */
    public function __toString(): string {
        return Dialect::default()->renderInsert($this);
    }

    /**
     * Render this statement with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderInsert($this);
    }

    /**
     * Render this statement in bind mode for the given dialect.
     *
     * @param Dialect $dialect The dialect to render with.
     * @return PreparedStatement
     */
    public function prepare(Dialect $dialect): PreparedStatement {
        $binder = $dialect->newBinder();
        $sql    = $dialect->withBinder($binder)->renderInsert($this);
        return new PreparedStatement($sql, $binder->values());
    }

    /** @return int|null Expected number of values per row, or null when neither columns nor prior rows are set. */
    private function expectedRowSize(): ?int {
        if ($this->columns !== []) {
            return count($this->columns);
        }
        if ($this->rows !== []) {
            return count($this->rows[0]);
        }
        return null;
    }
}
