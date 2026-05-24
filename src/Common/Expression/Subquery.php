<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Dml\Select;

/**
 * SQL subquery expression that wraps a SELECT statement in parentheses.
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Subquery extends Expr {

    /**
     * @param Select $query SELECT statement to wrap.
     * @param string|null $alias Optional alias rendered as `(...) AS alias`.
     */
    public function __construct(public readonly Select $query, ?string $alias = null) {
        $this->as($alias);
    }
}
