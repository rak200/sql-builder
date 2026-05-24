<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Common\Enum\GroupingMode;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary as BinaryOp;
use Rak200\SqlBuilder\Common\Enum\Operator\Math;
use Rak200\SqlBuilder\Common\Enum\Operator\Unary as UnaryOp;
use Rak200\SqlBuilder\Common\Expression\Binary;
use Rak200\SqlBuilder\Common\Expression\CaseWhen;
use Rak200\SqlBuilder\Common\Expression\Column;
use Rak200\SqlBuilder\Common\Expression\Exists;
use Rak200\SqlBuilder\Common\Expression\Func;
use Rak200\SqlBuilder\Common\Expression\Grouping;
use Rak200\SqlBuilder\Common\Expression\Param;
use Rak200\SqlBuilder\Common\Expression\Raw;
use Rak200\SqlBuilder\Common\Expression\Subquery;
use Rak200\SqlBuilder\Common\Expression\Unary;
use Rak200\SqlBuilder\Common\Expression\UuidInput;
use Rak200\SqlBuilder\Common\Expression\UuidOutput;
use Rak200\SqlBuilder\Common\Expression\Value;
use Rak200\SqlBuilder\Common\Expression\Window as WindowExpr;
use Rak200\SqlBuilder\Common\Reference\Column as ColumnRef;
use Rak200\SqlBuilder\Common\Reference\Identifier;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Prepared\PreparedStatement;
use InvalidArgumentException;

