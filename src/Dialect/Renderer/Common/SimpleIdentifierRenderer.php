<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Common;

use Rak200\SqlBuilder\Common\SimpleIdentifier;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;

/**
 * Renders a {@see SimpleIdentifier} (unqualified column name, used in USING).
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class SimpleIdentifierRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(SimpleIdentifier $component): string {
        return $this->dialect->quoteIdentifier($component->name);
    }
}
