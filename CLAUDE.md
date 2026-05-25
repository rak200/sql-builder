# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**rak200/sql-builder** is a standalone PHP 8.4+ library for building SQL strings via a fluent, type-safe API. It covers DML (SELECT, set operations) and DDL (tables, views, sequences, constraints, indexes). No ORM — it produces SQL strings only.

Depends on:
- `rak200/collections 0.*` for the typed `Vector` container used internally
- `rak200/utils ^1.0.0` for `Rak200\Utils\Str` (blank checks, `join` with `lastSeparator`, `wrap`) used by renderers

`ExpressionInterface` extends PHP's native {@see \Stringable} (PHP 8.0+); the previous `rak200/caster` dependency was removed in 0.12.0 since the contract is just `__toString(): string`.

Dev dependencies:
- `phpunit/phpunit ^13.1` for the test suite

## Conventions

- `declare(strict_types=1)` at the top of every file.
- **Documentation is mandatory.** Every class carries a PHPDoc class summary (one short paragraph) plus the `@author rak200 <rak.ricardo@windowslive.com>` tag. Every `public` method carries a PHPDoc that states what it does — `@param`/`@return`/`@throws` tags are added only when they convey information beyond the type signature (units, semantics, edge-case behaviour, exception condition). Private helpers are documented only when the implementation is non-obvious.


## Structure

```
sql-builder/
├── src/
│   ├── Common/
│   │   ├── Expr.php                  # abstract factory base
│   │   ├── ExpressionInterface.php   # extends native \Stringable
│   │   ├── Expression/*.php          # Binary, Unary, Column, Value, Raw, Func, CaseWhen, Exists,
│   │   │                             # Subquery, Param, UuidInput, UuidOutput, Window, Grouping
│   │   ├── Reference/*.php           # Column, Table, Identifier
│   │   ├── Enum/
│   │   │   ├── Operator/*.php        # Binary, Math, Unary
│   │   │   ├── Sort/*.php            # Direction, Nulls
│   │   │   └── *.php                 # JoinType, ForeignKeyAction, CheckOption, DataType, GroupingMode
│   │   ├── Join.php, Order.php, Window.php   # value objects
│   ├── Dml/                          # Select, Set, Insert, Update, Delete, Merge, MergeClause, Cte
│   ├── Ddl/                          # Column, Table, View, Sequence, Index, Schema + constraints
│   ├── Dialect/                      # see "Dialect Architecture" below
│   └── Prepared/                     # PreparedStatement, Binder
└── tests/
    ├── Unit/                         # mirrors src/ layout
    └── Integration/
```

