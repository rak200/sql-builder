# sql-builder

Fluent SQL query and schema builder for PHP 8.4+. No ORM — generates SQL strings via a type-safe, chainable API.

## Installation

```bash
composer require rak200/sql-builder
```

## Overview

| Layer | Classes | Purpose |
|-------|---------|---------|
| **DML** | `Select`, `Set` | Query building (SELECT, set operations) |
| **DDL** | `Table`, `Column`, `View`, `Sequence`, `Index`, constraints | Schema definition |
| **Common** | `Expression`, expressions, `Join`, `Order` | Shared building blocks |
| **Enums** | `BinaryOperator`, `JoinType`, `SortDirection`, … | Type-safe SQL keywords |

## DML — Queries

### SELECT

```php
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Dml\Select;

$query = Select::create()
    ->select('id', 'name', 'email')
    ->from('users', 'u')
    ->where(Expression::binary('u.active', BinaryOperator::Eq, 1))
    ->orderBy('u.name', SortDirection::ASC)
    ->limit(20)
    ->offset(0);

echo $query; // SELECT `id`, `name`, `email` FROM `users` AS `u` WHERE ...
```

### JOIN

`Select` exposes a dedicated method per join type — `join()` (INNER), `leftJoin()`, `rightJoin()`, `fullJoin()`, `crossJoin()`, plus `naturalJoin()` / `naturalLeftJoin()` / … and `joinUsing()` / `leftJoinUsing()` / … variants for `USING (...)` joins.

```php
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Dml\Select;

$query = Select::create()
    ->select('u.name', 'r.role')
    ->from('users', 'u')
    ->join(
        'roles',
        'r',
        Expression::binary('u.role_id', BinaryOperator::Eq, Expression::ref('r.id'))
    );
```

### Common Table Expressions (`WITH`)

```php
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Set;

$totals = Select::create()
    ->select('user_id', Expression::count('*'))
    ->from('orders')
    ->groupBy('user_id');

$query = Select::create()
    ->with('order_totals', $totals)
    ->select('user_id')
    ->from('order_totals');

// Recursive
$base = Select::create()->select(Expression::value(1));
$step = Select::create()
    ->select(Expression::raw('n + 1'))
    ->from('numbers')
    ->where(Expression::binary('n', BinaryOperator::Lt, 10));

$recursive = Select::create()
    ->withRecursive('numbers', Set::create($base)->union($step, all: true), ['n'])
    ->select('n')
    ->from('numbers');
```

### Window functions (`OVER`)

```php
use Rak200\SqlBuilder\Common\Window;

$running = Expression::over(
    Expression::sum('amount'),
    Window::create()
        ->partitionBy('user_id')
        ->orderBy('paid_at')
        ->rows('BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW')
)->as('running_total');

$query = Select::create()->select('user_id', $running)->from('payments');
```

`Window` exposes `partitionBy()`, `orderBy()`, plus `rows()` / `range()` / `groups()` shorthands or a raw `frame()` setter for any standards-compliant frame clause.

### `CASE WHEN`

```php
// Searched CASE
Expression::case()
    ->when(Expression::binary('amount', BinaryOperator::Gt, 100), Expression::value('high'))
    ->when(Expression::binary('amount', BinaryOperator::Gt, 10),  Expression::value('medium'))
    ->else(Expression::value('low'))
    ->as('bucket');

// Simple CASE
Expression::case('status')
    ->when('active', 1)
    ->when('inactive', 0)
    ->else(-1);
```

In simple form, scalar `when()` values are auto-wrapped as literals. In searched form, the condition must be an `ExpressionInterface` (typically a binary expression).

### Set operations (UNION, EXCEPT, INTERSECT)

```php
use Rak200\SqlBuilder\Dml\Set;

$union = Set::create($selectA)
    ->union($selectB)              // UNION
    ->union($selectC, all: true)   // UNION ALL
    ->except($selectD)             // EXCEPT
    ->intersect($selectE)          // INTERSECT
    ->orderBy('name')
    ->limit(50);
```

