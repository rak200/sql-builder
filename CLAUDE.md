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
│   ├── Dml/              # SELECT, Set (UNION/EXCEPT/INTERSECT); Insert/Update/Delete are stubs
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

## Testing

PHPUnit 13 is configured via `phpunit.xml` with two suites: `Unit` and `Integration`. The strict flags `failOnWarning` and `failOnRisky` are enabled — risky/incomplete tests fail the run.

Run:
- `composer test` — runs all suites
- `vendor/bin/phpunit --testsuite Unit` — only the unit suite
- `vendor/bin/phpunit tests/Unit/SomeTest.php` — single file

Test classes mirror the source namespace (e.g. `Rak200\SqlBuilder\Common\Expression` → `Rak200\SqlBuilder\Tests\Unit\Common\ExpressionTest`). Since the library only produces SQL strings, tests assert on the exact string output of expressions/builders — no database connection is required.

## Versioning

Follows [Semantic Versioning](https://semver.org). Current version: **0.0.3** — unstable while the API stabilises.

When releasing a new version:
1. Update `"version"` in `composer.json`
2. Update `CHANGELOG.md`: add a new `## [x.y.z] - YYYY-MM-DD` section with `### Added / Changed / Fixed / Removed` entries and a comparison link at the bottom
3. Update the version reference in `README.md`
4. Commit and push
5. Create and push a git tag matching the version: `git tag x.y.z && git push origin x.y.z`

Consumers using `"type": "vcs"` in their `composer.json` resolve versions from git tags.

