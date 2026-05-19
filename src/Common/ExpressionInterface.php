<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

use Rak200\Caster\Contracts\ToString;

/**
 * Expression interface.
 *
 * Defines a contract for SQL expression builders that can be converted to SQL strings.
 * All SQL expression classes must implement this interface.
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
interface ExpressionInterface extends ToString {}
