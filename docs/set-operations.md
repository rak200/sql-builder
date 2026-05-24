[← Docs index](README.md)

# Set operations (UNION, EXCEPT, INTERSECT)

```php
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Set;
```

`Set` wraps two or more `Select` queries with set operators. Start with `Set::create($first)` and chain `union()` / `except()` / `intersect()` for each additional operand.

## Contents

- [UNION](#union)
- [EXCEPT](#except)
- [INTERSECT](#intersect)
- [Chaining](#chaining)
- [ORDER BY / LIMIT / OFFSET on the combined result](#order-by--limit--offset-on-the-combined-result)
- [Set as a CTE body](#set-as-a-cte-body)
- [SQLite caveat](#sqlite-caveat)
- [Prepared statements](#prepared-statements)

[↑ Back to top](#)

## UNION

```php
$active   = Select::create()->select('id')->from('users')->where(Expr::binary('active', Binary::Eq, 1));
$inactive = Select::create()->select('id')->from('users')->where(Expr::binary('active', Binary::Eq, 0));

Set::create($active)->union($inactive);
// (SELECT `id` FROM `users` WHERE (`active` = 1)) UNION (SELECT `id` FROM `users` WHERE (`active` = 0))

// UNION ALL — preserves duplicates
Set::create($active)->union($inactive, all: true);
// ... UNION ALL ...
```

[↑ Back to top](#)

## EXCEPT

Rows in the running result that are *not* in the operand:

```php
Set::create($allUsers)->except($bannedUsers);
// (SELECT ...) EXCEPT (SELECT ...)
```

[↑ Back to top](#)

## INTERSECT

Rows present in both:

```php
Set::create($premiumUsers)->intersect($activeUsers);
// (SELECT ...) INTERSECT (SELECT ...)
```

[↑ Back to top](#)

## Chaining

Operators can be mixed and chained. The result is evaluated left-to-right (every dialect follows the standard precedence: `INTERSECT` binds tighter than `UNION`/`EXCEPT`, but parentheses around each operand make that moot here).

```php
Set::create($a)
    ->union($b)
    ->union($c, all: true)
    ->except($d)
    ->intersect($e);
// (SELECT ...) UNION (SELECT ...) UNION ALL (SELECT ...) EXCEPT (SELECT ...) INTERSECT (SELECT ...)
```

[↑ Back to top](#)

## ORDER BY / LIMIT / OFFSET on the combined result

```php
Set::create($selectA)
    ->union($selectB)
    ->orderBy('name')
    ->limit(50)
    ->offset(0);
// (SELECT ...) UNION (SELECT ...) ORDER BY `name` ASC LIMIT 50
```

These clauses apply to the **combined** result, not to any individual operand. To order an operand internally, do it inside that `Select` itself.

[↑ Back to top](#)

## Set as a CTE body

A `Set` is a valid CTE body — required for recursive CTEs:

```php
$base = Select::create()->select(Expr::val(1)->as('n'));
$step = Select::create()
    ->select(Expr::raw('n + 1'))
    ->from('numbers')
    ->where(Expr::binary('n', Binary::Lt, 10));

Select::create()
    ->withRecursive('numbers', Set::create($base)->union($step, all: true), ['n'])
    ->select('n')
    ->from('numbers');
// WITH RECURSIVE `numbers` (`n`) AS ((SELECT ...) UNION ALL (SELECT ...)) SELECT `n` FROM `numbers`
```

[↑ Back to top](#)

## SQLite caveat

SQLite's parser rejects the parenthesised form `(SELECT...) UNION (SELECT...)` that the library emits — every other major engine (PostgreSQL, MariaDB, MySQL) accepts it. This is a SQLite quirk, not a library bug. The integration smoke tests under `tests/Integration/` document and work around it.

[↑ Back to top](#)

## Prepared statements

```php
$set = Set::create($queryA)->union($queryB);
$prepared = $set->prepare(new DefaultDialect());
```

The binder threads through both operands; parameter values from each `Select` end up in `$prepared->parameters` in declaration order.

[↑ Back to top](#)
