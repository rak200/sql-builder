<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\Window as WindowSpec;

/**
 * SQL window function expression: `<function> OVER (<window>)`.
 *
 * Wraps a function call ({@see Func}, typically an aggregate or a
 * window-only function such as `ROW_NUMBER`, `RANK`, `LAG`, `LEAD`)
 * with its window specification.
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Window extends Expr {

    /**
     * @param ExpressionInterface $function Inner function call (typically a {@see Func}).
     * @param WindowSpec $window Window specification (PARTITION BY, ORDER BY, frame).
     */
    public function __construct(
        public readonly ExpressionInterface $function,
        public readonly WindowSpec $window
    ) {}
}
