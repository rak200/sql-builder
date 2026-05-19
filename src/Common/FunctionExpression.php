<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

/**
 * SQL function call expression (e.g. COUNT(*), UPPER(name)).
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class FunctionExpression extends Expression {
    /** @var ExpressionInterface[] $arguments Normalized argument expressions */
    private array $arguments;

    /**
     * @param string $name Function name (automatically uppercased).
     * @param mixed ...$arguments Arguments passed to the function.
     */
    public function __construct(private string $name, mixed ...$arguments) {
        $this->name = strtoupper($name);
        $this->arguments = array_map(static fn ($argument) => Expression::normalize($argument), $arguments);
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return sprintf('%s(%s)%s', $this->name, implode(', ', $this->arguments), $this->aliasToSql());
    }
}
