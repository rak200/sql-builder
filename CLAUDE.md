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

### Class layout (as built)

```
src/Dialect/
├── Dialect.php                         # abstract base
├── DefaultDialect.php                  # permissive baseline; composes default renderers
├── UnsupportedFeatureException.php
├── Dsn/
│   └── DsnParser.php                   # parses DSN → Dialect instance
├── Renderer/
│   ├── ComponentRenderer.php           # marker interface
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
│   ├── MariaDbDialect.php              # rejects Postgres-only FROM/USING and RETURNING on writes
│   ├── MariaDb105Dialect.php           # re-enables RETURNING (single-table only for DELETE)
│   └── Renderer/
│       ├── InsertRenderer.php          # throws on RETURNING
│       ├── UpdateRenderer.php          # throws on FROM and on RETURNING
│       ├── DeleteRenderer.php          # throws on USING and on RETURNING
│       ├── UpdateRenderer105.php       # inherits FROM rejection, allows RETURNING
│       └── DeleteRenderer105.php       # inherits USING rejection, allows RETURNING
└── Postgres/
    ├── PostgresDialect.php             # double-quoted identifiers, standard-conforming strings
    ├── Postgres15Dialect.php           # placeholder for MERGE / NULLS NOT DISTINCT
    └── Renderer/
        └── InsertRenderer.php          # throws on ON DUPLICATE KEY UPDATE
```

Postgres-only multi-table forms (`UPDATE ... FROM`, `DELETE ... USING`) and `RETURNING` are inherited from the permissive default — `PostgresDialect` only needs the quoting / string-escape overrides on the dialect itself plus the one Insert renderer override.

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

## Planned: Safety & Quality

One item remains on the `README.md` "Not yet implemented" list. This section is the **specification** the implementation must follow; nothing here is built yet.

### Consistent identifier quoting

Smaller, contained refactor of the DDL renderer layer to route every identifier through `Dialect::quoteIdentifier()`.

#### Current inconsistency

`Dialect::quoteIdentifier()` (default) produces backticks. The DDL renderers, however, wrap the result in literal double quotes — `CREATE TABLE "`users`" (...)` — and several places skip the dialect entirely and emit `"%s"` with the raw identifier name. Inventory of offenders:

```
src/Dialect/Renderer/Ddl/TableRenderer.php       CREATE TABLE "...", ALTER TABLE "...", RENAME COLUMN "...", DROP COLUMN "...", DROP CONSTRAINT "..."
src/Dialect/Renderer/Ddl/ViewRenderer.php        CREATE VIEW "..." and the explicit column list
src/Dialect/Renderer/Ddl/IndexRenderer.php       "name", "table", and each column inside the parentheses
src/Dialect/Renderer/Ddl/SequenceRenderer.php    CREATE SEQUENCE "...", ALTER SEQUENCE "...", DROP SEQUENCE "..."
src/Dialect/Renderer/Ddl/PrimaryKeyRenderer.php  CONSTRAINT "...", and the column list
src/Dialect/Renderer/Ddl/UniqueKeyRenderer.php   same shape as PrimaryKey
src/Dialect/Renderer/Ddl/ForeignKeyRenderer.php  CONSTRAINT "...", REFERENCES "..." and both column lists
src/Dialect/Renderer/Ddl/CheckRenderer.php       CONSTRAINT "..."
```

#### Target

Every identifier — table, view, index, sequence, schema, constraint, column inside a constraint or index column list — is emitted via `$this->dialect->quoteIdentifier(...)`. No literal `"..."` wraps remain in any renderer. Output becomes uniform: backticks on the default dialect, double quotes on Postgres, etc.

#### Migration steps

1. Sweep the eight DDL renderer files; replace every `sprintf('"%s"', ...)` and every `"%s"` template with a call through `$this->dialect->quoteIdentifier(...)`.
2. Run the suite. Tests under `tests/Unit/Ddl/{TableTest, ViewTest, IndexTest, SequenceTest, PrimaryKeyTest, UniqueKeyTest, ForeignKeyTest, CheckTest}.php` plus several `tests/Unit/Dialect/*Test.php` files assert the current quirky output literally — expect ~40-60 assertions to need updates.
3. Update each failing assertion to the cleaned-up string. **Do not** revert the renderer change to keep an old test green — the test is the artefact, the new output is the goal.
4. Cross-check `SchemaDialectTest`, `DropTruncateDialectTest`, `SelectExtensionsDialectTest` and the propagation tests for cosmetic regressions.
5. Update the README's DDL example snippets so the comment-after-`echo` matches the new output (the artefacts appear in several places).
6. CHANGELOG: list this as a **BREAKING (0.x) output change** — emitted SQL changes for every DDL statement even though it remains semantically equivalent and parses identically.

