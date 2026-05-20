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
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UnaryExpression extends Expression {

    /**
     * @param UnaryOperator $operator The unary operator.
     * @param ExpressionInterface $operand The operand to apply the operator to.
     */
    public function __construct(
        public readonly UnaryOperator $operator,
        public readonly ExpressionInterface $operand
    ) {}
}
