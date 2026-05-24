<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Stringable;

/**
 * Expression interface.
 *
 * Defines a contract for SQL expression builders that can be converted to SQL strings.
 * All SQL expression classes must implement this interface.
 *
 * Extends the native PHP {@see Stringable} (PHP 8.0+) — the contract is
 * simply `__toString(): string`. The previous `rak200/caster` `ToString`
 * dependency was dropped in 0.12.0.
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
interface ExpressionInterface extends Stringable {}
