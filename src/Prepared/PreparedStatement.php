<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Prepared;

/**
 * Result of preparing a statement for parameter binding.
 *
 * Pairs the rendered SQL string with the parameter array suitable for
 * {@see \PDO::prepare()} / {@see \PDOStatement::execute()}. The parameter
 * array is mutable so callers can rebind values between runs without
 * re-rendering the SQL.
 *
 * Shape of `$parameters` follows the dialect that produced it:
 * - Named (`:name`)            → associative `['name' => value, …]`
 * - Postgres positional (`$N`) → list, one entry per unique key
 * - MariaDB positional (`?`)   → list, one entry per textual occurrence
 *
 * @package Rak200\SqlBuilder\Prepared
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class PreparedStatement {

    /**
     * @param string $sql The rendered SQL string with placeholders.
     * @param array<int|string, mixed> $parameters Bound parameter values.
     */
    public function __construct(
        public readonly string $sql,
        public array $parameters = []
    ) {}
}
