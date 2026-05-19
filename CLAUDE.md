# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Project Overview

**rak200/sql-builder** is a standalone PHP 8.4+ library for building SQL strings via a fluent, type-safe API. It covers DML (SELECT, set operations) and DDL (tables, views, sequences, constraints, indexes). No ORM — it produces SQL strings only.

Depends on:
- `rak200/caster ^1.0.0` for the `ToString` contract used by `ExpressionInterface`
- `rak200/collections ^0.0.1` for the typed `Collection` container used internally

Dev dependencies:
- `phpunit/phpunit ^13.1` for the test suite

## Structure

```
sql-builder/
├── src/
│   ├── Common/           # Shared expression building blocks
│   │   ├── Enum/         # BinaryOperator, UnaryOperator, JoinType, SortDirection, NullsPlacement, ForeignKeyAction, CheckOption
│   │   ├── ExpressionInterface.php   # extends Rak200\Caster\Contracts\ToString
│   │   ├── Expression.php            # abstract base with factory methods
│   │   ├── Join.php, Order.php       # JOIN and ORDER BY value objects
│   │   └── *Expression.php           # concrete expression types
│   ├── Dml/              # Select, Set (UNION/EXCEPT/INTERSECT), Insert, Update, Delete
│   ├── Ddl/
│   │   ├── Enum/DataType.php         # SQL column type enum
│   │   ├── Column.php, Table.php, View.php, Sequence.php, Index.php
│   │   └── Constraint.php, PrimaryKey.php, UniqueKey.php, ForeignKey.php, Check.php
│   └── Utils/            # Internal: StringUtils (not part of public API)
└── tests/
    ├── Unit/             # Fast, isolated tests against single classes
    └── Integration/      # End-to-end SQL generation tests across multiple builders
```

