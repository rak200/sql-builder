<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\UnaryOperator;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dml\Select;
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
     * Create a binary expression (e.g., column = value, count > 10).
     *
     * @param mixed $left Left operand.
     * @param BinaryOperator $operator The binary operator.
     * @param mixed $right Right operand.
     * @return BinaryExpression
     */
    public static function binary(mixed $left, BinaryOperator $operator, mixed $right): BinaryExpression {
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
     * Combine multiple expressions with a logical operator.
     *
     * @param BinaryOperator $operator The combining operator (AND, OR).
     * @param ExpressionInterface ...$terms Expressions to combine.
     * @return ExpressionInterface The combined expression.
     * @throws InvalidArgumentException If no expressions are provided.
     */
    protected static function combine(BinaryOperator $operator, ExpressionInterface ...$terms): ExpressionInterface {
        if (count($terms) === 0) {
            throw new InvalidArgumentException('At least one expression is required.');
        }

        $expression = array_shift($terms);

        foreach ($terms as $term) {
            $expression = new BinaryExpression($expression, $operator, $term);
        }

        return $expression;
    }
}
