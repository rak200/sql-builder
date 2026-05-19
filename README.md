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

Current version: **0.0.3** — early development, **unstable**. The API may break between `0.0.x` releases and the library is not yet recommended for production use.

### What works today

- **DML:** `Select` (DISTINCT, JOINs incl. NATURAL/USING, WHERE/AND/OR, GROUP BY, HAVING, ORDER BY with NULL placement, LIMIT/OFFSET, subqueries), `Set` (UNION, UNION ALL, EXCEPT, INTERSECT) with ORDER BY/LIMIT/OFFSET on the combined result.
- **DDL:** `Table` (CREATE and ALTER: ADD/DROP/MODIFY/RENAME column, ADD/DROP CONSTRAINT, ADD INDEX, RENAME TO), `Column`, `View` (with OR REPLACE / TEMPORARY / IF NOT EXISTS / WITH CHECK OPTION), `Sequence` (CREATE and ALTER incl. RESTART / NEXTVAL), `Index`, and constraints (`PrimaryKey`, `UniqueKey`, `ForeignKey`, `Check`).
- **Expressions:** binary/unary operators, AND/OR groups, EXISTS, subqueries, function calls, aggregates (`COUNT`, `SUM`, `AVG`, `MIN`, `MAX`), raw SQL escape hatch, identifier and value quoting.
- **Tests:** PHPUnit 13 unit suite under `tests/Unit/`; run with `composer test`.

### Not yet implemented

DML write statements
- [ ] `Insert` — currently an empty stub at `src/Dml/Insert.php`
- [ ] `Update` — currently an empty stub at `src/Dml/Update.php`
- [ ] `Delete` — currently an empty stub at `src/Dml/Delete.php`

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

Contributions to any of the above are welcome.

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