Production classes live under `Rak200\SqlBuilder\` (PSR-4 from `src/`); test classes live under `Rak200\SqlBuilder\Tests\` (PSR-4 from `tests/`, dev-only).

## Key Abstractions

**`ExpressionInterface`** — everything that renders to SQL string implements this (extends `ToString` → `__toString()`).

**`Expression`** (abstract) — base class with static factory methods used everywhere:
- `Expression::binary($left, BinaryOperator, $right)` — comparison/logical
- `Expression::and(...$exprs)` / `Expression::or(...$exprs)` — logical groups
- `Expression::column($ref)` — column reference
- `Expression::raw($sql)` — escape hatch for raw SQL
- `Expression::count/sum/avg/max/min($col)` — aggregate functions
- `Expression::exists($subquery)` — EXISTS clause

**`Select`** — main DML builder; fluent chain: `->select()->from()->join()->where()->groupBy()->having()->orderBy()->limit()->offset()`.

**`Set`** — wraps multiple `Select` with set operators: `Set::union()`, `Set::unionAll()`, `Set::except()`, `Set::intersect()`.

**`Collection`** (from `rak200/collections`) — typed generic container used internally by `Select`, `Set`, `Table`.

## Identifier & Value Quoting

`Expression` quotes identifiers with backticks and values depending on type:
- Strings → `'value'` (single-quoted, backslash-escaped)
- Numbers → unquoted
- `null` → `NULL`
- Arrays → `(v1, v2, v3)` (for `IN`)

**Known limitation:** uses string concatenation with quoting helpers — no prepared statement parameters yet. SQL injection risk if user input reaches value positions.

## Planned: Dialect Architecture

A dialect layer is planned to make SQL rendering portable across databases without bloating builders with per-vendor branches. This section is the **specification** the implementation must follow; nothing here is built yet.

### Goals

1. A **default dialect** that permits every feature — useful for tests and as the inheritance root.
2. **Database-specific dialects** extend the default and override individual component renderers to throw, silently ignore, or simulate a feature with a hack (e.g. flattening `schema.table` to `schema_table` for engines without schemas).
3. **Version-specific dialects** branch off the database dialect (e.g. `MariaDb105` extending `MariaDb` to enable `RETURNING`).
4. **All rendering logic currently in builder `__toString()` methods moves to the dialect.** Builders become thin data carriers: state + validation + factory methods. The dialect owns *how* a builder turns into SQL.
5. Each renderable component (Select, Insert, Column, ForeignKey, …) gets its **own renderer class** under the dialect's namespace. Dialects compose these renderers, so a subclass can swap one renderer without rewriting the whole dialect.
6. **`__toString()` defaults to the default dialect**; passing a specific dialect is opt-in via `toSql(Dialect $dialect)`.
7. The dialect is selected **at runtime** via `Dialect::fromDsn(string $dsn)`, mirroring how PDO DSNs identify the driver. This lets the same code target different databases per environment.

### Class layout

```
src/Dialect/
├── Dialect.php                         # abstract base
├── DefaultDialect.php                  # final-default, permissive baseline
├── UnsupportedFeatureException.php
├── Dsn/
│   └── DsnParser.php                   # parses DSN → Dialect instance
├── Renderer/                           # interfaces and default impls
│   ├── ComponentRenderer.php           # interface: render(Component): string
│   ├── Dml/
│   │   ├── SelectRenderer.php
│   │   ├── InsertRenderer.php
│   │   ├── UpdateRenderer.php
│   │   ├── DeleteRenderer.php
│   │   └── SetRenderer.php
│   ├── Ddl/
│   │   ├── TableRenderer.php
│   │   ├── ColumnRenderer.php
│   │   ├── ViewRenderer.php
│   │   ├── SequenceRenderer.php
│   │   ├── IndexRenderer.php
│   │   ├── PrimaryKeyRenderer.php
│   │   ├── UniqueKeyRenderer.php
│   │   ├── ForeignKeyRenderer.php
│   │   └── CheckRenderer.php
│   └── Common/
│       ├── BinaryExpressionRenderer.php
│       ├── UnaryExpressionRenderer.php
│       ├── ColumnExpressionRenderer.php
│       ├── ColumnReferenceRenderer.php
│       ├── ValueExpressionRenderer.php
│       ├── RawExpressionRenderer.php
│       ├── FunctionExpressionRenderer.php
│       ├── ExistsExpressionRenderer.php
│       ├── SubqueryExpressionRenderer.php
│       ├── SimpleIdentifierRenderer.php
│       ├── TableReferenceRenderer.php
│       ├── OrderRenderer.php
│       └── JoinRenderer.php
├── MariaDb/
│   ├── MariaDbDialect.php              # extends DefaultDialect
│   ├── MariaDb105Dialect.php           # extends MariaDbDialect, adds RETURNING
│   └── Renderer/                       # only the overrides live here
│       ├── InsertRenderer.php          # adds ON DUPLICATE KEY UPDATE handling
│       └── ...
└── Postgres/
    ├── PostgresDialect.php             # extends DefaultDialect
    ├── Postgres15Dialect.php           # version-specific, if needed
    └── Renderer/
        ├── DeleteRenderer.php          # adds USING handling
        ├── UpdateRenderer.php          # adds FROM handling
        ├── ColumnReferenceRenderer.php # double-quoted identifiers
        └── ValueExpressionRenderer.php # single-quote-only escaping
```

Rationale for one class per component: it keeps each renderer small enough to read in one screen, makes overriding per-dialect a one-file change, and lets a subclass swap one renderer (`MariaDb\Renderer\InsertRenderer`) without touching the rest.

### The `Dialect` contract

```php
abstract class Dialect {
    abstract public function quoteIdentifier(string $identifier): string;
    abstract public function quoteValue(mixed $value): string;

    // One method per renderable component. The default dialect implements
    // them by delegating to the per-component renderer instances it composes.
    abstract public function renderSelect(Select $component): string;
    abstract public function renderInsert(Insert $component): string;
    abstract public function renderUpdate(Update $component): string;
    abstract public function renderDelete(Delete $component): string;
    abstract public function renderSet(Set $component): string;
    abstract public function renderTable(Table $component): string;
    abstract public function renderColumn(Column $component): string;
    // ... one per component class

    /** Default singleton, lazily instantiated. */
    public static function default(): self {
        return self::$default ??= new DefaultDialect();
    }

