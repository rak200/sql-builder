<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use InvalidArgumentException;
use Rak200\SqlBuilder\Common\Expression\CaseWhen as CaseExpression;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders a {@see CaseExpression} as
 * `CASE [subject] WHEN ... THEN ... [ELSE ...] END[ AS alias]`.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class CaseExpressionRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(CaseExpression $component): string {
        if ($component->whens === []) {
            throw new InvalidArgumentException('CASE expression requires at least one WHEN clause.');
        }

        $sql = 'CASE';

        if ($component->subject !== null) {
            $sql .= ' ' . $this->dialect->renderExpression($component->subject);
        }

        foreach ($component->whens as $branch) {
            $sql .= sprintf(
                ' WHEN %s THEN %s',
                $this->dialect->renderExpression($branch['when']),
                $this->dialect->renderExpression($branch['then'])
            );
        }

        if ($component->else !== null) {
            $sql .= ' ELSE ' . $this->dialect->renderExpression($component->else);
        }

        $sql .= ' END';

        if ($component->alias !== null) {
            $sql .= ' AS ' . $this->dialect->quoteIdentifier($component->alias);
        }

        return $sql;
    }
}
