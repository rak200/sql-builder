<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use Rak200\SqlBuilder\Common\Expr;

/**
 * Raw SQL expression that is passed through without quoting or escaping.
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Raw extends Expr {

    /**
     * @param string $sql Raw SQL string to embed verbatim.
     */
    public function __construct(public readonly string $sql) {}
}
