<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Common\Enum\ArithmeticOperator;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\UnaryOperator;
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
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
abstract class Expression implements ExpressionInterface {

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
     *
     * @param Dialect $dialect The dialect to render with.
     * @return string
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderExpression($this);
    }

    /**
     * Render this expression in bind mode for the given dialect.
     *
     * Values are emitted as dialect-specific placeholders and accumulated
     * into the resulting {@see PreparedStatement::$parameters} array.
     *
     * @param Dialect $dialect The dialect to render with.
     * @return PreparedStatement The SQL and bound parameters.
     */
    public function prepare(Dialect $dialect): PreparedStatement {
        $binder = $dialect->newBinder();
        $sql    = $dialect->withBinder($binder)->renderExpression($this);
        return new PreparedStatement($sql, $binder->values());
    }

    /**
     * Quote a SQL identifier via the default dialect (backwards-compatible shim).
     *
     * @param string $identifier The identifier to quote.
     * @return string The quoted identifier.
     */
    public static function quoteIdentifier(string $identifier): string {
        return Dialect::default()->quoteIdentifier($identifier);
    }

    /**
     * Quote a SQL value via the default dialect (backwards-compatible shim).
     *
     * @param mixed $value The value to quote.
     * @return string The quoted value.
     */
    public static function quoteValue(mixed $value): string {
        return Dialect::default()->quoteValue($value);
    }

    /**
     * Normalize a value to an expression for use inside binary/unary operators.
     *
     * Strings become {@see ColumnReference} (no alias); other scalars become {@see ValueExpression}.
     *
     * @param mixed $value The value to normalize.
     * @return ExpressionInterface The normalized expression.
     */
    protected static function normalize(mixed $value): ExpressionInterface {
        if ($value instanceof ExpressionInterface) {
            return $value;
        }

        if (is_string($value)) {
            return new ColumnReference($value);
        }

        return new ValueExpression($value);
    }

    /**
     * Create a raw SQL expression.
     *
     * @param string $sql The raw SQL string.
     * @return RawExpression
     */
    public static function raw(string $sql): RawExpression {
        return new RawExpression($sql);
    }

    /**
     * Create a SELECT-projection column expression with optional alias.
     *
     * @param string $name The column name (e.g., 'users.id' or 'name').
     * @param string|null $alias Optional alias for the column.
     * @return ColumnExpression
     */
    public static function column(string $name, ?string $alias = null): ColumnExpression {
        return new ColumnExpression($name, $alias);
    }

    /**
     * Create a column reference for use inside conditions, ORDER BY, and GROUP BY.
     *
     * @param string $name Column or qualified identifier.
     * @return ColumnReference
     */
    public static function ref(string $name): ColumnReference {
        return new ColumnReference($name);
    }

    /**
     * Create a simple unqualified identifier for use in USING clauses.
     *
     * @param string $name Unqualified column name.
     * @return SimpleIdentifier
     * @throws InvalidArgumentException If $name contains a dot.
     */
    public static function identifier(string $name): SimpleIdentifier {
        return new SimpleIdentifier($name);
    }

    /**
     * Create a literal value expression.
     *
     * @param mixed $value The literal value.
     * @return ValueExpression
     */
    public static function value(mixed $value): ValueExpression {
        return new ValueExpression($value);
    }

    /**
     * Mark a value as destined for a UUID column.
     *
     * Wraps the given value (or expression) in a {@see UuidInputExpression}
     * so the active dialect can apply the appropriate input transform —
     * `::uuid` cast on PostgreSQL (when the inner is a literal or a
     * parameter), `UUID_TO_BIN(...)` on MariaDB/MySQL, pass-through on the
     * default dialect. Strings are normalised to a {@see ValueExpression}.
     *
     * @param string|ExpressionInterface $value The UUID value (text) or
     *                                          a wrapped expression.
     * @return UuidInputExpression
     */
    public static function uuid(string|ExpressionInterface $value): UuidInputExpression {
        return new UuidInputExpression(
            $value instanceof ExpressionInterface ? $value : new ValueExpression($value)
        );
    }

    /**
     * Mark a column as carrying UUID-typed data for projection in `SELECT`.
     *
     * Wraps a {@see ColumnExpression} in a {@see UuidOutputExpression} so
     * the active dialect can apply the appropriate output transform —
     * `BIN_TO_UUID(...)` on MariaDB/MySQL, pass-through on the default
     * dialect and PostgreSQL.
     *
     * @param string $name Column or qualified identifier.
     * @param string|null $alias Optional projection alias.
     * @return UuidOutputExpression
     */
    public static function uuidColumn(string $name, ?string $alias = null): UuidOutputExpression {
        return new UuidOutputExpression(new ColumnExpression($name, $alias));
    }

    /**
     * Create a prepared-statement parameter placeholder.
     *
     * Use `int` keys for positional placeholders (`?` on MariaDB/MySQL, `$N`
     * on Postgres) and `string` keys for named placeholders (`:name`, PDO-
     * emulated on every dialect). Reuse of the same key collapses to a
     * single entry in the resulting parameters array on dialects that
     * support placeholder reuse on the wire (Postgres positional, named on
     * both).
     *
     * The optional default value is bound when the placeholder is first
     * emitted; callers can override values per run via
     * {@see \Rak200\SqlBuilder\Prepared\PreparedStatement::$parameters}.
     *
     * @param int|string $key Positional index or parameter name.
     * @param mixed $value Optional default value to associate with the slot.
     * @return ParameterExpression
     */
    public static function param(int|string $key, mixed $value = null): ParameterExpression {
        return new ParameterExpression($key, $value);
    }

    /**
     * Create a function call expression.
     *
     * @param string $name The function name (e.g., 'COUNT', 'MAX', 'SUM').
     * @param mixed ...$arguments Arguments to pass to the function.
     * @return FunctionExpression
     */
    public static function func(string $name, mixed ...$arguments): FunctionExpression {
        return new FunctionExpression($name, ...$arguments);
    }

    /**
     * Create a subquery expression.
     *
     * @param Select $query The SELECT query.
     * @param string|null $alias Optional alias for the subquery.
     * @return SubqueryExpression
     */
    public static function subquery(Select $query, ?string $alias = null): SubqueryExpression {
        return new SubqueryExpression($query, $alias);
    }

    /**
     * Create a binary expression (e.g., `column = value`, `count > 10`, `price + tax`).
     *
     * @param mixed $left Left operand.
     * @param BinaryOperator|ArithmeticOperator $operator The binary or arithmetic operator.
     * @param mixed $right Right operand.
     * @return BinaryExpression
     */
    public static function binary(mixed $left, BinaryOperator|ArithmeticOperator $operator, mixed $right): BinaryExpression {
        return new BinaryExpression(self::normalize($left), $operator, self::normalize($right));
    }

    /**
     * Create a unary expression (e.g., NOT expression, -value).
     *
     * @param UnaryOperator $operator The unary operator.
     * @param mixed $operand The operand to apply the operator to.
     * @return UnaryExpression
     */
    public static function unary(UnaryOperator $operator, mixed $operand): UnaryExpression {
        return new UnaryExpression($operator, self::normalize($operand));
    }

    /**
     * Create a logical AND expression combining multiple conditions.
     *
     * @param ExpressionInterface ...$terms Conditions to combine with AND.
     * @return ExpressionInterface The combined AND expression.
     * @throws InvalidArgumentException If no expressions are provided.
     */
    public static function and(ExpressionInterface ...$terms): ExpressionInterface {
        return self::combine(BinaryOperator::And, ...$terms);
    }

    /**
     * Create a logical OR expression combining multiple conditions.
     *
     * @param ExpressionInterface ...$terms Conditions to combine with OR.
     * @return ExpressionInterface The combined OR expression.
     * @throws InvalidArgumentException If no expressions are provided.
     */
    public static function or(ExpressionInterface ...$terms): ExpressionInterface {
        return self::combine(BinaryOperator::Or, ...$terms);
    }

    /**
     * Create an EXISTS expression for subqueries.
     *
     * @param Select $query The subquery to check existence of.
     * @return ExistsExpression
     */
    public static function exists(Select $query): ExistsExpression {
        return new ExistsExpression(new SubqueryExpression($query));
    }

    /**
     * Create a NOT expression.
     *
     * @param mixed $expression The expression to negate.
     * @return UnaryExpression
     */
    public static function not(mixed $expression): UnaryExpression {
        return new UnaryExpression(UnaryOperator::Not, self::normalize($expression));
    }

    /**
     * Create a SUM aggregate function expression.
     *
     * @param mixed $expression The expression to sum.
     * @param string|null $alias Optional alias for the result.
     * @return FunctionExpression
     */
    public static function sum(mixed $expression, ?string $alias = null): FunctionExpression {
        return self::func('SUM', $expression)->as($alias ?? 'SUM');
    }

    /**
     * Create an AVG aggregate function expression.
     *
     * @param mixed $expression The expression to average.
     * @param string|null $alias Optional alias for the result.
     * @return FunctionExpression
     */
    public static function avg(mixed $expression, ?string $alias = null): FunctionExpression {
        return self::func('AVG', $expression)->as($alias ?? 'AVG');
    }

    /**
     * Create a COUNT aggregate function expression.
     *
     * @param mixed $expression The expression to count (default is '*' for all rows).
     * @param string|null $alias Optional alias for the result.
     * @return FunctionExpression
     */
    public static function count(mixed $expression = '*', ?string $alias = null): FunctionExpression {
        return self::func('COUNT', $expression)->as($alias ?? 'COUNT');
    }

    /**
     * Create a MAX aggregate function expression.
     *
     * @param mixed $expression The expression to find maximum of.
     * @param string|null $alias Optional alias for the result.
     * @return FunctionExpression
     */
    public static function max(mixed $expression, ?string $alias = null): FunctionExpression {
        return self::func('MAX', $expression)->as($alias ?? 'MAX');
    }

    /**
     * Create a MIN aggregate function expression.
     *
     * @param mixed $expression The expression to find minimum of.
     * @param string|null $alias Optional alias for the result.
     * @return FunctionExpression
     */
    public static function min(mixed $expression, ?string $alias = null): FunctionExpression {
        return self::func('MIN', $expression)->as($alias ?? 'MIN');
    }

    /**
     * Create a `CASE` expression.
     *
     * Pass a `$subject` for the simple form (`CASE subj WHEN val THEN ...`);
     * omit it for the searched form (`CASE WHEN cond THEN ...`).
     *
     * @param mixed $subject Optional simple-form subject; expression or column reference.
     */
    public static function case(mixed $subject = null): CaseExpression {
        return new CaseExpression($subject === null ? null : self::normalize($subject));
    }

    /**
     * Wrap a function call in an `OVER (...)` window clause.
     *
     * Build the {@see Window} fluently — `Window::create()->partitionBy(...)
     * ->orderBy(...)->rows(...)` — and pass it alongside the aggregate or
     * window-only function (`ROW_NUMBER`, `RANK`, `LAG`, …) to construct.
     *
     * @param ExpressionInterface $function The function call expression.
     * @param Window $window The window specification.
     */
    public static function over(ExpressionInterface $function, Window $window): WindowExpression {
        return new WindowExpression($function, $window);
    }

    /**
     * Combine multiple operands with a binary operator, left-associatively.
     *
     * Each operand is normalized (strings become column references, scalars become
     * value expressions, expressions are passed through), so callers can mix raw
     * values, column names, and expressions freely.
     *
     * @param BinaryOperator|ArithmeticOperator $operator The combining operator.
     * @param mixed ...$operands Operands to combine.
     * @return ExpressionInterface The combined expression.
     * @throws InvalidArgumentException If no operands are provided.
     */
    protected static function combine(BinaryOperator|ArithmeticOperator $operator, mixed ...$operands): ExpressionInterface {
        if (count($operands) === 0) {
            throw new InvalidArgumentException('At least one expression is required.');
        }

        $expression = self::normalize(array_shift($operands));

        foreach ($operands as $operand) {
            $expression = new BinaryExpression($expression, $operator, self::normalize($operand));
        }

        return $expression;
    }

    /**
     * Create an arithmetic addition expression (e.g., `(a + b + c)`).
     *
     * @param mixed ...$operands Operands to sum.
     * @return ExpressionInterface
     * @throws InvalidArgumentException If no operands are provided.
     */
    public static function add(mixed ...$operands): ExpressionInterface {
        return self::combine(ArithmeticOperator::Add, ...$operands);
    }

    /**
     * Create an arithmetic subtraction expression (e.g., `((a - b) - c)`).
     *
     * @param mixed ...$operands Operands to subtract, left-associative.
     * @return ExpressionInterface
     * @throws InvalidArgumentException If no operands are provided.
     */
    public static function sub(mixed ...$operands): ExpressionInterface {
        return self::combine(ArithmeticOperator::Sub, ...$operands);
    }

    /**
     * Create an arithmetic multiplication expression (e.g., `(a * b * c)`).
     *
     * @param mixed ...$operands Operands to multiply.
     * @return ExpressionInterface
     * @throws InvalidArgumentException If no operands are provided.
     */
    public static function mul(mixed ...$operands): ExpressionInterface {
        return self::combine(ArithmeticOperator::Mul, ...$operands);
    }

    /**
     * Create an arithmetic division expression (e.g., `((a / b) / c)`).
     *
     * @param mixed ...$operands Operands to divide, left-associative.
     * @return ExpressionInterface
     * @throws InvalidArgumentException If no operands are provided.
     */
    public static function div(mixed ...$operands): ExpressionInterface {
        return self::combine(ArithmeticOperator::Div, ...$operands);
    }

    /**
     * Create an arithmetic modulo expression (e.g., `((a % b) % c)`).
     *
     * @param mixed ...$operands Operands to apply modulo to, left-associative.
     * @return ExpressionInterface
     * @throws InvalidArgumentException If no operands are provided.
     */
    public static function mod(mixed ...$operands): ExpressionInterface {
        return self::combine(ArithmeticOperator::Mod, ...$operands);
    }
}