    /** Runtime selection from a DSN. */
    public static function fromDsn(string $dsn): self {
        return Dsn\DsnParser::parse($dsn);
    }
}
```

`DefaultDialect` composes one renderer per component (`Renderer\Dml\SelectRenderer`, …) and forwards each `renderXxx()` call. A dialect subclass overrides only the renderers it needs to specialise.

### Builder side — what changes in components

- Each component (Select, Insert, …) drops its private `buildXxx()` helpers and its `__toString()` becomes:
  ```php
  public function __toString(): string {
      return Dialect::default()->renderSelect($this);
  }

  public function toSql(Dialect $dialect): string {
      return $dialect->renderSelect($this);
  }
  ```
- Builder state must be readable by the renderer. Two acceptable approaches: **public readonly** properties or **explicit getters**. Either is fine, but pick one and apply consistently. Validation that today lives in `__toString()` (e.g. "INSERT requires VALUES or SELECT") stays in the builder — it's pre-render validation, not rendering.

### Override patterns by example

- **Throw on unsupported feature** — `Postgres\Renderer\InsertRenderer::renderOnDuplicateKeyUpdate()` throws `UnsupportedFeatureException` because PostgreSQL uses `ON CONFLICT` instead.
- **Silently ignore** — a tiny dialect that doesn't support `IF NOT EXISTS` could just drop the clause from the rendered output (debatable; prefer throwing unless the omission is genuinely safe).
- **Hack/simulate** — a `Sqlite\Renderer\TableRenderer` could rewrite `schema.table` to `schema_table` because SQLite has no schemas, preserving the multi-tenant intent of the caller.

### DSN parsing

`Dialect::fromDsn()` accepts common DSN forms and returns the right dialect:

| DSN scheme               | Returned dialect          |
| ------------------------ | ------------------------- |
| `mariadb://...`          | `MariaDb\MariaDbDialect`  |
| `mysql://...`            | `MariaDb\MariaDbDialect`  |
| `postgres://...`         | `Postgres\PostgresDialect`|
| `pgsql://...`            | `Postgres\PostgresDialect`|
| Unknown / no scheme      | `DefaultDialect`          |

Version hints come from a `version` query-string parameter (e.g. `mariadb://host/db?version=10.5` → `MariaDb105Dialect`). The parser is forgiving: unrecognised schemes or versions fall back to the closest base dialect rather than throwing.

### Migration steps (when this is implemented)

1. **Introduce the contract**: `Dialect`, `DefaultDialect`, `UnsupportedFeatureException`, and the renderer interface. Add `toSql(Dialect)` and the `__toString` → `Dialect::default()->renderXxx($this)` redirection to every component, keeping the existing per-`__toString` logic temporarily inside `DefaultDialect`'s renderers (cut-and-paste).
2. **Decompose `DefaultDialect`** into per-component renderer classes. The `__toString` test suite stays green because output is unchanged.
3. **Add `MariaDb` and `Postgres` dialects** with the overrides needed for their feature matrices (quoting, USING/FROM, ON DUPLICATE KEY UPDATE vs ON CONFLICT, RETURNING gates, ORDER BY/LIMIT on writes, …). Add dialect-specific test suites under `tests/Unit/Dialect/`.
4. **Add version variants** as the matrix demands (`MariaDb105` enabling `RETURNING`, etc.).
5. **Implement `Dialect::fromDsn()`** and document the supported schemes.

Once steps 1–2 land, every later dialect is additive: a new database means a new subclass with its renderer overrides; no churn in builders or existing tests.

## Testing

PHPUnit 13 is configured via `phpunit.xml` with two suites: `Unit` and `Integration`. The strict flags `failOnWarning` and `failOnRisky` are enabled — risky/incomplete tests fail the run.

Run:
- `composer test` — runs all suites
- `vendor/bin/phpunit --testsuite Unit` — only the unit suite
- `vendor/bin/phpunit tests/Unit/SomeTest.php` — single file

Test classes mirror the source namespace (e.g. `Rak200\SqlBuilder\Common\Expression` → `Rak200\SqlBuilder\Tests\Unit\Common\ExpressionTest`). Test methods follow PSR-12 camelCase (e.g. `testRendersQualifiedIdentifier`), **not** snake_case. Since the library only produces SQL strings, tests assert on the exact string output of expressions/builders — no database connection is required.

## Versioning

Follows [Semantic Versioning](https://semver.org). Current version: **0.1.1** — unstable while the API stabilises.

When releasing a new version:
1. Update `"version"` in `composer.json`
2. Update `CHANGELOG.md`: add a new `## [x.y.z] - YYYY-MM-DD` section with `### Added / Changed / Fixed / Removed` entries and a comparison link at the bottom
3. Update the version reference in `README.md`
4. Commit and push
5. Create and push a git tag matching the version: `git tag x.y.z && git push origin x.y.z`

Consumers using `"type": "vcs"` in their `composer.json` resolve versions from git tags.

