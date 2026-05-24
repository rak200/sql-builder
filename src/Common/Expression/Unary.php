<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use Rak200\SqlBuilder\Common\Enum\Operator\Unary as UnaryOp;
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\ExpressionInterface;

/**
 * Unary SQL expression with a single operand and an operator.
 *
 * Examples: NOT, EXISTS, IS NULL, etc.
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Unary extends Expr {

    /**
     * @param UnaryOp $operator Unary operator (`NOT`, `IS NULL`, `IS NOT NULL`, …).
     * @param ExpressionInterface $operand Expression the operator applies to.
     */
    public function __construct(
        public readonly UnaryOp $operator,
        public readonly ExpressionInterface $operand
    ) {}
}
