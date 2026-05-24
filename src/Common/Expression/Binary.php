<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use Rak200\SqlBuilder\Common\Enum\Operator\Binary as BinaryOp;
use Rak200\SqlBuilder\Common\Enum\Operator\Math;
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\ExpressionInterface;

/**
 * SQL binary expression combining two operands with an operator
 * (e.g. `a = b`, `x AND y`, `price + tax`).
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Binary extends Expr {

    /**
     * @param ExpressionInterface $left Left-hand operand.
     * @param BinaryOp|Math $operator Predicate (`=`, `<`, `AND`, …) or arithmetic (`+`, `*`, …) operator.
     * @param ExpressionInterface $right Right-hand operand.
     */
    public function __construct(
        public readonly ExpressionInterface $left,
        public readonly BinaryOp|Math $operator,
        public readonly ExpressionInterface $right
    ) {}
}
