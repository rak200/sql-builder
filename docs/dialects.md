[← Docs index](README.md)

# Dialects

```php
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDb105Dialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dialect\Postgres\Postgres15Dialect;
```

A **dialect** owns how every builder turns into SQL. Builders are thin data carriers; calling `__toString()` is just `Dialect::default()->renderXxx($this)`. To target a specific vendor or version, pass a dialect to `toSql()`:

```php
$query = Select::create()->from('users');

(string) $query;                                // default — backticks
$query->toSql(new PostgresDialect());           // double quotes
$query->toSql(Dialect::fromDsn('pgsql://h/db'));
```

## Available dialects

| Dialect | Identifier quoting | Behaviour |
|---------|-------------------|-----------|
| `DefaultDialect` | backticks (`` ` ``) | Permissive baseline; accepts every feature the builders expose |
| `PostgresDialect` | double quotes (`"`) | Postgres < 15; rejects `ON DUPLICATE KEY UPDATE`, `NULLS NOT DISTINCT`, `MERGE` |
| `Postgres15Dialect` | double quotes (`"`) | Postgres 15+; re-enables `NULLS [NOT] DISTINCT` and `MERGE` |
| `MariaDbDialect` | backticks (`` ` ``) | MariaDB / MySQL base; rejects `UPDATE ... FROM`, `DELETE ... USING`, `RETURNING`, `NULLS [NOT] DISTINCT`, `MERGE`; translates `Insert::onConflict()` → `ON DUPLICATE KEY UPDATE`; rewrites null-safe operators to `<=>`; simulates schemas via name-prefix flattening |
| `MariaDb105Dialect` | backticks (`` ` ``) | MariaDB 10.5+; re-enables `RETURNING` on INSERT/UPDATE/DELETE, otherwise inherits MariaDB behaviour |

[↑ Back to top](#)

## Selecting a dialect by DSN

`Dialect::fromDsn()` mirrors how PDO DSNs identify drivers:

```php
Dialect::fromDsn('mariadb://localhost/app');                  // MariaDbDialect
Dialect::fromDsn('mariadb://localhost/app?version=10.5');     // MariaDb105Dialect
Dialect::fromDsn('mysql://localhost/app');                    // MariaDbDialect
Dialect::fromDsn('postgres://localhost/app');                 // PostgresDialect
Dialect::fromDsn('pgsql://localhost/app');                    // PostgresDialect
Dialect::fromDsn('postgresql://localhost/app?version=15');    // Postgres15Dialect
Dialect::fromDsn('sqlite::memory:');                          // DefaultDialect (unknown scheme)
```

Recognised version hints:
- `mariadb`/`mysql` `?version=10.5` (or ≥ 10.5) → `MariaDb105Dialect`
- `postgres`/`pgsql`/`postgresql` `?version=15` (or ≥ 15) → `Postgres15Dialect`

Older versions or unknown schemes fall back to the closest base dialect rather than throwing.

[↑ Back to top](#)

## Vendor-specific feature gates

When a builder asks a dialect to render a feature it does not support, the dialect throws `Rak200\SqlBuilder\Dialect\UnsupportedFeatureException` at render time. The gate fires the moment you call `__toString()` / `toSql()` / `prepare()` — not when you call the builder method.

Selected gates:

| Feature | Default | Postgres < 15 | Postgres 15+ | MariaDB | MariaDB 10.5+ |
|---------|:-------:|:-------------:|:------------:|:-------:|:-------------:|
| `INSERT ... ON DUPLICATE KEY UPDATE` | ✅ | ❌ | ❌ | ✅ | ✅ |
| `INSERT ... ON CONFLICT (...) DO UPDATE` | ✅ | ✅ | ✅ | ✅ *(translated)* | ✅ *(translated)* |
| `INSERT ... ON CONFLICT (...) DO NOTHING` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `onConflictWhere()` | ✅ | ✅ | ✅ | ❌ | ❌ |
| `RETURNING` on INSERT/UPDATE/DELETE | ✅ | ✅ | ✅ | ❌ | ✅ |
| `UPDATE ... FROM` (multi-table) | ✅ | ✅ | ✅ | ❌ | ❌ |
| `DELETE ... USING` (multi-table) | ✅ | ✅ | ✅ | ❌ | ❌ |
| `MERGE` (SQL:2003) | ✅ | ❌ | ✅ | ❌ | ❌ |
| `NULLS [NOT] DISTINCT` on UNIQUE | ✅ | ❌ | ✅ | ❌ | ❌ |
| `SCHEMA` DDL (CREATE/DROP/ALTER) | ✅ | ✅ | ✅ | ❌ *(table-prefix sim)* | ❌ |
| `LATERAL` joins | ✅ | ✅ | ✅ | ✅ | ✅ |
| `GROUPING SETS` / `ROLLUP` / `CUBE` | ✅ | ✅ | ✅ | ✅ | ✅ |

[↑ Back to top](#)

## Null-safe comparison (MariaDB)

`Binary::NullSafeEq` and `Binary::NullSafeNe` render as `IS NOT DISTINCT FROM` / `IS DISTINCT FROM` on the default and Postgres dialects (SQL standard). The MariaDB dialect rewrites them to the native spaceship operator:

```php
Expr::binary('a', Binary::NullSafeEq, null);

// Default / Postgres: (`a` IS NOT DISTINCT FROM NULL)
// MariaDB:            (`a` <=> NULL)

Expr::binary('a', Binary::NullSafeNe, 1);
// Default / Postgres: (`a` IS DISTINCT FROM 1)
// MariaDB:            NOT (`a` <=> 1)
```

[↑ Back to top](#)

## UUID simulation

`DataType::Uuid` and the `Expr::uuid()` / `Expr::uuidColumn()` wrappers render differently per dialect:

| Aspect | Default / Postgres | MariaDB / MySQL |
|--------|-------------------|-----------------|
| Column type | `UUID` | `BINARY(16)` |
| `Expr::uuid('aaaa-…')` | `'aaaa-…'` (Postgres adds `::uuid` cast on literals/parameters) | `UUID_TO_BIN('aaaa-…')` |
| `Expr::uuid(Expr::ref('id'))` | `` `id` `` (no cast on column refs) | `UUID_TO_BIN(`id`)` |
| `Expr::uuidColumn('id')` | `` `id` `` | `BIN_TO_UUID(`id`)` (alias hoisted outside the call) |

Postgres adds the `::uuid` cast only when it's needed (literal / parameter contexts where Postgres can't infer the type). For example:

```php
Insert::create()->into('users')->columns('id')->values(Expr::uuid('aaaa-bbbb'));
// Postgres: INSERT INTO "users" ("id") VALUES ('aaaa-bbbb'::uuid)
// MariaDB:  INSERT INTO `users` (`id`) VALUES (UUID_TO_BIN('aaaa-bbbb'))
```

[↑ Back to top](#)

## Schema simulation (MariaDB)

MariaDB has no schema namespace independent of the database. The dialect flattens any `schema.table` reference to `schema_table`:

```php
Select::create()->from('reporting.events')->toSql(new MariaDbDialect());
// SELECT * FROM `reporting_events`

Expr::ref('reporting.events.id')->toSql(new MariaDbDialect());
// `reporting_events`.`id`
```

Schema-level DDL (`CREATE SCHEMA reporting`) is rejected with `UnsupportedFeatureException` — the simulation is a naming convention for tables, not a physical schema.

Two `Dialect` hooks drive this and are available for any dialect to override:

```php
$dialect->resolveTableName('reporting.events');         // → 'reporting_events' on MariaDB, unchanged on Postgres
$dialect->resolveColumnReference('reporting.events.id'); // → 'reporting_events.id' on MariaDB, unchanged on Postgres
```

[↑ Back to top](#)

## Writing a new dialect

Adding support for a new vendor is additive — no need to touch any builder.

1. Create `src/Dialect/<Vendor>/<Vendor>Dialect.php` extending `DefaultDialect` (or another existing dialect).
2. If the vendor uses different identifier quoting or string escaping, override `quoteIdentifier()` and `quoteValue()` on the dialect itself.
3. For each component whose rendering deviates, create a renderer in `src/Dialect/<Vendor>/Renderer/` extending the matching default renderer, then override the protected `xxxRenderer()` accessor on the dialect to wire it in.
4. Add unit tests under `tests/Unit/Dialect/` — both vendor-specific assertions and a dialect-propagation case to make sure nested expressions inherit the dialect.
5. Register the scheme in `src/Dialect/Dsn/DsnParser.php` if you want DSN-based selection.

The internal `CLAUDE.md` (project root) has more detail on the renderer composition model.

[↑ Back to top](#)

## Default dialect singleton

`Dialect::default()` returns a process-wide singleton — the same instance every time. `__toString()` on every builder uses it. To avoid mutating the singleton when running prepared statements, call `withBinder()` (which clones the dialect and resets renderer caches on the clone). End users typically don't call this directly — `prepare()` does.

[↑ Back to top](#)
