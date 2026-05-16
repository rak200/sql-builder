<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Dml\Select;
use InvalidArgumentException;

/**
 * SQL table or subquery reference with optional alias for use in FROM and JOIN clauses.
 *
 * @package Rak200\SqlBuilder\Common
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
final class TableReference implements ExpressionInterface {
    /**
     * @param string|Select $source Table name or SELECT subquery.
     * @param string|null $alias Optional alias; required when source is a subquery.
     * @throws \InvalidArgumentException If a subquery is provided without an alias.
     */
    public function __construct(
        private string|Select $source,
        private ?string $alias = null
    ) {
        if ($source instanceof Select && $alias === null) {
            throw new InvalidArgumentException('Subqueries in FROM must have an alias.');
        }
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        if ($this->source instanceof Select) {
            return sprintf('(%s) AS %s', $this->source, Expression::quoteIdentifier($this->alias));
        }

        if ($this->alias !== null) {
            return sprintf('%s AS %s', Expression::quoteIdentifier($this->source), Expression::quoteIdentifier($this->alias));
        }

        return Expression::quoteIdentifier($this->source);
    }
}
