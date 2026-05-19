<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dml;

use InvalidArgumentException;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Utils\StringUtils;

/**
 * SQL INSERT statement builder.
 *
 * Supports literal VALUES (single or multi-row), `INSERT ... SELECT`, plus
 * dialect extensions:
 * - **ON DUPLICATE KEY UPDATE** (MySQL / MariaDB) for upserts
 * - **RETURNING** (PostgreSQL, MariaDB, SQLite) to read inserted rows
 *
 * Columns may be declared explicitly via {@see columns()}; when declared, each
 * call to {@see values()} must supply the same number of values.
 *
 * Usage example:
 * ```php
 * Insert::create()
 *     ->into('users')
 *     ->columns('name', 'email')
 *     ->values('Alice', 'alice@example.com')
 *     ->values('Bob',   'bob@example.com')
 *     ->onDuplicateKeyUpdate('email', Expression::raw('VALUES(email)'))
 *     ->returning('id');
 * ```
 *
 * @package Rak200\SqlBuilder\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Insert implements ExpressionInterface {

    /** @var string $table Target table name */
    private string $table = '';

    /** @var string[] $columns Optional explicit column list */
    private array $columns = [];

    /** @var array<int, ExpressionInterface[]> $rows VALUES rows; each row is a list of normalised expressions */
    private array $rows = [];

    /** @var Select|null $select Source SELECT for INSERT ... SELECT (mutually exclusive with $rows) */
    private ?Select $select = null;

    /** @var array<string, ExpressionInterface> $onDuplicateKey ON DUPLICATE KEY UPDATE assignments */
    private array $onDuplicateKey = [];

    /** @var ExpressionInterface[] $returning RETURNING expressions */
    private array $returning = [];

    /**
     * Create a new INSERT statement builder.
     *
     * @return self
     */
    public static function create(): self {
        return new self();
    }

    /**
     * Set the target table.
     *
     * @param string $table Table name.
     * @return static
     */
    public function into(string $table): static {
        $this->table = $table;
        return $this;
    }

    /**
     * Declare the column list for the INSERT.
     *
     * When declared, each {@see values()} call must provide exactly this many values.
     *
     * @param string ...$names Column names.
     * @return static
     */
    public function columns(string ...$names): static {
        $this->columns = $names;
        return $this;
    }

    /**
     * Append one row of values. Call multiple times for a multi-row INSERT.
     *
     * Scalar arguments are wrapped in a {@see \Rak200\SqlBuilder\Common\ValueExpression};
     * {@see ExpressionInterface} arguments are used as-is (useful for `NOW()`, sequences, etc).
     *
     * @param mixed ...$row Values for one row.
     * @throws InvalidArgumentException If a SELECT source is already set or the row arity is inconsistent.
     * @return static
     */
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
                => $value instanceof ExpressionInterface ? $value : Expression::value($value),
            $row
        );

        return $this;
    }

    /**
     * Use a SELECT query as the source rows for the INSERT.
     *
     * @param Select $query Source SELECT.
     * @throws InvalidArgumentException If VALUES rows are already registered.
     * @return static
     */
    public function select(Select $query): static {
        if ($this->rows !== []) {
            throw new InvalidArgumentException('INSERT cannot mix VALUES and SELECT.');
        }

        $this->select = $query;
        return $this;
    }

    /**
     * Add or override an ON DUPLICATE KEY UPDATE assignment (MySQL / MariaDB upsert).
     *
     * Scalar values are wrapped in a {@see \Rak200\SqlBuilder\Common\ValueExpression};
     * use {@see Expression::raw()} for `VALUES(col)` or other dialect-specific references.
     *
     * @param string $column Column name to update on conflict.
     * @param mixed $value New value or expression.
     * @return static
     */
    public function onDuplicateKeyUpdate(string $column, mixed $value): static {
        $this->onDuplicateKey[$column] = $value instanceof ExpressionInterface
            ? $value
            : Expression::value($value);
        return $this;
    }

    /**
     * Add expressions to the RETURNING clause. Strings are treated as column references.
     *
     * @param ExpressionInterface|string ...$expressions Columns or expressions to return.
     * @return static
     */
    public function returning(ExpressionInterface|string ...$expressions): static {
        foreach ($expressions as $expression) {
            $this->returning[] = $expression instanceof ExpressionInterface
                ? $expression
                : Expression::column($expression);
        }
        return $this;
    }

    /** {@inheritdoc}
     * @throws InvalidArgumentException When the target table or value source is missing.
     */
    public function __toString(): string {
        if ($this->table === '') {
            throw new InvalidArgumentException('INSERT requires a target table; call into().');
        }
        if ($this->rows === [] && $this->select === null) {
            throw new InvalidArgumentException('INSERT requires VALUES or a SELECT source.');
        }

        $sql  = sprintf('INSERT INTO %s', Expression::quoteIdentifier($this->table));
        $sql .= $this->buildColumnList();
        $sql .= $this->select !== null ? ' ' . $this->select : ' VALUES ' . $this->buildRows();
        $sql .= $this->buildOnDuplicateKeyUpdate();
        $sql .= StringUtils::join($this->returning, ', ', ' RETURNING ');

        return $sql;
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

    /** Build the optional ` (col1, col2)` fragment. */
    private function buildColumnList(): string {
        return StringUtils::join(
            array_map(fn(string $c) => Expression::quoteIdentifier($c), $this->columns),
            ', ',
            ' (',
            ')'
        );
    }

    /** Build the comma-separated tuple list for VALUES. */
    private function buildRows(): string {
        $tuples = array_map(
            static fn(array $row): string => '(' . implode(', ', array_map('strval', $row)) . ')',
            $this->rows
        );

        return implode(', ', $tuples);
    }

    /** Build the ` ON DUPLICATE KEY UPDATE col = val, ...` fragment. */
    private function buildOnDuplicateKeyUpdate(): string {
        if ($this->onDuplicateKey === []) {
            return '';
        }

        $parts = [];
        foreach ($this->onDuplicateKey as $column => $value) {
            $parts[] = sprintf('%s = %s', Expression::quoteIdentifier($column), $value);
        }

        return ' ON DUPLICATE KEY UPDATE ' . implode(', ', $parts);
    }
}
