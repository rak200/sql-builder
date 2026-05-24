[← Docs index](README.md)

# Prepared statements

```php
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dml\Select;
```

When user input flows into value positions, **always** use prepared statements rather than inlining values. The library produces SQL with placeholders and a parallel parameter array; you hand both to PDO (or any driver that accepts the same shape).

## API

Every DML builder and every expression has a `prepare(Dialect $dialect)` method that returns a `PreparedStatement`:

```php
final class PreparedStatement {
    public function __construct(
        public readonly string $sql,
        public array $parameters = []
    ) {}
}
```

`$sql` carries placeholders matching the dialect's binder; `$parameters` is mutable so you can rebind values between runs.

[↑ Back to top](#)

## Inline values become anonymous placeholders

In bind mode, every `Expr::val()` value is automatically converted to a placeholder. You don't have to rewrite existing builders:

```php
$prepared = Select::create()
    ->from('users')
    ->where(Expr::binary('id', Binary::Eq, 1))
    ->prepare(new DefaultDialect());

$prepared->sql;        // SELECT * FROM `users` WHERE (`id` = ?)
$prepared->parameters; // [1]
```

The same builder rendered with `(string) $query` would inline `1` directly; under `prepare()` it becomes `?` plus the value in `$parameters`.

[↑ Back to top](#)

## Explicit parameters with `Expr::param()`

For control over reuse, defaults, or named placeholders, declare parameters explicitly:

```php
Expr::param(0);                  // positional placeholder, no default
Expr::param(0, 1);               // positional placeholder with default value 1
Expr::param('user_id');          // named placeholder :user_id
Expr::param('user_id', 1);       // named placeholder :user_id with default 1
```

The placeholder key drives reuse semantics in the binder (see below).

[↑ Back to top](#)

## Placeholder shapes by dialect

| Dialect | Anonymous values | `Expr::param(int $i)` | `Expr::param(string $name)` |
|---------|------------------|----------------------|----------------------------|
| `DefaultDialect`, `MariaDbDialect`, `MariaDb105Dialect` | `?` | `?` (fresh per occurrence; value duplicated) | `:name` (reused; single entry) |
| `PostgresDialect`, `Postgres15Dialect` | `$1`, `$2`, … | `$N` (reused across occurrences of the same key) | `:name` (reused; single entry) |

[↑ Back to top](#)

## Reuse semantics

**Postgres positional reuse** (native): the same `$N` text appears wherever you use the same int key, and the value is stored once.

```php
$prepared = Select::create()
    ->from('users')
    ->where(Expr::binary('id', Binary::Eq, Expr::param(0)))
    ->andWhere(Expr::binary('owner_id', Binary::Eq, Expr::param(0)))
    ->prepare(new PostgresDialect());

$prepared->sql;        // SELECT * FROM "users" WHERE ("id" = $1) AND ("owner_id" = $1)
$prepared->parameters; // [null]   (single entry — reused)
```

**MariaDB positional duplication** (wire protocol limitation): each `?` is independent. The same int key still acts as a "fresh slot" so the value array stays in sync with placeholders.

```php
// Same builder, MariaDB:
$prepared->sql;        // SELECT * FROM `users` WHERE (`id` = ?) AND (`owner_id` = ?)
$prepared->parameters; // [null, null]    (two entries — duplicated)
```

**Named placeholders** are reusable on every dialect through PDO emulation:

```php
$prepared = Select::create()
    ->from('users')
    ->where(Expr::binary('id', Binary::Eq, Expr::param('id', 1)))
    ->andWhere(Expr::binary('parent_id', Binary::Eq, Expr::param('id')))
    ->prepare(new DefaultDialect());

$prepared->sql;        // SELECT * FROM `users` WHERE (`id` = :id) AND (`parent_id` = :id)
$prepared->parameters; // ['id' => 1]
```

The default value (`1` in the example) is stored when the key is first emitted; later occurrences with no default leave the value untouched. Callers can override per run by mutating `$prepared->parameters` directly.

[↑ Back to top](#)

## Anonymous mode in detail

When you don't use `Expr::param()`, inline values still become placeholders — but they get no key, so they always get a fresh slot:

```php
$prepared = Select::create()
    ->from('users')
    ->where(Expr::binary('id', Binary::Eq, 1))
    ->andWhere(Expr::binary('id', Binary::Eq, 1))    // same literal!
    ->prepare(new PostgresDialect());

$prepared->sql;        // ... WHERE ("id" = $1) AND ("id" = $2)
$prepared->parameters; // [1, 1]
```

To reuse a placeholder on Postgres, declare the parameter explicitly with a stable key (`Expr::param(0, 1)`).

[↑ Back to top](#)

## Wiring into PDO

```php
$prepared = Select::create()
    ->from('users')
    ->where(Expr::binary('id', Binary::Eq, Expr::param(0)))
    ->prepare(new DefaultDialect());

$stmt = $pdo->prepare($prepared->sql);

// Override the default per execution
$prepared->parameters[0] = 42;
$stmt->execute($prepared->parameters);
```

Named placeholders bind by key:

```php
$prepared->parameters['user_id'] = 42;
$stmt->execute($prepared->parameters);   // PDO matches `:user_id` to 'user_id'
```

[↑ Back to top](#)

## Edge cases

- **Outside `prepare()`**: rendering a builder containing `Expr::param()` via `__toString()` or `toSql()` (no binder attached) throws `LogicException`. The placeholder cannot be rendered without a binder.
- **`Expr::raw()`** never becomes a placeholder — it's the explicit "I know what I'm doing, pass this through" escape hatch. Don't put user input there.
- **`LIMIT` / `OFFSET`**: these are inlined integers even in bind mode (most drivers can't bind LIMIT placeholders portably). Pass `Expr::param()` to `limit()` is not supported — the builder requires `int`.

[↑ Back to top](#)

## Dialect singleton safety

`prepare(Dialect)` clones the dialect before attaching a binder, so the process-wide `Dialect::default()` singleton is never mutated. The clone resets its renderer cache so its renderers point at the clone — there's no leakage even when nested expressions reach back through their renderers.

[↑ Back to top](#)