### INSERT

```php
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Dml\Insert;

// Multi-row literal values
$insert = Insert::create()
    ->into('users')
    ->columns('name', 'email', 'created_at')
    ->values('Alice', 'alice@example.com', Expression::raw('NOW()'))
    ->values('Bob',   'bob@example.com',   Expression::raw('NOW()'));

// INSERT ... SELECT
$archive = Insert::create()
    ->into('users_archive')
    ->columns('id', 'name')
    ->select($selectQuery);

// MySQL upsert with RETURNING (MariaDB, PostgreSQL, SQLite)
$upsert = Insert::create()
    ->into('users')
    ->columns('id', 'email')
    ->values(1, 'a@example.com')
    ->onDuplicateKeyUpdate('email', Expression::raw('VALUES(email)'))
    ->returning('id');
```

Scalar values are quoted automatically; `ExpressionInterface` arguments (e.g. `Expression::raw('NOW()')`, sequences) pass through unchanged.

### UPDATE

```php
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Dml\Update;

$update = Update::create()
    ->table('users', 'u')
    ->set('name', 'New Name')
    ->set('updated_at', Expression::raw('NOW()'))
    ->where(Expression::binary('id', BinaryOperator::Eq, 1));

// Multi-table (PostgreSQL FROM), ORDER BY / LIMIT (MySQL), RETURNING
$bulk = Update::create()
    ->table('users', 'u')
    ->set('name', Expression::ref('a.new_name'))
    ->from('audit', 'a')
    ->where(Expression::binary('u.id', BinaryOperator::Eq, Expression::ref('a.user_id')))
    ->orderBy('u.id', SortDirection::DESC)
    ->limit(100)
    ->returning('u.id');
```

WHERE conditions can be incrementally composed with `andWhere()` and `orWhere()`.

### DELETE

```php
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Dml\Delete;

$delete = Delete::create()
    ->from('users')
    ->where(Expression::binary('active', BinaryOperator::Eq, 0));

// Multi-table (PostgreSQL USING), ORDER BY / LIMIT (MySQL), RETURNING
$bulk = Delete::create()
    ->from('users', 'u')
    ->using('audit', 'a')
    ->where(Expression::binary('u.id', BinaryOperator::Eq, Expression::ref('a.user_id')))
    ->orderBy('u.id', SortDirection::DESC)
    ->limit(100)
    ->returning('u.id');
```

## DDL — Schema

### Table

`Table::create()` builds a `CREATE TABLE` statement; use `column()` / `constraint()` (or their plural `columns()` / `constraints()` variants) to populate it. The `addColumn()` / `addConstraint()` / `dropColumn()` family is reserved for `Table::alter()` mode.

```php
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Ddl\PrimaryKey;
use Rak200\SqlBuilder\Ddl\Table;

$table = Table::create('users')
    ->column(Column::create('id', DataType::BigInt)->autoIncrement()->nullable(false))
    ->column(Column::create('email', DataType::VarChar)->length(255)->nullable(false))
    ->constraint(PrimaryKey::create()->columns(['id']));

echo $table; // CREATE TABLE `users` (`id` BIGINT NOT NULL AUTO_INCREMENT, ...)
```

`ALTER TABLE`:

```php
$alter = Table::alter('users')
    ->addColumn(Column::create('created_at', DataType::DateTime))
    ->dropColumn('legacy_flag')
    ->renameColumn('email', 'email_address');
```

### Foreign key

`ForeignKey::create()` requires a constraint name; `columns()` and `references()` both take arrays.

```php
use Rak200\SqlBuilder\Ddl\ForeignKey;
use Rak200\SqlBuilder\Common\Enum\ForeignKeyAction;

$fk = ForeignKey::create('fk_users_role_id')
    ->columns(['role_id'])
    ->references('roles', ['id'])
    ->onDelete(ForeignKeyAction::CASCADE);
```

