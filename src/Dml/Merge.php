<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dml;

use InvalidArgumentException;
use Rak200\SqlBuilder\Common\Expr as Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\Reference\Table as TableReference;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Prepared\PreparedStatement;

/**
 * SQL:2003 `MERGE` statement builder.
 *
 * Fluent shape:
 *
 * ```
 * Merge::create()
 *     ->into('target', 't')
 *     ->using('source', 's')               // or ->using($selectQuery, 's')
 *     ->on(Expr::binary('t.id', Binary::Eq, Expr::ref('s.id')))
 *     ->whenMatchedUpdate(['col' => Expr::ref('s.col')])
 *     ->whenNotMatchedInsert(['id', 'col'], [Expr::ref('s.id'), Expr::ref('s.col')]);
 * ```
 *
 * Branches are emitted in declaration order; each may carry an `AND predicate`
 * filter. Render-time validation requires a target, a source, an `ON`
 * condition, and at least one `WHEN` branch.
 *
 * @package Rak200\SqlBuilder\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Merge implements ExpressionInterface {

    /** @var TableReference|null Target table. */
    public private(set) ?TableReference $target = null;

    /** @var TableReference|null Source table or subquery. */
    public private(set) ?TableReference $source = null;

    /** @var ExpressionInterface|null Join predicate. */
    public private(set) ?ExpressionInterface $on = null;

    /** @var MergeClause[] Ordered WHEN clauses. */
    public private(set) array $clauses = [];

    /** @var ExpressionInterface[] RETURNING expressions (Postgres 15+ / Snowflake). */
    public private(set) array $returning = [];

    public static function create(): self {
        return new self();
    }

    public function into(string $table, ?string $alias = null): static {
        $this->target = new TableReference($table, $alias);
        return $this;
    }

    public function using(string|Select $source, ?string $alias = null): static {
        $this->source = new TableReference($source, $alias);
        return $this;
    }

    public function on(ExpressionInterface $condition): static {
        $this->on = $condition;
        return $this;
    }

    /**
     * Append a `WHEN MATCHED [AND predicate] THEN UPDATE SET …` branch.
     *
     * @param array<string, mixed> $assignments
     */
    public function whenMatchedUpdate(array $assignments, ?ExpressionInterface $predicate = null): static {
        $this->clauses[] = new MergeClause(
            kind:      'update',
            matched:   true,
            predicate: $predicate,
            updates:   $this->normalizeAssignments($assignments),
        );
        return $this;
    }

    /** Append a `WHEN MATCHED [AND predicate] THEN DELETE` branch. */
    public function whenMatchedDelete(?ExpressionInterface $predicate = null): static {
        $this->clauses[] = new MergeClause(kind: 'delete', matched: true, predicate: $predicate);
        return $this;
    }

    /**
     * Append a `WHEN NOT MATCHED [AND predicate] THEN INSERT (…) VALUES (…)` branch.
     *
     * Pass `[]` for both arrays to emit `INSERT DEFAULT VALUES`.
     *
     * @param string[] $columns
     * @param mixed[]  $values Scalars are wrapped in {@see Expression::val()}.
     */
    public function whenNotMatchedInsert(array $columns, array $values, ?ExpressionInterface $predicate = null): static {
        if (count($columns) !== count($values)) {
            throw new InvalidArgumentException('MERGE INSERT: column and value lists must have equal length.');
        }

        $this->clauses[] = new MergeClause(
            kind:          'insert',
            matched:       false,
            predicate:     $predicate,
            insertColumns: array_values($columns),
            insertValues:  array_map(
                static fn($value): ExpressionInterface => $value instanceof ExpressionInterface
                    ? $value
                    : Expression::val($value),
                array_values($values)
            ),
        );
        return $this;
    }

    /** Append a `WHEN [NOT] MATCHED [AND predicate] THEN DO NOTHING` branch. */
    public function whenDoNothing(bool $matched, ?ExpressionInterface $predicate = null): static {
        $this->clauses[] = new MergeClause(kind: 'nothing', matched: $matched, predicate: $predicate);
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

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderMerge($this);
    }

    public function toSql(Dialect $dialect): string {
        return $dialect->renderMerge($this);
    }

    public function prepare(Dialect $dialect): PreparedStatement {
        $binder = $dialect->newBinder();
        $sql    = $dialect->withBinder($binder)->renderMerge($this);
        return new PreparedStatement($sql, $binder->values());
    }

    /**
     * @param array<string, mixed> $assignments
     * @return array<string, ExpressionInterface>
     */
    private function normalizeAssignments(array $assignments): array {
        $normalized = [];
        foreach ($assignments as $column => $value) {
            $normalized[$column] = $value instanceof ExpressionInterface
                ? $value
                : Expression::val($value);
        }
        return $normalized;
    }
}
