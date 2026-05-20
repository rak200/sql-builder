<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

/**
 * SQL literal value expression with proper quoting.
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class ValueExpression extends Expression {

    /**
     * @param mixed $value The PHP value to quote as a SQL literal.
     */
    public function __construct(public readonly mixed $value) {}
}
