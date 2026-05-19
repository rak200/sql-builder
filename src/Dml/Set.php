<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dml;

use Rak200\SqlBuilder\Common\Enum\NullsPlacement;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\Order;
use Rak200\Collections\Vector;
use Rak200\SqlBuilder\Utils\StringUtils;
use InvalidArgumentException;

/**
 * SQL set operation builder for UNION, UNION ALL, EXCEPT and INTERSECT queries.
 *
 * @package Rak200\SqlBuilder\Dml
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
final class Set implements ExpressionInterface {
    /** @var string UNION set operator */
    public const string UNION = 'UNION';
    /** @var string UNION ALL set operator (preserves duplicates) */
    public const string UNION_ALL = 'UNION ALL';
    /** @var string EXCEPT set operator */
    public const string EXCEPT = 'EXCEPT';
    /** @var string INTERSECT set operator */
    public const string INTERSECT = 'INTERSECT';

    /** @var array<array{type: string|null, query: Select}> $operations Registered set operations */
    private array $operations = [];
    /** @var Vector<Order> $orderBy ORDER BY entries applied to the full result */
    private Vector $orderBy;
    /** @var int|null $limit Row limit for the combined result */
    private ?int $limit = null;
    /** @var int|null $offset Row offset for the combined result */
    private ?int $offset = null;

    private function __construct() {
        $this->orderBy = new Vector(Order::class);
    }

    /**
     * Create a new set operation builder starting with the given SELECT query.
     *
     * @param Select $query The initial SELECT query.
     * @return self
     */
    public static function create(Select $query): self {
        $set = new self();
        $set->operations[] = [
            'type' => null,
            'query' => $query,
        ];
        return $set;
    }

    /**
     * Append a UNION or UNION ALL operation.
     *
     * @param Select $query The SELECT query to union with.
     * @param bool $all Use UNION ALL when true, UNION otherwise.
     * @return static
     */
    public function union(Select $query, bool $all = false): static {
        $this->operations[] = [
            'type' => $all ? self::UNION_ALL : self::UNION,
            'query' => $query,
        ];
        return $this;
    }

    /**
     * Append an EXCEPT operation.
     *
     * @param Select $query The SELECT query to exclude.
     * @return static
     */
    public function except(Select $query): static {
        $this->operations[] = [
            'type' => self::EXCEPT,
            'query' => $query,
        ];
        return $this;
    }

    /**
     * Append an INTERSECT operation.
     *
     * @param Select $query The SELECT query to intersect with.
     * @return static
     */
    public function intersect(Select $query): static {
        $this->operations[] = [
            'type' => self::INTERSECT,
            'query' => $query,
        ];
        return $this;
    }

    /**
     * Add an ORDER BY entry applied to the combined result.
     *
     * @param mixed $expression Column name or expression to sort by.
     * @param SortDirection $direction Sort direction; defaults to ASC.
     * @param NullsPlacement|null $nulls Optional NULL placement.
     * @return static
     */
    public function orderBy(mixed $expression, SortDirection $direction = SortDirection::ASC, ?NullsPlacement $nulls = null): static {
        $this->orderBy[] = new Order($expression, $direction, $nulls);
        return $this;
    }

    /**
     * Set the maximum number of rows to return from the combined result.
     *
     * @param int $limit Non-negative row count.
     * @throws \InvalidArgumentException If limit is negative.
     * @return static
     */
    public function limit(int $limit): static {
        if ($limit < 0) {
            throw new InvalidArgumentException('LIMIT must be zero or greater.');
        }
        $this->limit = $limit;
        return $this;
    }

    /**
     * Set the number of rows to skip from the combined result.
     *
     * @param int $offset Non-negative row offset.
     * @throws \InvalidArgumentException If offset is negative.
     * @return static
     */
    public function offset(int $offset): static {
        if ($offset < 0) {
            throw new InvalidArgumentException('OFFSET must be zero or greater.');
        }
        $this->offset = $offset;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        if (empty($this->operations)) {
            throw new InvalidArgumentException('Set operation must have at least one query.');
        }

        $sql = $this->buildOperations();
        $sql .= $this->buildOrderBy();
        $sql .= $this->buildLimitOffset();

        return trim($sql);
    }

    /** Build each SELECT query separated by its set operator keyword. */
    private function buildOperations(): string {
        $sql = '';

        foreach ($this->operations as $index => $operation) {
            if ($index === 0) {
                $sql .= '(' . $operation['query'] . ')';
            } else {
                $sql .= ' ' . $operation['type'] . ' (' . $operation['query'] . ')';
            }
        }

        return $sql;
    }

    /** Build the ORDER BY fragment for the combined result, or empty string if none. */
    private function buildOrderBy(): string {
        return StringUtils::join($this->orderBy->toArray(), ', ', ' ORDER BY ');
    }

    /** Build the LIMIT and OFFSET fragments for the combined result, or empty string if neither is set. */
    private function buildLimitOffset(): string {
        $sql = '';

        if ($this->limit !== null) {
            $sql .= ' LIMIT ' . $this->limit;
        }

        if ($this->offset !== null) {
            $sql .= ' OFFSET ' . $this->offset;
        }

        return $sql;
    }
}
