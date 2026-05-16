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
    ->orderBy('u.name', SortDirection::Asc)
    ->limit(20)
    ->offset(0);

echo $query; // SELECT `id`, `name`, `email` FROM `users` AS `u` WHERE ...
```

### JOIN

```php
use Rak200\SqlBuilder\Common\Enum\JoinType;

$query = Select::create()
    ->select('u.name', 'r.role')
    ->from('users', 'u')
    ->join(JoinType::Inner, 'roles', 'r', Expression::binary('u.role_id', BinaryOperator::Eq, Expression::column('r.id')));
```

### Set operations (UNION, EXCEPT, INTERSECT)

```php
use Rak200\SqlBuilder\Dml\Set;

$union = Set::union($selectA, $selectB)->orderBy('name')->limit(50);
```

## DDL — Schema

### Table

```php
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Ddl\PrimaryKey;
use Rak200\SqlBuilder\Ddl\Table;

$table = Table::create('users')
    ->addColumn(Column::create('id', DataType::BIGINT)->autoIncrement())
    ->addColumn(Column::create('email', DataType::VARCHAR, 255)->notNull())
    ->addConstraint(PrimaryKey::create()->columns('id'));

echo $table; // CREATE TABLE `users` ( ... )
```

### Foreign key

```php
use Rak200\SqlBuilder\Ddl\ForeignKey;
use Rak200\SqlBuilder\Common\Enum\ForeignKeyAction;

$fk = ForeignKey::create()
    ->columns('role_id')
    ->references('roles', 'id')
    ->onDelete(ForeignKeyAction::Cascade);
```

### View

```php
use Rak200\SqlBuilder\Ddl\View;

$view = View::create('active_users')->orReplace()->as($selectQuery);
```

## Expressions

```php
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\UnaryOperator;

Expression::binary('age', BinaryOperator::GtEq, 18);       // `age` >= 18
Expression::and(expr1, expr2, expr3);                       // (a AND b AND c)
Expression::or(expr1, expr2);                               // (a OR b)
Expression::exists($subquery);                              // EXISTS (SELECT ...)
Expression::count('*');                                     // COUNT(*)
Expression::raw('NOW()');                                   // NOW()
```

## Versioning

Follows [Semantic Versioning](https://semver.org). Current version: **0.0.1** — unstable until unit tests are added.

When releasing a new version:
1. Update `"version"` in `composer.json`
2. Commit and push
3. `git tag 0.x.y && git push origin 0.x.y`

## License

MIT
