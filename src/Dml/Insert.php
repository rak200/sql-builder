<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dml;

use InvalidArgumentException;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;

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
                => $value instanceof ExpressionInterface ? $value : Expression::value($value),
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
            : Expression::value($value);
        return $this;
    }

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
        return Dialect::default()->renderInsert($this);
    }

    /**
     * Render this statement with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderInsert($this);
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
