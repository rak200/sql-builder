<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * Base class for DDL constraint builders.
 *
 * Provides the shared constraint name property and its fluent setter.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
abstract class Constraint implements ExpressionInterface {

    /**
     * @param string $name Constraint name (empty for unnamed constraints).
     */
    public function __construct(public private(set) string $name = '') {}

    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    /** {@inheritdoc} */
    abstract public function __toString(): string;

    /**
     * Render this constraint with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderExpression($this);
    }
}
