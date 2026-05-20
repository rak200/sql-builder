# Changelog

All notable changes to this project will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [0.3.0] - 2026-05-19

### Added
- `Schema` DDL builder under `src/Ddl/` for `CREATE SCHEMA`, `DROP SCHEMA` and `ALTER SCHEMA ... RENAME TO`, with `IF [NOT] EXISTS`, `AUTHORIZATION`, and `CASCADE`/`RESTRICT` modifiers. Wired into the `Dialect` contract via a new abstract `renderSchema()` and `Renderer/Ddl/SchemaRenderer.php`.
- Two new override points on `Dialect`: `resolveTableName(string)` and `resolveColumnReference(string)`. Default: identity. Every table-aware default renderer (TableRenderer, TableReferenceRenderer, InsertRenderer, IndexRenderer, ForeignKeyRenderer, ViewRenderer, SequenceRenderer, ColumnReferenceRenderer, ColumnExpressionRenderer) now runs identifiers through these hooks before quoting.
- MariaDB schema simulation: `MariaDbDialect::resolveTableName()` flattens `schema.table` to `schema_table`; `resolveColumnReference()` flattens the schema prefix in three-part column references (`schema.table.column` → `schema_table.column`). `MariaDb\Renderer\SchemaRenderer` throws `UnsupportedFeatureException` on CREATE/DROP/ALTER SCHEMA — MariaDB has no schema namespace independent of the database, so schema-level DDL is intentionally refused.
- 35 new tests under `tests/Unit/Ddl/SchemaTest.php` and `tests/Unit/Dialect/SchemaDialectTest.php` covering the builder, the default and Postgres rendering, MariaDB's refusal of schema DDL, and the MariaDB table-prefix simulation across SELECT/INSERT/UPDATE/DELETE/JOIN/Index/ForeignKey/View/Sequence.

## [0.2.0] - 2026-05-19

### Added
- Dialect architecture under `src/Dialect/` per the `CLAUDE.md` spec: abstract `Dialect` base, permissive `DefaultDialect`, one renderer class per renderable component (13 Common, 5 DML, 9 DDL under `Renderer/`), `UnsupportedFeatureException`, and `Dialect::default()` singleton.
- Vendor dialects: `MariaDb\MariaDbDialect` (rejects PostgreSQL-only `UPDATE ... FROM`, `DELETE ... USING`, and `RETURNING`), `MariaDb\MariaDb105Dialect` (re-enables `RETURNING`), `Postgres\PostgresDialect` (double-quoted identifiers, standard-conforming string escaping, rejects `ON DUPLICATE KEY UPDATE`), `Postgres\Postgres15Dialect` (placeholder).
- `Dialect::fromDsn(string $dsn)` and `Dsn\DsnParser` mapping `mariadb`/`mysql`/`postgres`/`pgsql`/`postgresql` schemes to dialects, with a `?version=` query-string hint that selects `MariaDb105Dialect` (≥10.5) or `Postgres15Dialect` (≥15). Unknown schemes fall back to `DefaultDialect`.
- `toSql(Dialect $dialect): string` on every renderable component (Common expressions, DML builders, DDL builders) for opt-in per-call dialect rendering.
- 35 new unit tests under `tests/Unit/Dialect/` covering default rendering parity, Postgres/MariaDB quoting and feature gates, DSN parsing, and dialect propagation through nested subqueries / JOIN / EXISTS / set operations / INSERT...SELECT / DDL.

### Changed
- Every builder is now a thin data carrier: `__toString()` delegates to `Dialect::default()->renderXxx($this)`; the private `buildXxx()` helpers are removed and the rendering logic lives in the per-component renderer classes.
- Builder state is exposed to renderers via PHP 8.4 asymmetric visibility (`public private(set)`) for fluent-mutable properties, and `public readonly` for value-object properties. Fluent setter API is unchanged.
- `Expression::quoteIdentifier()` and `Expression::quoteValue()` now delegate to `Dialect::default()`; behaviour is unchanged for callers.
- `Join::validate()` is now public so the `JoinRenderer` can invoke it before producing SQL.

### Removed
- Private `buildXxx()` helpers on `Select`, `Insert`, `Update`, `Delete`, `Set`, `Table`, `View`, `Sequence` (logic moved into the corresponding renderer classes).

## [0.1.1] - 2026-05-19

### Added
- Specification for the planned Dialect architecture in `CLAUDE.md`: a permissive `DefaultDialect` base, per-database subclasses (MariaDB, PostgreSQL, …) that override individual component renderers, optional per-version variants, one renderer class per renderable component, and runtime selection via `Dialect::fromDsn()`. No code yet — this is the contract for the upcoming implementation.

### Changed
- Renamed all PHPUnit test methods from snake_case (`test_some_thing`) to PSR-12 camelCase (`testSomeThing`). No behaviour change.

## [0.1.0] - 2026-05-18

