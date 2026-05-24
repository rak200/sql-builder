[← Docs index](README.md)

# UPDATE

```php
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Common\Enum\Sort\Direction;
use Rak200\SqlBuilder\Dml\Update;
```

## Basic UPDATE

```php
Update::create()
    ->table('users')
    ->set('name', 'New Name')
    ->set('updated_at', Expr::raw('NOW()'))
    ->where(Expr::binary('id', Binary::Eq, 1));
// UPDATE `users` SET `name` = 'New Name', `updated_at` = NOW() WHERE (`id` = 1)
```

`set()` adds (or replaces) an assignment. Scalars are wrapped in `Expr::val()`; pre-built expressions pass through.

[↑ Back to top](#)

## Table alias

```php
Update::create()
    ->table('users', 'u')
    ->set('email', 'new@example.com')
    ->where(Expr::binary('u.id', Binary::Eq, 1));
// UPDATE `users` AS `u` SET `email` = 'new@example.com' WHERE (`u`.`id` = 1)
```

[↑ Back to top](#)

## WHERE composition

```php
Update::create()
    ->table('users')
    ->set('active', 0)
    ->where(Expr::binary('last_login', Binary::Lt, '2020-01-01'))
    ->andWhere(Expr::binary('active', Binary::Eq, 1))
    ->orWhere(Expr::binary('email', Binary::Is, null));
```

`andWhere()` is an alias for `where()`; both AND-compose. `orWhere()` OR-composes.

[↑ Back to top](#)

## Multi-table UPDATE (PostgreSQL FROM)

```php
Update::create()
    ->table('users', 'u')
    ->set('name', Expr::ref('a.new_name'))
    ->from('audit', 'a')
    ->where(Expr::binary('u.id', Binary::Eq, Expr::ref('a.user_id')));
// UPDATE `users` AS `u` SET `name` = `a`.`new_name` FROM `audit` AS `a` WHERE ...
```

`from()` may be called multiple times for additional reference tables. On `MariaDbDialect` (base) the FROM clause is rejected with `UnsupportedFeatureException`.

[↑ Back to top](#)

## ORDER BY and LIMIT (MySQL extension)

```php
Update::create()
    ->table('users')
    ->set('processed', 1)
    ->where(Expr::binary('processed', Binary::Eq, 0))
    ->orderBy('priority', Direction::DESC)
    ->limit(100);
// UPDATE `users` SET `processed` = 1 WHERE ... ORDER BY `priority` DESC LIMIT 100
```

Supported on MySQL / MariaDB; rejected on PostgreSQL.

[↑ Back to top](#)

## RETURNING

```php
Update::create()
    ->table('users')
    ->set('email', 'new@example.com')
    ->where(Expr::binary('id', Binary::Eq, 1))
    ->returning('id', 'email');
// ... RETURNING `id`, `email`
```

Supported on PostgreSQL, MariaDB ≥ 10.5, SQLite ≥ 3.35. On base `MariaDbDialect` it throws.

[↑ Back to top](#)

## Prepared statements

```php
Update::create()
    ->table('users')
    ->set('email', Expr::param('email'))
    ->where(Expr::binary('id', Binary::Eq, Expr::param('id')))
    ->prepare(new DefaultDialect());
// sql:        UPDATE `users` SET `email` = :email WHERE (`id` = :id)
// parameters: ['email' => null, 'id' => null]
```

See [Prepared statements](prepared-statements.md) for the binder semantics.

[↑ Back to top](#)
