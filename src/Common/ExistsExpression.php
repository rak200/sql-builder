<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Common\Enum\UnaryOperator;

/**
 * SQL EXISTS expression that wraps a subquery with the EXISTS operator.
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class ExistsExpression extends UnaryExpression {
    /**
     * @param SubqueryExpression $operand The subquery to check existence of.
     */
    public function __construct(SubqueryExpression $operand) {
        parent::__construct(UnaryOperator::Exists, $operand);
    }
}
