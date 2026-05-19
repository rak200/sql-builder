<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\ExpressionInterface;

/**
 * Base class for DDL constraint builders.
 *
 * Provides the shared constraint name property and its fluent setter.
 * Subclasses implement {@see __toString()} to produce the constraint-specific SQL fragment.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
abstract class Constraint implements ExpressionInterface {

    /** @var string $name Constraint name, empty when unnamed */
    protected string $name;

    /**
     * @param string $name Constraint name (empty for unnamed constraints).
     */
    public function __construct(string $name = '') {
        $this->name = $name;
    }

    /**
     * Set the constraint name.
     *
     * @param string $name Constraint name.
     * @return static
     */
    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    /** {@inheritdoc} */
    abstract public function __toString(): string;
}
