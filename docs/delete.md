[← Docs index](README.md)

# DELETE

```php
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Common\Enum\Sort\Direction;
use Rak200\SqlBuilder\Dml\Delete;
```

## Contents

- [Basic DELETE](#basic-delete)
- [Table alias](#table-alias)
- [WHERE composition](#where-composition)
- [Multi-table DELETE (PostgreSQL USING)](#multi-table-delete-postgresql-using)
- [ORDER BY and LIMIT (MySQL extension)](#order-by-and-limit-mysql-extension)
- [RETURNING](#returning)
- [Prepared statements](#prepared-statements)

[↑ Back to top](#)

## Basic DELETE

```php
Delete::create()
    ->from('users')
    ->where(Expr::binary('active', Binary::Eq, 0));
// DELETE FROM `users` WHERE (`active` = 0)
```

A `Delete` without `where()` deletes every row in the target table — the library does not refuse to render that. Be deliberate.

[↑ Back to top](#)

## Table alias

```php
Delete::create()
    ->from('users', 'u')
    ->where(Expr::binary('u.id', Binary::Eq, 1));
// DELETE FROM `users` AS `u` WHERE (`u`.`id` = 1)
```

[↑ Back to top](#)

## WHERE composition

```php
Delete::create()
    ->from('sessions')
    ->where(Expr::binary('expires_at', Binary::Lt, Expr::raw('NOW()')))
    ->andWhere(Expr::binary('user_id', Binary::Is, null));
```

`andWhere()` is an alias for `where()`; both AND-compose. `orWhere()` OR-composes.

[↑ Back to top](#)

## Multi-table DELETE (PostgreSQL USING)

```php
Delete::create()
    ->from('users', 'u')
    ->using('audit', 'a')
    ->where(Expr::binary('u.id', Binary::Eq, Expr::ref('a.user_id')))
    ->andWhere(Expr::binary('a.action', Binary::Eq, 'banned'));
// DELETE FROM `users` AS `u` USING `audit` AS `a` WHERE ...
```

`using()` may be called multiple times for additional reference tables. On `MariaDbDialect` (base) the USING clause is rejected with `UnsupportedFeatureException`.

[↑ Back to top](#)

## ORDER BY and LIMIT (MySQL extension)

```php
Delete::create()
    ->from('users')
    ->where(Expr::binary('active', Binary::Eq, 0))
    ->orderBy('created_at', Direction::ASC)
    ->limit(100);
// DELETE FROM `users` WHERE ... ORDER BY `created_at` ASC LIMIT 100
```

Supported on MySQL / MariaDB; rejected on PostgreSQL.

[↑ Back to top](#)

## RETURNING

```php
Delete::create()
    ->from('users')
    ->where(Expr::binary('id', Binary::Eq, 1))
    ->returning('id', 'email');
// ... RETURNING `id`, `email`
```

Supported on PostgreSQL, MariaDB ≥ 10.5, SQLite ≥ 3.35. On base `MariaDbDialect` it throws.

[↑ Back to top](#)

## Prepared statements

```php
Delete::create()
    ->from('users')
    ->where(Expr::binary('id', Binary::Eq, Expr::param(0)))
    ->prepare(new DefaultDialect());
// sql:        DELETE FROM `users` WHERE (`id` = ?)
// parameters: [null]
```

[↑ Back to top](#)
