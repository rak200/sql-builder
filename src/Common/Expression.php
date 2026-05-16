<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\UnaryOperator;
use Rak200\SqlBuilder\Dml\Select;
use InvalidArgumentException;

/**
 * Abstract SQL expression base class.
 *
 * Provides common functionality for all SQL expressions including alias support,
 * identifier/value quoting, and factory methods for creating various expression types.
 * This is the base class for all SQL expression builders.
 *
 * @package Rak200\SqlBuilder\Common
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
abstract class Expression implements ExpressionInterface {
    private ?string $alias = null;

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

    /**
     * Convert the expression to its SQL string representation (to be implemented by subclasses).
     *
     * @return string The SQL representation of the expression.
     */
    abstract public function __toString(): string;

    /**
     * Get the SQL representation of the alias.
     *
     * @return string The alias SQL or empty string if no alias is set.
     */
    protected function aliasToSql(): string {
        return $this->alias !== null ? ' AS ' . self::quoteIdentifier($this->alias) : '';
    }

    /**
     * Quote a SQL identifier (column name, table name, etc).
     *
     * @param string $identifier The identifier to quote.
     * @return string The quoted identifier.
     */
    public static function quoteIdentifier(string $identifier): string {
        $quoted = '`' . str_replace('.', '`.`', $identifier) . '`';
        return str_replace(['`*`','``'], ['*', '`'], $quoted); // Don't quote * or already quoted identifiers
    }

    /**
     * Quote a SQL value for safe inclusion in SQL queries.
     *
     * @param mixed $value The value to quote.
     * @return string The quoted value (NULL for null, properly escaped strings, etc).
     */
    public static function quoteValue(mixed $value): string {
        return match (true) {
            $value === null  => 'NULL',
            is_int($value)   => (string) $value,
            is_float($value) => (string) $value,
            is_bool($value)  => $value ? 'TRUE' : 'FALSE',
            default          => "'" . str_replace(
                ['\\',   "'"],
                ['\\\\', "''"],
                mb_convert_encoding((string) $value, 'UTF-8', 'UTF-8')
            ) . "'",
        };
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
     * Use this when the column appears in a SELECT list and may carry an alias.
     * For columns inside conditions use {@see ref()}; for USING use {@see identifier()}.
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
     * Supports qualified names (`table.column`) but does not carry an alias.
     * Use {@see column()} for SELECT projections that need an alias.
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
     * Rejects names that contain a dot (table qualifier), as SQL USING requires
     * bare column names.
     *
     * @param string $name Unqualified column name.
     * @return SimpleIdentifier
     * @throws \InvalidArgumentException If $name contains a dot.
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
