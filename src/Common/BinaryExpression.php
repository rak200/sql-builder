<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Common\Enum\BinaryOperator;

/**
 * SQL binary expression combining two operands with an operator (e.g. `a = b`, `x AND y`).
 *
 * @package Rak200\SqlBuilder\Common
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
final class BinaryExpression extends Expression {
    /**
     * @param ExpressionInterface $left Left-hand operand.
     * @param BinaryOperator $operator SQL binary operator.
     * @param ExpressionInterface $right Right-hand operand.
     */
    public function __construct(private ExpressionInterface $left, private BinaryOperator $operator, private ExpressionInterface $right) {}

    /** {@inheritdoc} */
    public function __toString(): string {
        return sprintf('(%s %s %s)%s', $this->left, $this->operator->value, $this->right, $this->aliasToSql());
    }
}