Production classes live under `Rak200\SqlBuilder\` (PSR-4 from `src/`); test classes live under `Rak200\SqlBuilder\Tests\` (PSR-4 from `tests/`, dev-only).

## Key Abstractions

**`ExpressionInterface`** — everything that renders to SQL string implements this (extends `ToString` → `__toString()`).

**`Expr`** (abstract) — factory base with the static methods used everywhere:
- `Expr::col($name, ?$alias)` — SELECT-projection column (returns `Expression\Column`)
- `Expr::val($value)` — literal value (returns `Expression\Value`)
- `Expr::ref($name)` — column reference for conditions/ORDER BY (returns `Reference\Column`)
- `Expr::binary($l, Operator\Binary|Operator\Math, $r)` — predicate or arithmetic
- `Expr::and(...)` / `Expr::or(...)` / `Expr::not($x)` — logical groups
- `Expr::add/sub/mul/div/mod(...)` — arithmetic chains
- `Expr::case($subject?)` — `CASE WHEN`
- `Expr::count/sum/avg/max/min($expr)` — aggregates
- `Expr::func($name, ...$args)` — generic function call
- `Expr::raw($sql)` — escape hatch
- `Expr::exists($select)` — `EXISTS (...)`
- `Expr::subquery($select, ?$alias)` — subquery expression
- `Expr::param(int|string $key, $value?)` — prepared-statement placeholder
- `Expr::uuid($value)` / `Expr::uuidColumn($name, ?$alias)` — UUID wrappers
- `Expr::over($func, Window $window)` — windowed function
- `Expr::rollup(...)` / `Expr::cube(...)` / `Expr::groupingSets(...)` — GROUP BY extensions (arrays in `groupingSets` become tuples; `[]` emits the grand-total grouping)
- `Expr::identifier($name)` — bare identifier for `USING (...)`

**`Select`** — main DML builder; fluent chain: `->select()->from()->join()->where()->groupBy()->having()->orderBy()->limit()->offset()`. Plus `->with()` / `->withRecursive()` for CTEs and `->lateralJoin()` / `->leftLateralJoin()` / `->crossLateralJoin()` for `LATERAL` joins.

**`Set`** — wraps multiple `Select` with set operators: `Set::union()`, `Set::unionAll()`, `Set::except()`, `Set::intersect()`.

**`Insert`** — exposes both the legacy MariaDB-flavoured `onDuplicateKeyUpdate($col, $val)` and the portable `onConflict($cols)->doUpdate($assignments)` / `->doNothing()` / `->onConflictWhere($predicate)` API. The dialect layer translates `onConflict` to native syntax: standard `ON CONFLICT (...) DO UPDATE` on default/Postgres, `ON DUPLICATE KEY UPDATE` on MariaDB / MySQL. Mixing the two on the same statement throws.

**`Merge`** — SQL:2003 `MERGE INTO target USING source ON cond WHEN [NOT] MATCHED [AND pred] THEN ...`. Branch helpers: `whenMatchedUpdate(assignments, predicate?)`, `whenMatchedDelete(predicate?)`, `whenNotMatchedInsert(columns, values, predicate?)`, `whenDoNothing(matched, predicate?)`. Accepted on the default dialect and on `Postgres15Dialect`; rejected on older Postgres and on every MariaDB dialect.

**`Vector`** (from `rak200/collections`) — typed generic container used internally by `Select`, `Set`, `Table` for elements like CTEs, columns, joins, GROUP BY items, ORDER BY entries, and table elements (renamed from `Collection` in 0.0.3).

## Identifier & Value Quoting

`Dialect::quoteIdentifier()` produces backticks on the default dialect and double quotes on Postgres. `Dialect::quoteValue()` handles inline values by type:
- Strings → `'value'` (single-quoted, backslash-escaped on default; standard-conforming on Postgres)
- Numbers → unquoted
- `null` → `NULL`
- `true`/`false` → `TRUE`/`FALSE`

For prepared-statement parameter binding (no inline values), use `->prepare(Dialect)` which returns a `PreparedStatement` with `sql` + `parameters`. See the Prepared/ folder.

## Dialect Architecture

Landed in 0.2.0. The dialect layer makes SQL rendering portable across databases without bloating builders with per-vendor branches. Builders are thin data carriers — state + validation + factory methods — and a `Dialect` instance owns *how* each component turns into SQL.

### Goals (all met in 0.2.0)

1. A **default dialect** (`DefaultDialect`) that permits every feature — used as the inheritance root and the implicit target of `__toString()`.
2. **Database-specific dialects** extend the default and override individual component renderers to throw, silently ignore, or simulate a feature.
3. **Version-specific dialects** branch off the database dialect (e.g. `MariaDb105Dialect` extending `MariaDbDialect` to re-enable `RETURNING`).
4. **All rendering logic that used to live in builder `__toString()` methods is now in renderer classes.** The builders' `__toString()` does nothing but `return Dialect::default()->renderXxx($this);`.
5. Each renderable component (Select, Insert, Column, ForeignKey, …) has its **own renderer class** under `src/Dialect/Renderer/`. Dialects compose these renderers, so a subclass can swap one without rewriting the whole dialect.
6. **`__toString()` uses the default dialect**; passing a specific dialect is opt-in via `toSql(Dialect $dialect)` on every component.
7. The dialect is selected at runtime via `Dialect::fromDsn(string $dsn)`, mirroring how PDO DSNs identify the driver.

### Class layout

```
src/Dialect/
├── Dialect.php                  # abstract base + polymorphic renderExpression() dispatch
├── DefaultDialect.php           # permissive baseline; composes default renderers
├── UnsupportedFeatureException.php
├── Dsn/DsnParser.php            # DSN scheme → Dialect instance
├── Renderer/                    # one renderer class per renderable component
│   ├── Common/                  # expression-level renderers (Binary, Column, Value, …)
│   ├── Dml/                     # statement renderers (Select, Insert, Update, Delete, Set, Cte)
│   └── Ddl/                     # schema renderers (Table, Column, View, Sequence, Index, constraints, Schema)
├── MariaDb/
│   ├── MariaDbDialect.php       # rejects Postgres-only FROM/USING and RETURNING on writes
│   ├── MariaDb105Dialect.php    # re-enables RETURNING
│   └── Renderer/                # vendor overrides (Insert/Update/Delete gating, schema flattening, UUID wrappers, null-safe rewrite, …)
└── Postgres/
    ├── PostgresDialect.php      # double-quoted identifiers, standard-conforming strings, $N binder
    ├── Postgres15Dialect.php    # placeholder for MERGE / NULLS NOT DISTINCT
    └── Renderer/                # Insert (rejects ON DUPLICATE KEY UPDATE), UuidInput (::uuid cast)
