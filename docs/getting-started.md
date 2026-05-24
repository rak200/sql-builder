[← Docs index](README.md)

# Getting started

## Contents

- [Installation](#installation)
- [Your first query](#your-first-query)
- [Mental model](#mental-model)
- [Inline values vs prepared statements](#inline-values-vs-prepared-statements)
- [What's next](#whats-next)

[↑ Back to top](#)

## Installation

```bash
composer require rak200/sql-builder
```

Requires **PHP 8.4+**. The library has two runtime dependencies (`rak200/collections`, `rak200/utils`) that composer resolves automatically.

[↑ Back to top](#)

## Your first query

```php
<?php

require 'vendor/autoload.php';

use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Dml\Select;

$query = Select::create()
    ->select('id', 'name')
    ->from('users')
    ->where(Expr::binary('id', Binary::Eq, 1));

echo $query;
// SELECT `id`, `name` FROM `users` WHERE (`id` = 1)
```

That's it — `echo $query` calls `__toString()`, which renders the SQL through the default dialect. You then pass the string to PDO (or any driver) yourself.

[↑ Back to top](#)

## Mental model

The library has three layers:

1. **Builders** (`Select`, `Insert`, `Update`, `Delete`, `Merge`, `Set`, `Table`, …) — fluent state containers. Calling `->where(...)` does nothing more than append to an internal array. They do not render anything until you ask.
2. **Expressions** (`Expr::col(...)`, `Expr::binary(...)`, `Expr::raw(...)`, …) — the smaller pieces that go into builders. All implement `ExpressionInterface` so you can compose freely.
3. **Dialects** (`DefaultDialect`, `PostgresDialect`, `MariaDbDialect`, …) — own the actual SQL rendering. The default dialect is permissive; vendor dialects override individual renderers to reject, simulate, or translate features.

Rendering always goes through a dialect:

```php
$query->__toString();                       // default dialect → backticks
$query->toSql(new PostgresDialect());       // double quotes
$query->toSql(Dialect::fromDsn('pgsql://localhost/app')); // from a DSN
```

There is **no execution layer**. The library produces SQL strings and (via `prepare()`) parameter arrays — you wire those into PDO, your ORM, your migration runner, anything that takes raw SQL.

[↑ Back to top](#)

## Inline values vs prepared statements

Two modes for binding values into SQL.

**Inline** (default — direct call to `__toString()` / `toSql()`):

```php
$sql = (string) Select::create()
    ->from('users')
    ->where(Expr::binary('id', Binary::Eq, 1));
// SELECT * FROM `users` WHERE (`id` = 1)
```

The dialect's `quoteValue()` escapes scalars and inlines them.

**Prepared** (via `prepare(Dialect)`):

```php
$prepared = Select::create()
    ->from('users')
    ->where(Expr::binary('id', Binary::Eq, Expr::param(0)))
    ->prepare(new DefaultDialect());

$prepared->sql;        // SELECT * FROM `users` WHERE (`id` = ?)
$prepared->parameters; // [0 => null]

$pdoStmt = $pdo->prepare($prepared->sql);
$pdoStmt->execute([1]);
```

Use prepared statements whenever user input flows into a value position. The library handles per-dialect placeholder shapes (`?` on MariaDB / SQLite, `$N` on Postgres, `:name` for named) — see [Prepared statements](prepared-statements.md) for details.

[↑ Back to top](#)

## What's next

- [Expressions](expressions.md) — the building blocks behind every clause
- [DML — SELECT](select.md) — the most-used builder, with all the join / where / group / order options
- [Dialects](dialects.md) — switching dialects, vendor-specific gates, writing your own

[↑ Back to top](#)
