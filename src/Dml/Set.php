<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dml;

use InvalidArgumentException;
use Rak200\Collections\Vector;
use Rak200\SqlBuilder\Common\Enum\Sort\Nulls as NullsPlacement;
use Rak200\SqlBuilder\Common\Enum\Sort\Direction as SortDirection;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\Order;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Prepared\PreparedStatement;

/**
 * SQL set operation builder for UNION, UNION ALL, EXCEPT and INTERSECT queries.
 *
 * @package Rak200\SqlBuilder\Dml
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Set implements ExpressionInterface {
    /** UNION set operator. */
    public const string UNION = 'UNION';
    /** UNION ALL set operator (preserves duplicates). */
    public const string UNION_ALL = 'UNION ALL';
    /** EXCEPT set operator. */
    public const string EXCEPT = 'EXCEPT';
    /** INTERSECT set operator. */
    public const string INTERSECT = 'INTERSECT';

    /** @var array<int, array{type: string|null, query: Select}> Registered set operations. */
    public private(set) array $operations = [];

    /** @var Vector<Order> ORDER BY entries applied to the full result. */
    public readonly Vector $orderBy;

    /** @var int|null Row limit for the combined result. */
    public private(set) ?int $limit = null;

    /** @var int|null Row offset for the combined result. */
    public private(set) ?int $offset = null;

    private function __construct() {
        $this->orderBy = new Vector(Order::class);
    }

    public static function create(Select $query): self {
        $set = new self();
        $set->operations[] = ['type' => null, 'query' => $query];
        return $set;
    }

    public function union(Select $query, bool $all = false): static {
        $this->operations[] = [
            'type' => $all ? self::UNION_ALL : self::UNION,
            'query' => $query,
        ];
        return $this;
    }

    public function except(Select $query): static {
        $this->operations[] = ['type' => self::EXCEPT, 'query' => $query];
        return $this;
    }

    public function intersect(Select $query): static {
        $this->operations[] = ['type' => self::INTERSECT, 'query' => $query];
        return $this;
    }

    public function orderBy(mixed $expression, SortDirection $direction = SortDirection::ASC, ?NullsPlacement $nulls = null): static {
        $this->orderBy[] = new Order($expression, $direction, $nulls);
        return $this;
    }

    public function limit(int $limit): static {
        if ($limit < 0) {
            throw new InvalidArgumentException('LIMIT must be zero or greater.');
        }
        $this->limit = $limit;
        return $this;
    }

    public function offset(int $offset): static {
        if ($offset < 0) {
            throw new InvalidArgumentException('OFFSET must be zero or greater.');
        }
        $this->offset = $offset;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderSet($this);
    }

    /**
     * Render this statement with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderSet($this);
    }

    /**
     * Render this statement in bind mode for the given dialect.
     *
     * @param Dialect $dialect The dialect to render with.
     * @return PreparedStatement
     */
    public function prepare(Dialect $dialect): PreparedStatement {
        $binder = $dialect->newBinder();
        $sql    = $dialect->withBinder($binder)->renderSet($this);
        return new PreparedStatement($sql, $binder->values());
    }
}
