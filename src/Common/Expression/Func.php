<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\Reference\Column as ColumnRef;

/**
 * SQL function call expression (e.g. COUNT(*), UPPER(name)).
 *
 * Class name `Func` instead of `Function` because PHP reserves the latter.
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Func extends Expr {

    /** @var string Uppercased function name. */
    public readonly string $name;

    /** @var ExpressionInterface[] Normalised argument expressions. */
    public readonly array $arguments;

    public function __construct(string $name, mixed ...$arguments) {
        $this->name = strtoupper($name);
        $this->arguments = array_map(
            static function (mixed $argument): ExpressionInterface {
                if ($argument instanceof ExpressionInterface) {
                    return $argument;
                }
                if (is_string($argument)) {
                    return new ColumnRef($argument);
                }
                return new Value($argument);
            },
            $arguments
        );
    }
}
