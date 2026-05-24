# DELETE

```php
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Common\Enum\Sort\Direction;
use Rak200\SqlBuilder\Dml\Delete;
```

## Basic DELETE

```php
Delete::create()
    ->from('users')
    ->where(Expr::binary('active', Binary::Eq, 0));
// DELETE FROM `users` WHERE (`active` = 0)
```

A `Delete` without `where()` deletes every row in the target table — the library does not refuse to render that. Be deliberate.

## Table alias

```php
Delete::create()
    ->from('users', 'u')
    ->where(Expr::binary('u.id', Binary::Eq, 1));
// DELETE FROM `users` AS `u` WHERE (`u`.`id` = 1)
```

## WHERE composition

```php
Delete::create()
    ->from('sessions')
    ->where(Expr::binary('expires_at', Binary::Lt, Expr::raw('NOW()')))
    ->andWhere(Expr::binary('user_id', Binary::Is, null));
```

`andWhere()` is an alias for `where()`; both AND-compose. `orWhere()` OR-composes.

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

## RETURNING

```php
Delete::create()
    ->from('users')
    ->where(Expr::binary('id', Binary::Eq, 1))
    ->returning('id', 'email');
// ... RETURNING `id`, `email`
```

Supported on PostgreSQL, MariaDB ≥ 10.5, SQLite ≥ 3.35. On base `MariaDbDialect` it throws.

## Prepared statements

```php
Delete::create()
    ->from('users')
    ->where(Expr::binary('id', Binary::Eq, Expr::param(0)))
    ->prepare(new DefaultDialect());
// sql:        DELETE FROM `users` WHERE (`id` = ?)
// parameters: [null]
```
