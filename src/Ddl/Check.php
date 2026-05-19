<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\ExpressionInterface;

/**
 * DDL Check constraint builder.
 *
 * Builds SQL CHECK constraint definitions for column and table-level value validation.
 * Supports both raw strings and expression objects as conditions.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Check extends Constraint {

    /** @var ExpressionInterface|string $condition The check condition */
    private ExpressionInterface|string $condition = '';

    /**
     * @param string $name Name of the check constraint.
     * @param ExpressionInterface|string $condition The check condition.
     */
    public function __construct(string $name = '', ExpressionInterface|string $condition = '') {
        parent::__construct($name);
        $this->condition = $condition;
    }

    /**
     * Create a new check constraint builder.
     *
     * @param string|null $name Constraint name (optional).
     * @return static
     */
    public static function create(?string $name = null): static {
        return new static($name ?? '');
    }

    /**
     * Set the check condition.
     *
     * @param ExpressionInterface|string $condition The condition as an expression or string.
     * @return static
     */
    public function condition(ExpressionInterface|string $condition): static {
        $this->condition = $condition;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        $sql = 'CHECK';

        if ($this->name) {
            $sql = sprintf('CONSTRAINT "%s" CHECK', $this->name);
        }

        if ($this->condition) {
            $sql .= sprintf(' (%s)', $this->condition);
        }

        return $sql;
    }
}
