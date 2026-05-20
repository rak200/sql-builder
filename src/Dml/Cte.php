<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dml;

use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * A single Common Table Expression entry in a `WITH` clause.
 *
 * Carries the CTE name, an optional column list, and the body query
 * (either a {@see Select} or a {@see Set} for UNION/INTERSECT/EXCEPT
 * forms — recursive CTEs always use a `Set` body). The `RECURSIVE`
 * keyword belongs to the enclosing `WITH` clause, not to the individual
 * Cte — it is flagged via {@see Select::withRecursive()} (or its
 * `Set`/`Insert`/... peers when those land).
 *
 * @package Rak200\SqlBuilder\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Cte implements ExpressionInterface {

    /**
     * @param string $name CTE name (referenceable like a table inside the body).
     * @param Select|Set $query CTE body.
     * @param array<int, string>|null $columns Optional explicit column-name list.
     */
    public function __construct(
        public readonly string $name,
        public readonly Select|Set $query,
        public readonly ?array $columns = null
    ) {}

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderCte($this);
    }

    /**
     * Render this CTE entry with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderCte($this);
    }
}
