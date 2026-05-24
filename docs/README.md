# sql-builder — Documentation

Fluent SQL query and schema builder for PHP 8.4+. Generates SQL strings via a type-safe, chainable API. **No ORM** — the library produces SQL only; you execute it yourself with PDO or any other layer.

## Table of contents

1. [Getting started](getting-started.md) — installation, first query, mental model
2. [Expressions](expressions.md) — the `Expr` factory: columns, values, operators, functions, aggregates, CASE WHEN, UUID, parameters, grouping extensions
3. [DML — SELECT](select.md) — `Select` builder: projections, FROM, JOIN family (incl. NATURAL, USING, LATERAL), WHERE / AND / OR, GROUP BY with ROLLUP/CUBE/GROUPING SETS, HAVING, ORDER BY, LIMIT/OFFSET, CTEs, window functions
4. [DML — INSERT](insert.md) — `Insert` builder: VALUES, INSERT … SELECT, portable `onConflict()`, MariaDB `onDuplicateKeyUpdate()`, RETURNING
5. [DML — UPDATE](update.md) — `Update` builder: SET, multi-table FROM (Postgres), WHERE, ORDER BY / LIMIT (MySQL), RETURNING
6. [DML — DELETE](delete.md) — `Delete` builder: multi-table USING (Postgres), WHERE, ORDER BY / LIMIT (MySQL), RETURNING
7. [DML — MERGE](merge.md) — SQL:2003 `Merge` builder with WHEN MATCHED / NOT MATCHED branches
8. [DML — Set operations](set-operations.md) — `Set` builder: UNION / UNION ALL / EXCEPT / INTERSECT
9. [DDL](ddl.md) — `Table`, `Column`, `View`, `Sequence`, `Index`, `Schema`, and constraints (`PrimaryKey`, `UniqueKey`, `ForeignKey`, `Check`); CREATE / ALTER / DROP / TRUNCATE
10. [Dialects](dialects.md) — `Dialect` abstraction, `DefaultDialect`, MariaDB / PostgreSQL variants, `Dialect::fromDsn()`, vendor-specific feature gates, writing your own dialect
11. [Prepared statements](prepared-statements.md) — `prepare(Dialect)`, `Expr::param()`, named vs positional placeholders, binder semantics per dialect

[↑ Back to top](#)

## Quick example

```php
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Common\Enum\Sort\Direction;
use Rak200\SqlBuilder\Dml\Select;

$query = Select::create()
    ->select('id', 'name', 'email')
    ->from('users', 'u')
    ->where(Expr::binary('u.active', Binary::Eq, 1))
    ->orderBy('u.name', Direction::ASC)
    ->limit(20);

echo $query;
// SELECT `id`, `name`, `email` FROM `users` AS `u` WHERE (`u`.`active` = 1) ORDER BY `u`.`name` ASC LIMIT 20
```

[↑ Back to top](#)

## Conventions used in these guides

- Code examples use the default dialect output (backticks) unless a vendor-specific section says otherwise.
- `Expr::binary($column, Binary::Eq, $value)` wraps its result in parentheses (`(a = b)`) — this is intentional to keep nested predicates unambiguous and is reflected in the example output.
- Where a feature is dialect-gated, the gate is noted inline (e.g. "Postgres 15+", "MariaDB ≥ 10.5 only").
- Imports are abbreviated to `use Rak200\SqlBuilder\...;` once per file; subsequent examples elide them.

[↑ Back to top](#)

## See also

- [`../README.md`](../README.md) — project overview, installation, status & roadmap
- [`../CHANGELOG.md`](../CHANGELOG.md) — version history with detailed change notes
- [`../CLAUDE.md`](../CLAUDE.md) — internal architecture notes (dialect layer, renderer composition, repo conventions)
