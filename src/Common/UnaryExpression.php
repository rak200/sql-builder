<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Common\Enum\UnaryOperator;

/**
 * Unary expression builder.
 *
 * Represents a unary SQL expression with a single operand and an operator.
 * Examples: NOT, EXISTS, IS NULL, etc.
 *
 * @package Rak200\SqlBuilder\Common
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
class UnaryExpression extends Expression {

    /**
     * Constructor for the UnaryExpression class.
     *
     * @param UnaryOperator $operator The unary operator.
     * @param ExpressionInterface $operand The operand to apply the operator to.
     */
    public function __construct(protected UnaryOperator $operator, protected ExpressionInterface $operand) {}

    /**
     * Convert the unary expression to SQL string representation.
     *
     * @return string The SQL representation of the unary expression.
     */
    public function __toString(): string {
        return sprintf('%s (%s)%s', $this->operator->value, $this->operand, $this->aliasToSql());
    }
}
