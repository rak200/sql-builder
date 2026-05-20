<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Dsn;

use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDb105Dialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\Postgres15Dialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;

/**
 * Parses PDO-style DSN strings to {@see Dialect} instances.
 *
 * Recognised schemes:
 * - `mariadb://...`, `mysql://...` → {@see MariaDbDialect}
 * - `postgres://...`, `pgsql://...`, `postgresql://...` → {@see PostgresDialect}
 *
 * Version hints come from the `version` query-string parameter; recognised
 * versions return a version-specific subclass:
 * - MariaDB ≥ 10.5 → {@see MariaDb105Dialect}
 * - PostgreSQL ≥ 15 → {@see Postgres15Dialect}
 *
 * Unknown schemes or versions fall back to the closest base dialect rather
 * than throwing.
 *
 * @package Rak200\SqlBuilder\Dialect\Dsn
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class DsnParser {

    /**
     * Parse a DSN string and return the matching {@see Dialect}.
     *
     * @param string $dsn The DSN string (e.g. `mariadb://h/db?version=10.5`).
     * @return Dialect
     */
    public static function parse(string $dsn): Dialect {
        $scheme  = self::extractScheme($dsn);
        $version = self::extractVersion($dsn);

        return match ($scheme) {
            'mariadb', 'mysql'                       => self::resolveMariaDb($version),
            'postgres', 'pgsql', 'postgresql'        => self::resolvePostgres($version),
            default                                  => new DefaultDialect(),
        };
    }

    private static function extractScheme(string $dsn): string {
        if (preg_match('/^([a-zA-Z][a-zA-Z0-9+\-]*):/', $dsn, $matches) === 1) {
            return strtolower($matches[1]);
        }
        return '';
    }

    private static function extractVersion(string $dsn): ?string {
        $queryStart = strpos($dsn, '?');
        if ($queryStart === false) {
            return null;
        }

        parse_str(substr($dsn, $queryStart + 1), $params);
        $version = $params['version'] ?? null;

        return is_string($version) ? $version : null;
    }

    private static function resolveMariaDb(?string $version): Dialect {
        if ($version !== null && self::versionAtLeast($version, '10.5')) {
            return new MariaDb105Dialect();
        }
        return new MariaDbDialect();
    }

    private static function resolvePostgres(?string $version): Dialect {
        if ($version !== null && self::versionAtLeast($version, '15')) {
            return new Postgres15Dialect();
        }
        return new PostgresDialect();
    }

    private static function versionAtLeast(string $candidate, string $minimum): bool {
        return version_compare($candidate, $minimum, '>=');
    }
}
