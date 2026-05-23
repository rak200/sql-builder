<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use Rak200\SqlBuilder\Common\Enum\Operator\Unary as UnaryOp;

/**
 * SQL EXISTS expression that wraps a subquery with the EXISTS operator.
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Exists extends Unary {

    public function __construct(Subquery $operand) {
        parent::__construct(UnaryOp::Exists, $operand);
    }
}
