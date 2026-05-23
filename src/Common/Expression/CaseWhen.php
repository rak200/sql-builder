<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use InvalidArgumentException;
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\ExpressionInterface;

/**
 * SQL `CASE ... WHEN ... THEN ... [ELSE ...] END` expression.
 *
 * Supports both forms:
 * - **Searched CASE**: `CASE WHEN <condition> THEN <result> ... END`.
 *   Build with `Expr::case()` (no subject); each `when()` requires an
 *   {@see ExpressionInterface} condition (typically a binary expression).
 * - **Simple CASE**: `CASE <subject> WHEN <value> THEN <result> ... END`.
 *   Build with `Expr::case($subject)`. Bare scalar `when()` arguments are
 *   wrapped as {@see Value}; pass an {@see ExpressionInterface} to compare
 *   against another column / expression.
 *
 * Class name `CaseWhen` instead of `Case` because PHP reserves the latter.
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class CaseWhen extends Expr {

    /** @var ExpressionInterface|null Optional subject for simple-form CASE. */
    public private(set) ?ExpressionInterface $subject;

    /** @var array<int, array{when: ExpressionInterface, then: ExpressionInterface}> */
    public private(set) array $whens = [];

    /** @var ExpressionInterface|null Optional ELSE result. */
    public private(set) ?ExpressionInterface $else = null;

    public function __construct(?ExpressionInterface $subject = null) {
        $this->subject = $subject;
    }

    /**
     * Append a `WHEN condition THEN result` branch.
     *
     * @throws InvalidArgumentException When a non-expression condition is used in searched form.
     */
    public function when(mixed $condition, mixed $result): static {
        $this->whens[] = [
            'when' => $this->normalizeCondition($condition),
            'then' => $this->normalizeResult($result),
        ];
        return $this;
    }

    /**
     * Set the `ELSE` branch.
     */
    public function else(mixed $result): static {
        $this->else = $this->normalizeResult($result);
        return $this;
    }

    private function normalizeCondition(mixed $value): ExpressionInterface {
        if ($value instanceof ExpressionInterface) {
            return $value;
        }
        if ($this->subject === null) {
            throw new InvalidArgumentException(
                'Searched CASE WHEN requires an ExpressionInterface condition; got ' . get_debug_type($value) . '.'
            );
        }
        return new Value($value);
    }

    private function normalizeResult(mixed $value): ExpressionInterface {
        return $value instanceof ExpressionInterface ? $value : new Value($value);
    }
}
