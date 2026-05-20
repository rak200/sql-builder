<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Postgres;

/**
 * PostgreSQL 15+ dialect.
 *
 * Placeholder for version-specific behaviour (e.g. MERGE statement support,
 * NULLS NOT DISTINCT on unique constraints). Inherits the base
 * {@see PostgresDialect} unchanged for now.
 *
 * @package Rak200\SqlBuilder\Dialect\Postgres
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Postgres15Dialect extends PostgresDialect {}
