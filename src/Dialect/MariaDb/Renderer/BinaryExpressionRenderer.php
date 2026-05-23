<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\MariaDb\Renderer;

use Rak200\SqlBuilder\Common\Expression\Binary as BinaryExpression;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary as BinaryOperator;
use Rak200\SqlBuilder\Dialect\Renderer\Common\BinaryExpressionRenderer as BaseBinaryExpressionRenderer;

/**
 * MariaDB binary-expression renderer.
 *
 * Rewrites the SQL-standard null-safe operators that the default dialect
 * emits (`IS NOT DISTINCT FROM` / `IS DISTINCT FROM`) to MariaDB's native
 * spaceship operator: `<=>` for equal, and `NOT (a <=> b)` for not-equal.
 *
 * All other operators (comparisons, pattern matching, logical, …) fall
 * through to the default renderer unchanged.
 *
 * @package Rak200\SqlBuilder\Dialect\MariaDb\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class BinaryExpressionRenderer extends BaseBinaryExpressionRenderer {

    public function render(BinaryExpression $component): string {
        if ($component->operator === BinaryOperator::NullSafeEq) {
            return $this->renderSpaceship($component, negated: false);
        }

        if ($component->operator === BinaryOperator::NullSafeNe) {
            return $this->renderSpaceship($component, negated: true);
        }

        return parent::render($component);
    }

    private function renderSpaceship(BinaryExpression $component, bool $negated): string {
        $spaceship = sprintf(
            '(%s <=> %s)',
            $this->dialect->renderExpression($component->left),
            $this->dialect->renderExpression($component->right)
        );

        $sql = $negated ? 'NOT ' . $spaceship : $spaceship;

        if ($component->alias !== null) {
            $sql .= ' AS ' . $this->dialect->quoteIdentifier($component->alias);
        }

        return $sql;
    }
}
