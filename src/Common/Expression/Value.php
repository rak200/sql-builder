<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use Rak200\SqlBuilder\Common\Expr;

/**
 * SQL literal value expression with proper quoting.
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Value extends Expr {

    /**
     * @param mixed $value Literal value (string, int, float, bool, or null);
     *                     the dialect's `quoteValue()` decides how it is rendered.
     */
    public function __construct(public readonly mixed $value) {}
}
