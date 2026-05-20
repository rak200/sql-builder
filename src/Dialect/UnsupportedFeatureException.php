<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect;

use RuntimeException;

/**
 * Thrown when a dialect is asked to render a feature it does not support
 * (e.g. PostgreSQL receiving an `ON DUPLICATE KEY UPDATE` clause).
 *
 * @package Rak200\SqlBuilder\Dialect
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class UnsupportedFeatureException extends RuntimeException {}