### View

The view body is supplied via `query()`. `orReplace()` and `ifNotExists()` are mutually exclusive.

```php
use Rak200\SqlBuilder\Ddl\View;

$view = View::create('active_users')
    ->orReplace()
    ->query($selectQuery);
```

### Sequence

```php
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Ddl\Sequence;
use Rak200\SqlBuilder\Ddl\Table;

$seq = Sequence::create('order_id_seq')
    ->startWith(1000)
    ->incrementBy(1)
    ->noMaxValue()
    ->cache(20);

$orders = Table::create('orders')
    ->column(Column::create('id', DataType::BigInt)->nullable(false)->sequence($seq));
```

### Drop / truncate

Every DDL builder exposes a `drop()` factory; `Table` additionally exposes `truncate()`. `IF EXISTS`, `CASCADE` / `RESTRICT` and `RESTART IDENTITY` / `CONTINUE IDENTITY` are fluent modifiers.

```php
use Rak200\SqlBuilder\Ddl\Index;
use Rak200\SqlBuilder\Ddl\Sequence;
use Rak200\SqlBuilder\Ddl\Table;
use Rak200\SqlBuilder\Ddl\View;

echo Table::drop('users')->ifExists()->cascade();
// DROP TABLE IF EXISTS `users` CASCADE

echo Table::truncate('users')->restartIdentity()->cascade();
// TRUNCATE TABLE `users` RESTART IDENTITY CASCADE

echo View::drop('active_users')->ifExists();
// DROP VIEW IF EXISTS `active_users`

echo Index::drop('idx_users_email')->ifExists()->cascade();
// DROP INDEX IF EXISTS `idx_users_email` CASCADE
// (MariaDB requires the parent table; call ->table('users') and the dialect
//  will emit `DROP INDEX `idx_users_email` ON `users``.)

echo Sequence::drop('order_id_seq')->ifExists()->cascade();
// DROP SEQUENCE IF EXISTS `order_id_seq` CASCADE
```

MariaDB rejects PostgreSQL-only TRUNCATE modifiers (`RESTART IDENTITY`, `CONTINUE IDENTITY`, `CASCADE`, `RESTRICT`) with `UnsupportedFeatureException`. `DROP INDEX` on MariaDB requires the parent table — see {@see MariaDbDialect}.

## Expressions

```php
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;

Expression::binary('age', BinaryOperator::Ge, 18); // `age` >= 18
Expression::and($expr1, $expr2, $expr3);                           // (a AND b AND c)
Expression::or($expr1, $expr2);                                    // (a OR b)
Expression::not($expr);                                            // NOT (...)
Expression::exists($subquery);                                     // EXISTS (SELECT ...)
Expression::count('*');                                            // COUNT(*)
Expression::func('COALESCE', Expression::ref('nickname'), 'guest');// COALESCE(`nickname`, 'guest')
Expression::raw('NOW()');                                          // NOW()
```

Use `Expression::column()` for SELECT projections (supports an alias), `Expression::ref()` for column references inside conditions/`ORDER BY`/`GROUP BY`, and `Expression::identifier()` for bare names in `USING (...)`.

## Status & Roadmap

Current version: **0.8.0** — early development, **unstable**. The API may still break between `0.x` releases and the library is not yet recommended for production use.

### What works today