/**
 * Abstract SQL expression base class.
 *
 * Provides common functionality for all SQL expressions including alias support
 * and factory methods for creating various expression types. Concrete subclasses
 * carry their own state; rendering is delegated to a {@see Dialect}.
 *
 * Renamed from `Expression` to `Expr` in 0.9.0 — see CLAUDE.md "API density"
 * section.
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
abstract class Expr implements ExpressionInterface {

    /** @var string|null Read by renderers; written only via {@see as()}. */
    public private(set) ?string $alias = null;

    /**
     * Set an alias for the expression.
     *
     * @param string|null $alias The alias name.
     * @return static
     */
    public function as(?string $alias): static {
        $this->alias = $alias;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderExpression($this);
    }

    /**
     * Render this expression with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderExpression($this);
    }

    /**
     * Render this expression in bind mode for the given dialect.
     */
    public function prepare(Dialect $dialect): PreparedStatement {
        $binder = $dialect->newBinder();
        $sql    = $dialect->withBinder($binder)->renderExpression($this);
        return new PreparedStatement($sql, $binder->values());
    }

    /** Quote a SQL identifier via the default dialect (backwards-compatible shim). */
    public static function quoteIdentifier(string $identifier): string {
        return Dialect::default()->quoteIdentifier($identifier);
    }

    /** Quote a SQL value via the default dialect (backwards-compatible shim). */
    public static function quoteValue(mixed $value): string {
        return Dialect::default()->quoteValue($value);
    }

    /**
     * Normalize a value to an expression for use inside binary/unary operators.
     *
     * Strings become {@see ColumnRef} (no alias); other scalars become {@see Value}.
     */
    protected static function normalize(mixed $value): ExpressionInterface {
        if ($value instanceof ExpressionInterface) {
            return $value;
        }

        if (is_string($value)) {
            return new ColumnRef($value);
        }

        return new Value($value);
    }

    /** Create a raw SQL expression. */
    public static function raw(string $sql): Raw {
        return new Raw($sql);
    }

    /**
     * Create a SELECT-projection column expression with optional alias.
     *
     * Renamed from `column()` to `col()` in 0.9.0.
     */
    public static function col(string $name, ?string $alias = null): Column {
        return new Column($name, $alias);
    }

    /** Create a column reference for use inside conditions, ORDER BY, and GROUP BY. */
    public static function ref(string $name): ColumnRef {
        return new ColumnRef($name);
    }

    /**
     * Create a simple unqualified identifier for use in USING clauses.
     *
     * @throws InvalidArgumentException If $name contains a dot.
     */
    public static function identifier(string $name): Identifier {
        return new Identifier($name);
    }

    /**
     * Create a literal value expression.
     *
     * Renamed from `value()` to `val()` in 0.9.0.
     */
    public static function val(mixed $value): Value {
        return new Value($value);
    }

    /**
     * Mark a value as destined for a UUID column.
     */
    public static function uuid(string|ExpressionInterface $value): UuidInput {
        return new UuidInput(
            $value instanceof ExpressionInterface ? $value : new Value($value)
        );
    }

    /**
     * Mark a column as carrying UUID-typed data for projection in `SELECT`.
     */
    public static function uuidColumn(string $name, ?string $alias = null): UuidOutput {
        return new UuidOutput(new Column($name, $alias));
    }

    /**
     * Create a prepared-statement parameter placeholder.
     */
    public static function param(int|string $key, mixed $value = null): Param {
        return new Param($key, $value);
    }

    /** Create a function call expression. */
    public static function func(string $name, mixed ...$arguments): Func {
        return new Func($name, ...$arguments);
    }

    /** Create a subquery expression. */
    public static function subquery(Select $query, ?string $alias = null): Subquery {
        return new Subquery($query, $alias);
    }

    /** Create a binary expression. */
    public static function binary(mixed $left, BinaryOp|Math $operator, mixed $right): Binary {
        return new Binary(self::normalize($left), $operator, self::normalize($right));
    }

    /** Create a unary expression. */
    public static function unary(UnaryOp $operator, mixed $operand): Unary {
        return new Unary($operator, self::normalize($operand));
    }

    /** Create a logical AND expression combining multiple conditions. */
    public static function and(ExpressionInterface ...$terms): ExpressionInterface {
        return self::combine(BinaryOp::And, ...$terms);
    }

    /** Create a logical OR expression combining multiple conditions. */
    public static function or(ExpressionInterface ...$terms): ExpressionInterface {
        return self::combine(BinaryOp::Or, ...$terms);
    }

    /** Create an EXISTS expression for subqueries. */
    public static function exists(Select $query): Exists {
        return new Exists(new Subquery($query));
    }

    /** Create a NOT expression. */
    public static function not(mixed $expression): Unary {
        return new Unary(UnaryOp::Not, self::normalize($expression));
    }

    /** `SUM(expr) AS alias` — aliased to `SUM` by default. */
    public static function sum(mixed $expression, ?string $alias = null): Func {
        return self::func('SUM', $expression)->as($alias ?? 'SUM');
    }

    /** `AVG(expr) AS alias` — aliased to `AVG` by default. */
    public static function avg(mixed $expression, ?string $alias = null): Func {
        return self::func('AVG', $expression)->as($alias ?? 'AVG');
    }

    /** `COUNT(expr) AS alias` — `COUNT(*)` by default; aliased to `COUNT`. */
    public static function count(mixed $expression = '*', ?string $alias = null): Func {
        return self::func('COUNT', $expression)->as($alias ?? 'COUNT');
    }

    /** `MAX(expr) AS alias` — aliased to `MAX` by default. */
    public static function max(mixed $expression, ?string $alias = null): Func {
        return self::func('MAX', $expression)->as($alias ?? 'MAX');
    }

    /** `MIN(expr) AS alias` — aliased to `MIN` by default. */
    public static function min(mixed $expression, ?string $alias = null): Func {
        return self::func('MIN', $expression)->as($alias ?? 'MIN');
    }

    /**
     * Create a `CASE` expression.
     *
     * Pass a `$subject` for the simple form (`CASE subj WHEN val THEN ...`);
     * omit it for the searched form (`CASE WHEN cond THEN ...`).
     */
    public static function case(mixed $subject = null): CaseWhen {
        return new CaseWhen($subject === null ? null : self::normalize($subject));
    }

    /** Wrap a function call in an `OVER (...)` window clause. */
    public static function over(ExpressionInterface $function, Window $window): WindowExpr {
        return new WindowExpr($function, $window);
    }

    /**
     * Combine multiple operands with a binary operator, left-associatively.
     *
     * @throws InvalidArgumentException If no operands are provided.
     */
    protected static function combine(BinaryOp|Math $operator, mixed ...$operands): ExpressionInterface {
        if (count($operands) === 0) {
            throw new InvalidArgumentException('At least one expression is required.');
        }

        $expression = self::normalize(array_shift($operands));

        foreach ($operands as $operand) {
            $expression = new Binary($expression, $operator, self::normalize($operand));
        }

        return $expression;
    }

    /** Create an arithmetic addition expression (e.g., `(a + b + c)`). */
    public static function add(mixed ...$operands): ExpressionInterface {
        return self::combine(Math::Add, ...$operands);
    }

    /** Create an arithmetic subtraction expression (e.g., `((a - b) - c)`). */
    public static function sub(mixed ...$operands): ExpressionInterface {
        return self::combine(Math::Sub, ...$operands);
    }

    /** Create an arithmetic multiplication expression (e.g., `(a * b * c)`). */
    public static function mul(mixed ...$operands): ExpressionInterface {
        return self::combine(Math::Mul, ...$operands);
    }

    /** Create an arithmetic division expression (e.g., `((a / b) / c)`). */
    public static function div(mixed ...$operands): ExpressionInterface {
        return self::combine(Math::Div, ...$operands);
    }

    /** Create an arithmetic modulo expression (e.g., `((a % b) % c)`). */
    public static function mod(mixed ...$operands): ExpressionInterface {
        return self::combine(Math::Mod, ...$operands);
    }

    /**
     * Create a `GROUPING SETS ((...), (...))` expression for `GROUP BY`.
     *
     * Each item may be a string column name, an `ExpressionInterface`, or an
     * array (rendered as a tuple, including `[]` for the grand-total grouping).
     */
    public static function groupingSets(mixed ...$sets): Grouping {
        return new Grouping(GroupingMode::Sets, ...$sets);
    }

    /** Create a `ROLLUP (a, b, c)` expression for `GROUP BY`. */
    public static function rollup(mixed ...$expressions): Grouping {
        return new Grouping(GroupingMode::Rollup, ...$expressions);
    }

    /** Create a `CUBE (a, b, c)` expression for `GROUP BY`. */
    public static function cube(mixed ...$expressions): Grouping {
        return new Grouping(GroupingMode::Cube, ...$expressions);
    }
}
