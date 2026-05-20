<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

/**
 * SQL window function expression: `<function> OVER (<window>)`.
 *
 * Wraps a function call ({@see FunctionExpression}, typically an aggregate
 * or a window-only function such as `ROW_NUMBER`, `RANK`, `LAG`, `LEAD`)
 * with its window specification.
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class WindowExpression extends Expression {

    public function __construct(
        public readonly ExpressionInterface $function,
        public readonly Window $window
    ) {}
}
