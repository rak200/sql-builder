<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Ddl;

use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Ddl\Check;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders a {@see Check} as `[CONSTRAINT "name"] CHECK (condition)`.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class CheckRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Check $component): string {
        $sql = 'CHECK';

        if ($component->name !== '') {
            $sql = sprintf('CONSTRAINT %s CHECK', $this->dialect->quoteIdentifier($component->name));
        }

        $condition = $component->condition;
        if ($condition instanceof ExpressionInterface) {
            $condition = $this->dialect->renderExpression($condition);
        }

        if ($condition !== '') {
            $sql .= sprintf(' (%s)', $condition);
        }

        return $sql;
    }
}