```

Postgres-only multi-table forms (`UPDATE ... FROM`, `DELETE ... USING`) and `RETURNING` are inherited from the permissive default — `PostgresDialect` only needs the quoting / string-escape overrides on the dialect itself plus a couple of renderer overrides.

### The `Dialect` contract

```php
abstract class Dialect {
    abstract public function quoteIdentifier(string $identifier): string;
    abstract public function quoteValue(mixed $value): string;

    // One abstract method per renderable component (Select, Insert, ..., Order, Join).
    abstract public function renderSelect(Select $component): string;
    abstract public function renderInsert(Insert $component): string;
    // ...

    /** Polymorphic dispatch by concrete expression type — used by renderers to
     *  render nested ExpressionInterface instances without duplicating the
     *  type-to-renderer switch in every renderer. */
    public function renderExpression(ExpressionInterface $expression): string;

    public static function default(): self;          // lazy singleton → DefaultDialect
    public static function fromDsn(string $dsn): self; // delegates to Dsn\DsnParser
}
```

`DefaultDialect` composes one renderer per component via **protected `xxxRenderer()` accessor methods** with lazy `??=` initialisation. A subclass overrides only the accessors whose renderer it wants to swap (e.g. `MariaDbDialect::insertRenderer()` returns `MariaDb\Renderer\InsertRenderer`). The renderer holds a back-reference to the owning dialect so nested rendering and identifier/value quoting always route through the right dialect.

### Builder side — how state is exposed

- Each component's `__toString()` is:
  ```php
  public function __toString(): string {
      return Dialect::default()->renderSelect($this);
  }

  public function toSql(Dialect $dialect): string {
      return $dialect->renderSelect($this);
  }
  ```
- Builder state is exposed to renderers using **PHP 8.4 asymmetric visibility**: `public private(set)` for fluent-mutable properties, and `public readonly` for value-object properties set once in the constructor. The fluent setter API is unchanged for callers; renderers just read `$component->columns`, `$component->where`, etc.
- Pre-render validation (e.g. "INSERT requires VALUES or SELECT", `Join::validate()`) lives in the builder or in the renderer entry-point — not in private build helpers, which have been removed entirely.

### Override patterns by example

- **Throw on unsupported feature** — `Postgres\Renderer\InsertRenderer::renderOnDuplicateKeyUpdate()` throws `UnsupportedFeatureException` because PostgreSQL uses `ON CONFLICT` instead. Same pattern in `MariaDb\Renderer\InsertRenderer::renderReturning()`.
- **Override at the dialect level** — `PostgresDialect::quoteIdentifier()` and `quoteValue()` are overridden directly on the dialect (not via a renderer), and every default renderer that calls `$this->dialect->quoteIdentifier(...)` automatically picks up the new behaviour. Most identifier-quoting overrides do *not* need a renderer override.
- **Hack/simulate** — `MariaDbDialect::resolveTableName()` flattens `schema.table` to `schema_table` (and `resolveColumnReference()` flattens the schema prefix in three-part column refs) so callers that address tables in a logical schema keep working on an engine that has no schema namespace. The `Schema` DDL builder, in turn, refuses to emit CREATE/DROP/ALTER SCHEMA on MariaDB — the schema simulation is purely a naming convention, not a physical operation on the database.

### Schema simulation hooks

`Dialect` exposes two concrete (non-abstract) override points used by every table-aware renderer:

```php
public function resolveTableName(string $name): string;        // default: identity
public function resolveColumnReference(string $name): string;  // default: identity
```

Every default renderer that emits a *table* identifier (CREATE/ALTER TABLE, RENAME TO, INSERT INTO, FROM/JOIN, CREATE VIEW, CREATE/ALTER SEQUENCE, REFERENCES, CREATE INDEX ... ON) runs the name through `resolveTableName()` before quoting. `ColumnReferenceRenderer` and `ColumnExpressionRenderer` run their names through `resolveColumnReference()`. The default dialect leaves both untouched; `MariaDbDialect` overrides them to do the schema-to-prefix flattening. Adding a new "schema simulation" for another engine is a single dialect-level override of those two methods.

### DSN parsing

`Dialect::fromDsn()` accepts common DSN forms and returns the right dialect:

| DSN scheme                                | Returned dialect                  |
| ----------------------------------------- | --------------------------------- |
| `mariadb://...`, `mysql://...`            | `MariaDb\MariaDbDialect`          |
| `postgres://...`, `pgsql://...`, `postgresql://...` | `Postgres\PostgresDialect` |
| Unknown / no scheme                       | `DefaultDialect`                  |

