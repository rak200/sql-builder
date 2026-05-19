<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Dml\Select;

/**
 * SQL subquery expression that wraps a SELECT statement in parentheses.
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class SubqueryExpression extends Expression {

    /**
     * @param Select $query The SELECT query to use as a subquery.
     * @param string|null $alias Optional alias for the subquery.
     */
    public function __construct(private Select $query, ?string $alias = null) {
        $this->as($alias);
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return "({$this->query}){$this->aliasToSql()}";
    }
}
