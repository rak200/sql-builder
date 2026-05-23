<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common\Expression;

use Rak200\SqlBuilder\Common\Expr;

/**
 * Declarative placeholder for a prepared-statement parameter.
 *
 * Carries a key (`int` for positional, `string` for named) and an optional
 * default value. The key drives reuse semantics in the binder:
 * - String keys → `:name` on every dialect; reused, single array entry.
 * - Int keys + Postgres → `$N`; reused natively, single array entry.
 * - Int keys + MariaDB/MySQL → fresh `?` per occurrence, value duplicated.
 *
 * The default value is bound when the placeholder is first emitted. Callers
 * can override values per run via the resulting
 * {@see \Rak200\SqlBuilder\Prepared\PreparedStatement::$parameters} array.
 *
 * @package Rak200\SqlBuilder\Common\Expression
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class Param extends Expr {

    public function __construct(
        public readonly int|string $key,
        public readonly mixed $value = null
    ) {}
}
