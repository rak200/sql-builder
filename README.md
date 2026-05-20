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
    ->where(Expression::binary('u.active', BinaryOperator::Equal, 1))
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
        Expression::binary('u.role_id', BinaryOperator::Equal, Expression::ref('r.id'))
    );
```

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
    ->where(Expression::binary('id', BinaryOperator::Equal, 1));

// Multi-table (PostgreSQL FROM), ORDER BY / LIMIT (MySQL), RETURNING
$bulk = Update::create()
    ->table('users', 'u')
    ->set('name', Expression::ref('a.new_name'))
    ->from('audit', 'a')
    ->where(Expression::binary('u.id', BinaryOperator::Equal, Expression::ref('a.user_id')))
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
    ->where(Expression::binary('active', BinaryOperator::Equal, 0));

// Multi-table (PostgreSQL USING), ORDER BY / LIMIT (MySQL), RETURNING
$bulk = Delete::create()
    ->from('users', 'u')
    ->using('audit', 'a')
    ->where(Expression::binary('u.id', BinaryOperator::Equal, Expression::ref('a.user_id')))
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

## Expressions

```php
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;

Expression::binary('age', BinaryOperator::GreaterThanOrEqual, 18); // `age` >= 18
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

Current version: **0.3.0** — early development, **unstable**. The API may still break between `0.x` releases and the library is not yet recommended for production use.

### What works today

- **DML:** `Select` (DISTINCT, JOINs incl. NATURAL/USING, WHERE/AND/OR, GROUP BY, HAVING, ORDER BY with NULL placement, LIMIT/OFFSET, subqueries), `Set` (UNION, UNION ALL, EXCEPT, INTERSECT) with ORDER BY/LIMIT/OFFSET on the combined result, `Insert` (single/multi-row VALUES, INSERT ... SELECT, ON DUPLICATE KEY UPDATE, RETURNING), `Update` (SET, multi-table FROM, WHERE, ORDER BY/LIMIT, RETURNING), `Delete` (multi-table USING, WHERE, ORDER BY/LIMIT, RETURNING).
- **DDL:** `Table` (CREATE and ALTER: ADD/DROP/MODIFY/RENAME column, ADD/DROP CONSTRAINT, ADD INDEX, RENAME TO), `Column`, `View` (with OR REPLACE / TEMPORARY / IF NOT EXISTS / WITH CHECK OPTION), `Sequence` (CREATE and ALTER incl. RESTART / NEXTVAL), `Index`, and constraints (`PrimaryKey`, `UniqueKey`, `ForeignKey`, `Check`).
- **Expressions:** binary/unary operators, AND/OR groups, EXISTS, subqueries, function calls, aggregates (`COUNT`, `SUM`, `AVG`, `MIN`, `MAX`), raw SQL escape hatch, identifier and value quoting.
- **Dialects:** abstract `Dialect` base with a permissive `DefaultDialect`, vendor dialects (`MariaDbDialect` / `MariaDb105Dialect`, `PostgresDialect` / `Postgres15Dialect`), one renderer class per component, runtime selection via `Dialect::fromDsn()`, opt-in per-call rendering via `toSql(Dialect)`. Vendor-specific feature gates (e.g. PostgreSQL rejects `ON DUPLICATE KEY UPDATE`, MariaDB <10.5 rejects `RETURNING`) raise `UnsupportedFeatureException`.
- **Tests:** PHPUnit 13 unit suite under `tests/Unit/`; run with `composer test`.

### Not yet implemented

DDL drop / truncate
- [ ] `DROP TABLE` (incl. `IF EXISTS`, `CASCADE`)
- [ ] `DROP VIEW`
- [ ] `DROP INDEX`
- [ ] `DROP SEQUENCE`
- [ ] `TRUNCATE TABLE`

SELECT extensions
- [ ] `WITH` / Common Table Expressions (CTEs), incl. recursive
- [ ] Window functions (`OVER`, `PARTITION BY`, frame clauses)
- [ ] `CASE WHEN ... THEN ... ELSE ... END` expression factory

Safety & quality
- [ ] Parameter binding / prepared-statement placeholders. Today values are inlined via string concatenation and quoting — **SQL injection risk if user input reaches value positions**.
- [ ] Consistent identifier quoting across all builders (`Expression::quoteIdentifier()` uses backticks, while `Table`/`View`/`Index`/`Sequence`/constraint builders emit `"..."`).

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
