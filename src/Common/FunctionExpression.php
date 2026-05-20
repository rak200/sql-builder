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

    /** @var string Uppercased function name. */
    public readonly string $name;

    /** @var ExpressionInterface[] Normalised argument expressions. */
    public readonly array $arguments;

    /**
     * @param string $name Function name (automatically uppercased).
     * @param mixed ...$arguments Arguments passed to the function.
     */
    public function __construct(string $name, mixed ...$arguments) {
        $this->name = strtoupper($name);
        $this->arguments = array_map(
            static fn($argument): ExpressionInterface => Expression::normalize($argument),
            $arguments
        );
    }
}
