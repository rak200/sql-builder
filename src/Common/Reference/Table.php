<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Reference;

use InvalidArgumentException;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dml\Select;

/**
 * SQL table or subquery reference with optional alias for use in FROM and JOIN clauses.
 *
 * @package Rak200\SqlBuilder\Common\Reference
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Table implements ExpressionInterface {

    /**
     * @param string|Select $source Table name or SELECT subquery.
     * @param string|null $alias Optional alias; required when source is a subquery.
     * @throws InvalidArgumentException If a subquery is provided without an alias.
     */
    public function __construct(
        public readonly string|Select $source,
        public readonly ?string $alias = null
    ) {
        if ($source instanceof Select && $alias === null) {
            throw new InvalidArgumentException('Subqueries in FROM must have an alias.');
        }
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderTableReference($this);
    }

    public function toSql(Dialect $dialect): string {
        return $dialect->renderTableReference($this);
    }
}