- **DML:** `Select` (DISTINCT, JOINs incl. NATURAL/USING, WHERE/AND/OR, GROUP BY, HAVING, ORDER BY with NULL placement, LIMIT/OFFSET, subqueries), `Set` (UNION, UNION ALL, EXCEPT, INTERSECT) with ORDER BY/LIMIT/OFFSET on the combined result, `Insert` (single/multi-row VALUES, INSERT ... SELECT, ON DUPLICATE KEY UPDATE, RETURNING), `Update` (SET, multi-table FROM, WHERE, ORDER BY/LIMIT, RETURNING), `Delete` (multi-table USING, WHERE, ORDER BY/LIMIT, RETURNING).
- **DDL:** `Table` (CREATE, ALTER, DROP, TRUNCATE — with IF EXISTS / CASCADE / RESTRICT / RESTART IDENTITY / CONTINUE IDENTITY modifiers and ADD/DROP/MODIFY/RENAME column, ADD/DROP CONSTRAINT, ADD INDEX, RENAME TO in ALTER mode), `Column`, `View` (CREATE with OR REPLACE / TEMPORARY / IF NOT EXISTS / WITH CHECK OPTION, plus DROP), `Sequence` (CREATE, ALTER incl. RESTART / NEXTVAL, and DROP), `Index` (CREATE and DROP), `Schema` (CREATE / DROP / ALTER ... RENAME TO; on MariaDB simulated as table-name prefixing), and constraints (`PrimaryKey`, `UniqueKey`, `ForeignKey`, `Check`).
- **Expressions:** binary/unary operators (compact mnemonics `Eq`/`Ne`/`Gt`/`Lt`/`Ge`/`Le`, plus null-safe `NullSafeEq`/`NullSafeNe` that emit `IS [NOT] DISTINCT FROM` on the default/Postgres dialect and `<=>` / `NOT (<=>)` on MariaDB), AND/OR groups, EXISTS, subqueries, function calls, aggregates (`COUNT`, `SUM`, `AVG`, `MIN`, `MAX`), `CASE WHEN` (searched and simple forms), window functions (`OVER (PARTITION BY ... ORDER BY ... ROWS/RANGE/GROUPS ...)`), raw SQL escape hatch, identifier and value quoting.
- **SELECT extensions:** Common Table Expressions (`WITH name [(cols)] AS (...)`) with `Select::with()` / `withRecursive()`, including multi-CTE and recursive bodies via `Set` unions.
- **Parameter binding:** opt-in `prepare(Dialect): PreparedStatement` on every DML builder. `Expression::param(int|string, mixed)` declares positional (`?` / `$N`) or named (`:name`) placeholders; existing `ValueExpression` values auto-convert in bind mode. Postgres reuses `$N` natively for repeated keys; MariaDB/MySQL duplicates values per `?` occurrence; named placeholders are reusable on both via PDO emulation.
- **UUID columns:** `DataType::Uuid` for DDL plus `Expression::uuid(value)` / `Expression::uuidColumn(name)` for DML. PostgreSQL gets the native `UUID` type with `::uuid` casts on literals/parameters where the type can't be inferred; MariaDB stores as `BINARY(16)` with transparent `UUID_TO_BIN(...)` / `BIN_TO_UUID(...)` wrapping at value and projection boundaries — same pattern as the schema simulation.
- **Dialects:** abstract `Dialect` base with a permissive `DefaultDialect`, vendor dialects (`MariaDbDialect` / `MariaDb105Dialect`, `PostgresDialect` / `Postgres15Dialect`), one renderer class per component, runtime selection via `Dialect::fromDsn()`, opt-in per-call rendering via `toSql(Dialect)`. Vendor-specific feature gates (e.g. PostgreSQL rejects `ON DUPLICATE KEY UPDATE`, MariaDB <10.5 rejects `RETURNING`) raise `UnsupportedFeatureException`.
- **Tests:** PHPUnit 13 unit suite under `tests/Unit/`; run with `composer test`.

## Versioning

Follows [Semantic Versioning](https://semver.org).

When releasing a new version:
1. Update `"version"` in `composer.json`
2. Update `CHANGELOG.md`: add a new `## [x.y.z] - YYYY-MM-DD` section with `### Added / Changed / Fixed / Removed` entries and a comparison link at the bottom
3. Update the version reference in this README
4. Commit and push
5. Create and push a git tag matching the version: `git tag x.y.z && git push origin x.y.z`

## License

MIT
