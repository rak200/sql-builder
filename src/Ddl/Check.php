<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * DDL Check constraint builder.
 *
 * Supports both raw strings and expression objects as conditions.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Check extends Constraint {

    /**
     * @param string $name Name of the check constraint.
     * @param ExpressionInterface|string $condition The check condition.
     */
    public function __construct(
        string $name = '',
        public private(set) ExpressionInterface|string $condition = ''
    ) {
        parent::__construct($name);
    }

    /** Create a CHECK constraint with an optional name. */
    public static function create(?string $name = null): static {
        return new static($name ?? '');
    }

    /** Set the boolean condition the constraint enforces. */
    public function condition(ExpressionInterface|string $condition): static {
        $this->condition = $condition;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderCheck($this);
    }
}
