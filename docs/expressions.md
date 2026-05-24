# Expressions

`Expr` is the abstract factory used everywhere — it produces the typed expression objects that builders accept as arguments. All expressions implement `ExpressionInterface` (which extends native `\Stringable`), so any expression can be rendered standalone or composed into a larger statement.

```php
use Rak200\SqlBuilder\Common\Expr;
```

## Column references

Two distinct factories — same name, different purpose:

| Factory | Returns | Use for |
|---------|---------|---------|
| `Expr::col($name, ?$alias)` | `Expression\Column` | SELECT-list projections (supports aliases) |
| `Expr::ref($name)` | `Reference\Column` | column references inside conditions, ORDER BY, GROUP BY, JOIN ON |
| `Expr::identifier($name)` | `Reference\Identifier` | bare names in `USING (...)` (rejects dots) |

```php
Expr::col('email', 'contact_email');  // `email` AS `contact_email`     (SELECT)
Expr::ref('u.id');                     // `u`.`id`                       (WHERE / JOIN)
Expr::identifier('account_id');        // `account_id`                   (USING)
```

The split is intentional: SELECT projections may have aliases (`AS`), but a column reference inside `WHERE a = b` cannot — using `col()` everywhere would emit alias syntax in places where the SQL parser would reject it.

[↑ Back to top](#)

## Literals and raw SQL

```php
Expr::val(42);          // 42
Expr::val('hello');     // 'hello'
Expr::val(null);        // NULL
Expr::val(true);        // TRUE
Expr::raw('NOW()');     // NOW()                  (passes through verbatim, no quoting)
Expr::raw('CURRENT_TIMESTAMP - INTERVAL 1 DAY');
```

`val()` runs the value through the dialect's `quoteValue()` — string escaping differs between MariaDB (backslash-escapes) and Postgres (standard-conforming). `raw()` is the escape hatch when no factory exists for what you need.

> ⚠️ **Never interpolate user input into `raw()`** — that's a SQL injection vulnerability. Use [parameters](#prepared-statement-parameters) instead.

[↑ Back to top](#)

## Binary operators (predicates and arithmetic)

```php
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Common\Enum\Operator\Math;

Expr::binary('age', Binary::Ge, 18);                       // (`age` >= 18)
Expr::binary('status', Binary::Ne, 'banned');              // (`status` <> 'banned')
Expr::binary('email', Binary::Like, '%@example.com');      // (`email` LIKE '%@example.com')

// Null-safe comparisons (IS [NOT] DISTINCT FROM on Postgres/default,
// <=> / NOT (<=>) on MariaDB)
Expr::binary('a', Binary::NullSafeEq, null);                // (`a` IS NOT DISTINCT FROM NULL)

// Arithmetic with the dedicated Math operator
Expr::binary('price', Math::Mul, 1.1);                      // (`price` * 1.1)
```

Available `Binary` cases: `Eq`, `Ne`, `Gt`, `Lt`, `Ge`, `Le`, `Like`, `NotLike`, `In`, `NotIn`, `Is`, `IsNot`, `NullSafeEq`, `NullSafeNe`, `And`, `Or`, `Between`, `NotBetween`.

Available `Math` cases: `Add`, `Sub`, `Mul`, `Div`, `Mod`.

[↑ Back to top](#)

## Logical composition

```php
Expr::and($a, $b, $c);   // (a AND b AND c)
Expr::or($a, $b);        // (a OR b)
Expr::not($a);           // NOT (a)
```

`and()` / `or()` are left-associative and accept any number of operands. Anything implementing `ExpressionInterface` works.

[↑ Back to top](#)

## Arithmetic chains

Shortcuts that produce left-associative chains of `Math::*` binary expressions:

```php
Expr::add('a', 'b', 'c');     // ((`a` + `b`) + `c`)
Expr::sub(10, 'discount');    // (10 - `discount`)
Expr::mul('qty', 'price');    // (`qty` * `price`)
Expr::div('total', 'qty');    // (`total` / `qty`)
Expr::mod('id', 16);          // (`id` % 16)
```

[↑ Back to top](#)

## Function calls and aggregates

```php
Expr::func('COALESCE', Expr::ref('nickname'), 'guest');
// COALESCE(`nickname`, 'guest')

Expr::func('UPPER', 'name');
// UPPER(`name`)

// Aggregates auto-alias to the function name (override with the second arg):
Expr::count('*');                          // COUNT(*) AS `COUNT`
Expr::sum('amount');                       // SUM(`amount`) AS `SUM`
Expr::sum('amount', 'total_amount');       // SUM(`amount`) AS `total_amount`
Expr::avg('rating');                       // AVG(`rating`) AS `AVG`
Expr::max('price');                        // MAX(`price`) AS `MAX`
Expr::min('price');                        // MIN(`price`) AS `MIN`
```

Function arguments follow the standard normalisation: `ExpressionInterface` passes through, strings become column references, other scalars become literal values.

[↑ Back to top](#)

## CASE WHEN

Both standard forms are supported.

**Searched** form (each `when()` is a predicate):

```php
Expr::case()
    ->when(Expr::binary('amount', Binary::Gt, 100), Expr::val('high'))
    ->when(Expr::binary('amount', Binary::Gt, 10),  Expr::val('medium'))
    ->else(Expr::val('low'))
    ->as('bucket');
// CASE WHEN (`amount` > 100) THEN 'high' WHEN (`amount` > 10) THEN 'medium' ELSE 'low' END AS `bucket`
```

**Simple** form (subject compared against each `when()` value; scalars auto-wrap):

```php
Expr::case('status')
    ->when('active', 1)
    ->when('inactive', 0)
    ->else(-1);
// CASE `status` WHEN 'active' THEN 1 WHEN 'inactive' THEN 0 ELSE -1 END
```

[↑ Back to top](#)

## Subqueries and EXISTS

```php
$subquery = Select::create()->select('id')->from('orders')
    ->where(Expr::binary('user_id', Binary::Eq, Expr::ref('u.id')));

Expr::exists($subquery);
// EXISTS ((SELECT `id` FROM `orders` WHERE (`user_id` = `u`.`id`)))

Expr::subquery($subquery, 'orders_sq');
// (SELECT `id` FROM `orders` WHERE (`user_id` = `u`.`id`)) AS `orders_sq`
```

`exists()` is shorthand for wrapping the subquery in `EXISTS (...)`; `subquery()` is for using a SELECT as a scalar or table reference.

[↑ Back to top](#)

## Window functions (OVER)

```php
use Rak200\SqlBuilder\Common\Window;

$running = Expr::over(
    Expr::sum('amount'),
    Window::create()
        ->partitionBy('user_id')
        ->orderBy('paid_at')
        ->rows('BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW')
)->as('running_total');

Select::create()->select('user_id', $running)->from('payments');
```

`Window` exposes `partitionBy()`, `orderBy()`, plus `rows()` / `range()` / `groups()` shorthands. `frame()` lets you set any standards-compliant frame clause verbatim.

[↑ Back to top](#)

## GROUP BY extensions

Three SQL:1999 extensions for hierarchical / cube-style aggregation:

```php
// ROLLUP — nested totals
Expr::rollup('region', 'product');
// ROLLUP (`region`, `product`)

// CUBE — every combination of subtotals
Expr::cube('region', 'product');
// CUBE (`region`, `product`)

// GROUPING SETS — explicit list of grouping tuples
Expr::groupingSets(['region', 'product'], ['region'], []);
// GROUPING SETS ((`region`, `product`), (`region`), ())
```

The `[]` in `groupingSets()` emits the grand-total grouping `()`. Use them inside `Select::groupBy()`:

```php
Select::create()
    ->select('region', Expr::sum('amount'))
    ->from('sales')
    ->groupBy(Expr::rollup('region', 'product'));
```

[↑ Back to top](#)

## UUID wrappers

UUIDs need transformation on engines that store them differently (PostgreSQL has native `UUID`, MariaDB simulates with `BINARY(16)`). Two wrappers handle the round trip transparently:

```php
// Value-side: marks a value as destined for a UUID column.
// Postgres adds `::uuid` cast on literals; MariaDB wraps in UUID_TO_BIN(...).
Expr::uuid('aaaa-bbbb-cccc-dddd');
Expr::uuid(Expr::param('id'));   // also recurses through parameter placeholders

// Column-side: marks a column projection as UUID-typed.
// Postgres: pass-through. MariaDB: wraps in BIN_TO_UUID(...), hoisting the
// alias outside the call so the projected column name stays intact.
Expr::uuidColumn('id');
Expr::uuidColumn('id', 'user_id');
```

See [Dialects](dialects.md) for the exact per-vendor transformation.

[↑ Back to top](#)

## Prepared-statement parameters

```php
Expr::param(0);                  // positional placeholder, no default
Expr::param(0, 1);               // positional placeholder with default value 1
Expr::param('user_id');          // named placeholder :user_id
Expr::param('user_id', 1);       // named placeholder with default value 1
```

Used inside a builder, these become real placeholders only when you call `prepare(Dialect)` — outside that context they throw. See [Prepared statements](prepared-statements.md).

[↑ Back to top](#)

## Aliasing

Most expression types can carry an alias via `as()`, even after construction:

```php
Expr::col('email')->as('contact_email');
Expr::sum('amount')->as('total');
Expr::case('status')->when('active', 1)->else(0)->as('is_active');
```

`Reference\Column` and `Reference\Identifier` deliberately don't accept aliases — they exist for contexts where aliases aren't valid SQL.

[↑ Back to top](#)