### Added
- `Insert` builder: `INSERT INTO ... VALUES (...)` (single/multi-row), `INSERT INTO ... SELECT ...`, `ON DUPLICATE KEY UPDATE` (MySQL/MariaDB upsert) via `onDuplicateKeyUpdate()`, and `RETURNING` (PostgreSQL/MariaDB/SQLite) via `returning()`. Scalar values are auto-quoted; `ExpressionInterface` values (e.g. `Expression::raw('NOW()')`, `VALUES(col)`) pass through unchanged.
- `Update` builder: `UPDATE <table> [AS alias] SET ... [WHERE ...]` with chainable `where()` / `andWhere()` / `orWhere()` and per-column `set()` (scalars quoted, expressions pass through). Plus dialect extensions: multi-table `FROM` (PostgreSQL), `ORDER BY` and `LIMIT` (MySQL), and `RETURNING`.
- `Delete` builder is now functional: `DELETE FROM <table> [AS alias] [WHERE ...]` with chainable `where()` / `andWhere()` / `orWhere()`. Plus dialect extensions: multi-table `USING` (PostgreSQL), `ORDER BY` and `LIMIT` (MySQL), and `RETURNING`.
- 53 new unit tests under `tests/Unit/Dml/` covering Insert/Update/Delete happy paths, validation errors, multi-row INSERT, INSERT ... SELECT, ON DUPLICATE KEY UPDATE, RETURNING, multi-table FROM/USING, ORDER BY / LIMIT, AND/OR WHERE composition, and full-pipeline clause order.

### Changed
- Normalised `@author` tag in every source file under `src/` to `rak200 <rak.ricardo@windowslive.com>`.

## [0.0.3] - 2026-05-18

### Added
- PHPUnit 13 unit test suite under `tests/Unit/` covering `Common`, `Dml`, `Ddl` and `Utils` (194 tests, 244 assertions). Run with `composer test`.
- `Status & Roadmap` section in `README.md` documenting what works today and what is still pending — `Insert` / `Update` / `Delete` stubs, `DROP` / `TRUNCATE`, CTEs (`WITH`), window functions, `CASE WHEN`, parameter binding, unified identifier quoting.
- `CHANGELOG.md`, with retroactive entries for 0.0.1 and 0.0.2.

### Changed
- Migrated internal containers in `Select`, `Set` and `Table` from the deprecated `Rak200\Collections\Collection` to `Rak200\Collections\Vector`.
- Rewrote `README.md` examples to match the actual fluent API: correct enum cases (`BinaryOperator::Equal`, `SortDirection::ASC`, `DataType::BigInt`, …), join helpers (`Select::join()` / `leftJoin()` / `joinUsing()` / `naturalJoin()`), `Set::create($a)->union($b)`, `Table::create()->column()` / `->constraint()`, `View::query()`, and added a `Sequence` example.

### Fixed
- `Join::__toString()` no longer emits an empty string for joins without `ON` or `USING` (e.g. `NATURAL` joins, `CROSS JOIN` with no condition). Previously routed through `StringUtils::join()` with an empty list, which discarded the prefix.

## [0.0.2] - 2026-05-16

### Changed
- Extracted the typed `Collection` container into the standalone [`rak200/collections`](https://github.com/rak200/collections) package; this project now depends on it instead of vendoring the class.

## [0.0.1] - 2026-05-16

### Added
- Initial release of the fluent SQL query and schema builder for PHP 8.4+.
- **DML:** `Select` (DISTINCT, JOINs incl. NATURAL/USING, WHERE/GROUP BY/HAVING, ORDER BY with NULL placement, LIMIT/OFFSET, subqueries) and `Set` (`UNION` / `UNION ALL` / `EXCEPT` / `INTERSECT`).
- **DDL:** `Table` (CREATE and ALTER), `Column`, `View`, `Sequence`, `Index`, and constraints (`PrimaryKey`, `UniqueKey`, `ForeignKey`, `Check`).
- **Expressions:** binary/unary operators, AND/OR groups, EXISTS, subqueries, function calls, aggregates (`COUNT`, `SUM`, `AVG`, `MIN`, `MAX`), raw SQL escape hatch, identifier and value quoting via `Expression::quoteIdentifier()` / `Expression::quoteValue()`.

[Unreleased]: https://github.com/rak200/sql-builder/compare/0.3.0...HEAD
[0.3.0]: https://github.com/rak200/sql-builder/compare/0.2.0...0.3.0
[0.2.0]: https://github.com/rak200/sql-builder/compare/0.1.1...0.2.0
[0.1.1]: https://github.com/rak200/sql-builder/compare/0.1.0...0.1.1
[0.1.0]: https://github.com/rak200/sql-builder/compare/0.0.3...0.1.0
[0.0.3]: https://github.com/rak200/sql-builder/compare/0.0.2...0.0.3
[0.0.2]: https://github.com/rak200/sql-builder/compare/0.0.1...0.0.2
[0.0.1]: https://github.com/rak200/sql-builder/releases/tag/0.0.1
