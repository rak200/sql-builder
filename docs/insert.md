# INSERT

```php
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;
```

## Basic INSERT … VALUES

```php
Insert::create()
    ->into('users')
    ->columns('id', 'name', 'email')
    ->values(1, 'alice', 'a@example.com');
// INSERT INTO `users` (`id`, `name`, `email`) VALUES (1, 'alice', 'a@example.com')
```

`columns()` declares the column list (and fixes the row arity for subsequent `values()` calls). Scalar values are quoted automatically; `ExpressionInterface` arguments (raw SQL, function calls, sequences) pass through unchanged.

[↑ Back to top](#)

## Multi-row

Call `values()` multiple times:

```php
Insert::create()
    ->into('users')
    ->columns('name', 'email', 'created_at')
    ->values('Alice', 'alice@example.com', Expr::raw('NOW()'))
    ->values('Bob',   'bob@example.com',   Expr::raw('NOW()'));
// INSERT INTO `users` (`name`, `email`, `created_at`)
// VALUES ('Alice', 'alice@example.com', NOW()), ('Bob', 'bob@example.com', NOW())
```

Each row must have the same arity as the declared columns (or as the first row when `columns()` is omitted).

[↑ Back to top](#)

## INSERT … SELECT

```php
$source = Select::create()
    ->select('id', 'name')
    ->from('staging_users')
    ->where(Expr::raw('imported_at IS NULL'));

Insert::create()
    ->into('users')
    ->columns('id', 'name')
    ->select($source);
// INSERT INTO `users` (`id`, `name`) SELECT ...
```

`values()` and `select()` are mutually exclusive — mixing them throws.

[↑ Back to top](#)

## Portable upsert — `onConflict()`

The cross-dialect upsert API. Same call site renders differently per dialect.

```php
Insert::create()
    ->into('users')
    ->columns('id', 'email')
    ->values(1, 'a@example.com')
    ->onConflict('id')
    ->doUpdate(['email' => Expr::raw('EXCLUDED.email')]);

// Default / Postgres:
//   ... ON CONFLICT (`id`) DO UPDATE SET `email` = EXCLUDED.email
//
// MariaDB / MySQL — translated automatically:
//   ... ON DUPLICATE KEY UPDATE `email` = EXCLUDED.email
```

`onConflict()` accepts a string (single column) or array (composite). Pass `[]` to omit the target list (PostgreSQL infers the primary-key conflict; MariaDB ignores the target).

### `DO NOTHING`

```php
Insert::create()
    ->into('users')->columns('id', 'email')
    ->values(1, 'a@example.com')
    ->onConflict('id')
    ->doNothing();
// Postgres: ... ON CONFLICT (`id`) DO NOTHING
// MariaDB:  UnsupportedFeatureException — use INSERT IGNORE manually
```

### `WHERE` filter on the conflict action (Postgres-only)

```php
Insert::create()
    ->into('users')->columns('id', 'last_login')
    ->values(1, Expr::raw('NOW()'))
    ->onConflict('id')
    ->doUpdate(['last_login' => Expr::raw('EXCLUDED.last_login')])
    ->onConflictWhere(Expr::raw('users.last_login < EXCLUDED.last_login'));
// Postgres: ... ON CONFLICT (`id`) DO UPDATE SET ... WHERE users.last_login < ...
// MariaDB:  UnsupportedFeatureException
```

[↑ Back to top](#)

## Legacy MariaDB upsert — `onDuplicateKeyUpdate()`

For raw MariaDB-flavoured statements when you want to bypass the portable layer:

```php
Insert::create()
    ->into('users')
    ->columns('id', 'email')
    ->values(1, 'a@example.com')
    ->onDuplicateKeyUpdate('email', Expr::raw('VALUES(email)'));
// ... ON DUPLICATE KEY UPDATE `email` = VALUES(email)   (rejected on Postgres)
```

Mixing `onConflict()` and `onDuplicateKeyUpdate()` on the same statement throws — pick one.

[↑ Back to top](#)

## RETURNING

Supported on PostgreSQL, MariaDB ≥ 10.5, and SQLite ≥ 3.35.

```php
Insert::create()
    ->into('users')
    ->columns('id', 'name')
    ->values(1, 'alice')
    ->returning('id', 'name');
// ... RETURNING `id`, `name`
```

On `MariaDbDialect` (base, < 10.5) it throws `UnsupportedFeatureException`. Use `MariaDb105Dialect` (or `Dialect::fromDsn('mariadb://h/db?version=10.5')`) to enable it on MariaDB.

[↑ Back to top](#)

## Prepared statements

```php
Insert::create()
    ->into('users')
    ->columns('id', 'name')
    ->values(Expr::param(0), Expr::param(1))
    ->prepare(new DefaultDialect());
// sql:        INSERT INTO `users` (`id`, `name`) VALUES (?, ?)
// parameters: [null, null]  (defaults; override per execution)
```

Use named parameters when you want to reuse the same value in multiple positions:

```php
Insert::create()
    ->into('users')
    ->columns('id', 'created_by', 'updated_by')
    ->values(Expr::param('id', 1), Expr::param('user', 42), Expr::param('user'))
    ->prepare(new DefaultDialect());
// sql:        ... VALUES (:id, :user, :user)
// parameters: ['id' => 1, 'user' => 42]
```

See [Prepared statements](prepared-statements.md).

[↑ Back to top](#)