## Planned: UUID column support

Native first-class UUID handling — column type in DDL, value wrapping in DML, transparent input/output transformation on engines that lack a native UUID type.

### Background

- **PostgreSQL** has a native `uuid` type. UUID literals in SQL are written as `'aaaa…'::uuid` (or as a plain quoted string in a `uuid`-typed context, with implicit cast). No round-trip transformation is needed.
- **MariaDB** has no native UUID type (the 10.7 `UUID` alias is just `CHAR(36)`). The standard simulation stores UUIDs as `BINARY(16)`, with the built-in `UUID_TO_BIN(text [, swap_flag])` / `BIN_TO_UUID(bin [, swap_flag])` functions converting between text and binary at the value boundaries.

The plan therefore covers two distinct concerns: the DDL **type declaration** (`UUID` vs `BINARY(16)`) and the DML **value/column wrapping** (`UUID_TO_BIN(...)` on the way in, `BIN_TO_UUID(...)` on the way out).

### Goals

1. **DDL** — `Column::create('id', DataType::Uuid)` renders portably:
   - Default dialect → `UUID` (passes through to whatever the engine supports; documented as "permissive baseline").
   - Postgres → `UUID` (native).
   - MariaDB → `BINARY(16)`.
2. **DML input** — wrap values addressed to UUID columns so they reach the engine in the right shape:
   - Default → emit the UUID string verbatim (`'aaaa-…'`).
   - Postgres → emit `'aaaa-…'::uuid` (or `$N::uuid` in bind mode) **only when the inner is a `ValueExpression` or `ParameterExpression`**. Column references pass through without the cast — `WHERE "id" = "other_id"` stays clean. The cast guarantees correctness in standalone contexts (`SELECT $1 AS id`) where PG can't infer the type from a target column.
   - MariaDB → wrap as `UUID_TO_BIN('aaaa-…')` (omitting the second `swap_flag` argument; matches MariaDB's default and the byte order produced by `UUID()`). In bind mode the placeholder still goes through the binder (`UUID_TO_BIN(?)` / `UUID_TO_BIN($N)`); the binder records the **text** UUID and the wrap stays on the wire.
3. **DML output** — when projecting a UUID column in `SELECT`, render the column so the engine yields a text UUID:
   - Default / Postgres → no wrap; the column reads back as text natively.
   - MariaDB → `BIN_TO_UUID(\`id\`)` (preserving any alias).
4. **Comparison / JOIN / WHERE** — `Expression::binary('id', Eq, Expression::uuid('aaaa-…'))` produces `"id" = 'aaaa-…'` on Postgres and `` `id` = UUID_TO_BIN('aaaa-…') `` on MariaDB. The same shape on both sides of a JOIN when both columns are UUID.
5. **Backward compatible** — every existing DDL/DML test keeps its output; UUID support is purely additive (new enum case + new expression types + opt-in dialect renderers).

### New abstractions

#### DDL

- Add `DataType::Uuid` to `src/Ddl/Enum/DataType.php`. Default `ColumnRenderer` emits the enum's string value (`UUID`), unchanged path.
- `MariaDb\Renderer\ColumnRenderer` — new file. Overrides only the type-emission step: `DataType::Uuid` → `BINARY(16)`. All other types delegate to the parent. Wired in via a new protected `columnRenderer()` factory on `MariaDbDialect`.
- Postgres needs no override (the default `UUID` already matches).

#### DML

Two new expression types under `src/Common/`:

```
src/Common/
├── UuidInputExpression.php    # wraps a string|ExpressionInterface destined for a UUID column
└── UuidOutputExpression.php   # wraps a column reference whose binary value should be decoded to text
```

Both extend `Expression` (so they inherit `as()` for aliases) and carry a single inner `ExpressionInterface`:

```php
final class UuidInputExpression extends Expression {
    public function __construct(public readonly ExpressionInterface $inner) {}
}

final class UuidOutputExpression extends Expression {
    public function __construct(public readonly ExpressionInterface $inner) {}
}
```

Factories on `Expression`:

```php
public static function uuid(string|ExpressionInterface $value): UuidInputExpression {
    return new UuidInputExpression(self::normalize($value));   // strings become ValueExpression
}

public static function uuidColumn(string $name, ?string $alias = null): UuidOutputExpression {
    $col = new ColumnExpression($name, $alias);
    return new UuidOutputExpression($col);
}
```

The split (input vs output) is intentional: the directions are different SQL functions and live in different positions in the statement, so collapsing them onto one factory would surprise callers. The naming mirrors the existing `Expression::value()` / `Expression::column()` distinction.

#### Renderers

Two new default renderers (no-op wraps — they just delegate to the inner expression) under `src/Dialect/Renderer/Common/`:

```
UuidInputExpressionRenderer.php    # default: render inner as-is
UuidOutputExpressionRenderer.php   # default: render inner as-is
```

The default is intentionally a pass-through because the **default dialect** doesn't claim a backend — Postgres-native or MariaDB-simulated behaviour is opt-in per vendor.

MariaDB overrides under `src/Dialect/MariaDb/Renderer/`:

```
UuidInputExpressionRenderer.php    # emits UUID_TO_BIN(<inner>) — no swap_flag
UuidOutputExpressionRenderer.php   # emits BIN_TO_UUID(<inner>) [AS <alias>] — alias hoisted from the wrapped ColumnExpression, no swap_flag
```

Postgres override under `src/Dialect/Postgres/Renderer/`:

```
UuidInputExpressionRenderer.php    # appends ::uuid iff $component->inner is ValueExpression or ParameterExpression
```

The Postgres override is short — one `if ($inner instanceof ValueExpression || $inner instanceof ParameterExpression)` branch around the rendered inner. Column-reference inputs and any other expression types fall through to the parent (no cast).

#### Dialect contract additions

```php
abstract class Dialect {
    abstract public function renderUuidInputExpression(UuidInputExpression $component): string;
    abstract public function renderUuidOutputExpression(UuidOutputExpression $component): string;
}
```

Add the two cases to `Dialect::renderExpression()`'s polymorphic `match`. `DefaultDialect` adds renderer slots, accessor factories, and the two `render*` implementations (one-liner each, like every existing one). `__clone()` resets the two new slots alongside the others (same pattern as `ParameterExpressionRenderer`).

### Migration steps

1. **DDL type**
   - Add `DataType::Uuid = 'UUID'` to the enum.
   - Create `src/Dialect/MariaDb/Renderer/ColumnRenderer.php` extending the default; override only the type-emission helper to map `DataType::Uuid` → `BINARY(16)`.
   - Wire `MariaDbDialect::columnRenderer()` accessor.
2. **DML expressions**
   - Add `UuidInputExpression`, `UuidOutputExpression` under `src/Common/`.
   - Add `Expression::uuid()` and `Expression::uuidColumn()` factories.
3. **Default renderers**
   - Add `UuidInputExpressionRenderer` and `UuidOutputExpressionRenderer` under `src/Dialect/Renderer/Common/` (pass-through default behaviour).
   - Add the abstract `renderUuid*Expression()` methods on `Dialect`, the dispatch cases in `renderExpression()`, and the slots + accessors on `DefaultDialect`. Extend the `__clone()` reset list.
4. **MariaDB overrides**
   - Add `UuidInputExpressionRenderer` (emits `UUID_TO_BIN(<inner>)`, no swap_flag) and `UuidOutputExpressionRenderer` (emits `BIN_TO_UUID(<inner>) [AS <alias>]`, no swap_flag) under `src/Dialect/MariaDb/Renderer/`.
   - Override the protected `uuidInputExpressionRenderer()` and `uuidOutputExpressionRenderer()` factories on `MariaDbDialect`.
5. **Postgres override**
   - Add `Postgres\Renderer\UuidInputExpressionRenderer` that appends `::uuid` when the inner is `ValueExpression` or `ParameterExpression`; otherwise delegate to parent.
   - Override the protected `uuidInputExpressionRenderer()` factory on `PostgresDialect`.
6. **Tests** under `tests/Unit/`:
   - `tests/Unit/Ddl/UuidColumnTest.php` — `Column::create('id', DataType::Uuid)` on default/Postgres → `UUID`, on MariaDB → `BINARY(16)`, with NOT NULL / DEFAULT modifiers preserved on both.
   - `tests/Unit/Common/UuidExpressionTest.php` — factories produce the right inner expressions; default renders are pass-through.
   - `tests/Unit/Dialect/UuidDialectTest.php`:
     - **DDL**: Default and Postgres emit `UUID`; MariaDB emits `BINARY(16)`.
     - **INSERT/UPDATE value wrapping**: literal `Expression::uuid('aaa…')` in `Insert::values()` / `Update::set()` becomes bare on default, `'aaa…'::uuid` on Postgres, `UUID_TO_BIN('aaa…')` on MariaDB.
     - **Bind mode**: `prepare()` shows placeholder shape per dialect — `?` (bare) on default, `$1::uuid` on Postgres, `UUID_TO_BIN(?)` on MariaDB. `parameters` carries the **text** UUID on all three.
     - **WHERE comparison**: `WHERE id = Expression::uuid(...)` wraps the right operand; left column reference stays unwrapped.
     - **JOIN ON across two UUID columns**: both column refs unwrapped on Postgres (no cast needed); on MariaDB the `ON` clause stays bare-column too — the simulation only wraps **values**, not column-to-column comparisons (binary columns already compare as binary).
     - **SELECT projection**: `Expression::uuidColumn('id', 'id')` renders as `"id" AS "id"` on Postgres, `` `id` AS `id` `` on default, `` BIN_TO_UUID(`id`) AS `id` `` on MariaDB.
     - **Nested with `Expression::param()`**: `Expression::uuid(Expression::param('uid'))` recurses through the renderer dispatch — `UUID_TO_BIN(:uid)` on MariaDB, `:uid::uuid` on Postgres, `:uid` on default.
     - **Postgres no-cast on column ref**: `Expression::uuid(Expression::ref('other_id'))` stays as `"other_id"` (no `::uuid` because the inner is a column reference, not a value/param).
7. **README** — extend the Status & Roadmap with a short "UUID columns" bullet under "What works today" once landed; document the `Expression::uuid()` / `Expression::uuidColumn()` factories and the MariaDB simulation in the same section that already covers schema simulation.
8. **CHANGELOG** — `### Added: First-class UUID columns. New DataType::Uuid; Expression::uuid() / Expression::uuidColumn() wrappers; MariaDB simulates as BINARY(16) with transparent UUID_TO_BIN / BIN_TO_UUID conversion at value and projection boundaries; PostgreSQL emits ::uuid casts only where ambiguous (literals and parameters), not on column references.`

### Resolved decisions

- **MariaDB `swap_flag`** — **omitted**. `UUID_TO_BIN(x)` / `BIN_TO_UUID(x)` without the optional second argument. Matches MariaDB's default and the byte layout produced by `UUID()`. Can be surfaced as a `MariaDbDialect` knob in a later release if a caller needs the v1 index-locality reordering.
- **Postgres `::uuid` cast** — **conditional on inner type**. Append `::uuid` iff `$component->inner` is a `ValueExpression` or a `ParameterExpression`. Column references and other expression types fall through without a cast. Keeps `WHERE id = $1::uuid` correct while leaving `WHERE id = other_id` clean.
- **Nested UUID expressions** — supported by construction. `Expression::uuid(Expression::param('uid'))` recurses through `Dialect::renderExpression()` and produces the right shape per dialect; covered by an explicit test in `UuidDialectTest`.

## Testing

PHPUnit 13 is configured via `phpunit.xml` with two suites: `Unit` and `Integration`. The strict flags `failOnWarning` and `failOnRisky` are enabled — risky/incomplete tests fail the run.

Run:
- `composer test` — runs all suites
- `vendor/bin/phpunit --testsuite Unit` — only the unit suite
- `vendor/bin/phpunit tests/Unit/SomeTest.php` — single file

Test classes mirror the source namespace (e.g. `Rak200\SqlBuilder\Common\Expression` → `Rak200\SqlBuilder\Tests\Unit\Common\ExpressionTest`). Test methods follow PSR-12 camelCase (e.g. `testRendersQualifiedIdentifier`), **not** snake_case. Since the library only produces SQL strings, tests assert on the exact string output of expressions/builders — no database connection is required.

## Versioning

Follows [Semantic Versioning](https://semver.org). Current version: **0.5.0** — unstable while the API stabilises.

When releasing a new version:
1. Update `"version"` in `composer.json`
2. Update `CHANGELOG.md`: add a new `## [x.y.z] - YYYY-MM-DD` section with `### Added / Changed / Fixed / Removed` entries and a comparison link at the bottom
3. Update the version reference in `README.md`
4. Commit and push
5. Create and push a git tag matching the version: `git tag x.y.z && git push origin x.y.z`

Consumers using `"type": "vcs"` in their `composer.json` resolve versions from git tags.