Version hints come from a `version` query-string parameter:
- `mariadb://host/db?version=10.5` (or any ≥10.5) → `MariaDb105Dialect`
- `postgres://host/db?version=15`   (or any ≥15)   → `Postgres15Dialect`

The parser is forgiving: unrecognised schemes or older versions fall back to the closest base dialect rather than throwing.

### Adding a new dialect

The migration that landed 0.2.0 (introduce the contract → decompose into renderers → add MariaDB/Postgres → add version variants → wire up `fromDsn`) is now done. Adding another database is additive:

1. Create `src/Dialect/<Vendor>/<Vendor>Dialect.php` extending `DefaultDialect` (or another base).
2. Override `quoteIdentifier()` / `quoteValue()` on the dialect itself if the vendor differs.
3. For each component whose rendering deviates, create a renderer in `src/Dialect/<Vendor>/Renderer/` extending the matching default renderer, and override the protected `xxxRenderer()` factory on the dialect to wire it in.
4. Add unit tests under `tests/Unit/Dialect/` — both vendor-specific assertions and a dialect-propagation case to make sure nested expressions inherit the dialect.
5. Register the scheme in `Dsn\DsnParser` if you want DSN-based selection.

## Testing

PHPUnit 13 is configured via `phpunit.xml` with two suites: `Unit` and `Integration`. The strict flags `failOnWarning` and `failOnRisky` are enabled — risky/incomplete tests fail the run.

Run:
- `composer test` — runs all suites
- `vendor/bin/phpunit --testsuite Unit` — only the unit suite
- `vendor/bin/phpunit tests/Unit/SomeTest.php` — single file

Test classes mirror the source namespace (e.g. `Rak200\SqlBuilder\Common\Expr` → `Rak200\SqlBuilder\Tests\Unit\Common\ExprTest`). Test methods follow PSR-12 camelCase (e.g. `testRendersQualifiedIdentifier`), **not** snake_case. Since the library only produces SQL strings, tests assert on the exact string output of expressions/builders — no database connection is required.

CI runs the **unit + integration suites** on every push and PR to `master` against PHP 8.4 and 8.5 (matrix). See `.github/workflows/tests.yml`. The integration suite (`tests/Integration/PdoSmokeTest.php`) runs against in-memory SQLite — see comments on that file for the SQLite-quirk caveats (`UNION` with parens, missing `MERGE`/`LATERAL`/`GROUPING SETS`/etc).

## Versioning

Follows [Semantic Versioning](https://semver.org). The `0.x` line is **unstable** while the API stabilises; the current version lives in `composer.json` and the README.

When releasing a new version:
1. Update `"version"` in `composer.json`
2. Update `CHANGELOG.md`: add a new `## [x.y.z] - YYYY-MM-DD` section with `### Added / Changed / Fixed / Removed` entries and a comparison link at the bottom
3. Update the version reference in `README.md`
4. Sweep `CLAUDE.md`: remove finished `## Planned: …` sections and refresh `## Structure` / `## Key Abstractions` if class names, folder layout or method names changed. Keep the file concise — prune rather than append.
5. Commit, then `git tag x.y.z` and `git push origin master && git push origin x.y.z`.

Consumers using `"type": "vcs"` in their `composer.json` resolve versions from git tags.

