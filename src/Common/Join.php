<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\SqlBuilder\Common\Enum\JoinType;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Utils\StringUtils;
use InvalidArgumentException;

/**
 * SQL JOIN clause builder supporting INNER, LEFT, RIGHT, FULL, CROSS and NATURAL variants.
 *
 * @package Rak200\SqlBuilder\Common
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
final class Join implements ExpressionInterface {

    /** @var TableReference $table The joined table or subquery */
    private TableReference $table;

    /**
     * @param JoinType $type JOIN type.
     * @param string|Select $table Table name or SELECT subquery to join.
     * @param string|null $alias Optional alias for the joined table.
     * @param ExpressionInterface|null $on Optional ON condition.
     * @param bool $natural Whether this is a NATURAL JOIN.
     * @param array<string>|null $using Column list for USING clause.
     */
    public function __construct(
        private JoinType $type,
        string|Select $table,
        ?string $alias = null,
        private ?ExpressionInterface $on = null,
        private bool $natural = false,
        private ?array $using = null
    ) {
        $this->table = new TableReference($table, $alias);
    }

    /**
     * Set the ON condition for the join.
     *
     * @param ExpressionInterface $condition Join condition expression.
     * @return static
     */
    public function on(ExpressionInterface $condition): static {
        $this->on = $condition;
        return $this;
    }

    /**
     * Mark this join as a NATURAL JOIN.
     *
     * @return static
     */
    public function natural(): static {
        $this->natural = true;
        return $this;
    }

    /**
     * Set the USING column list for the join.
     *
     * @param mixed ...$columns Column names or expressions.
     * @return static
     */
    public function using(mixed ...$columns): static {
        $this->using = array_map(fn($column) => $column instanceof ExpressionInterface ? $column : Expression::identifier((string) $column), $columns);
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        $this->validate();

        $type = $this->natural ? "NATURAL {$this->type->value}" : $this->type->value;
        $sql  = "$type {$this->table}";

        if ($this->on !== null) {
            return "$sql ON {$this->on}";
        }

        return StringUtils::join($this->using ?? [], ', ', "$sql USING (", ')');
    }

    /**
     * Validate that the join configuration is consistent.
     *
     * @throws \InvalidArgumentException On conflicting join options.
     */
    private function validate(): void {
        if ($this->natural && $this->on !== null) {
            throw new InvalidArgumentException('NATURAL JOIN cannot have an ON condition.');
        }

        if ($this->natural && $this->using !== null && count($this->using) > 0) {
            throw new InvalidArgumentException('NATURAL JOIN cannot have a USING clause.');
        }

        if ($this->using !== null && $this->on !== null) {
            throw new InvalidArgumentException('JOIN cannot have both ON and USING.');
        }

        if ($this->type === JoinType::CROSS && $this->on !== null) {
            throw new InvalidArgumentException('CROSS JOIN cannot have an ON condition.');
        }

        if ($this->type === JoinType::CROSS && $this->using !== null && count($this->using) > 0) {
            throw new InvalidArgumentException('CROSS JOIN cannot use USING.');
        }

        if ($this->using !== null && count($this->using) === 0) {
            throw new InvalidArgumentException('USING must have at least one column.');
        }
    }

}
