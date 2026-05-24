# SELECT

```php
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Common\Enum\Sort\Direction;
use Rak200\SqlBuilder\Common\Enum\Sort\Nulls;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Set;
```

`Select::create()` returns a fluent builder. Every method returns `static` so you can chain freely. The builder is a thin state container — it does no rendering until you call `__toString()` or `toSql($dialect)`.

## Projection

```php
Select::create()
    ->select('id', 'name', 'email')
    ->from('users');
// SELECT `id`, `name`, `email` FROM `users`

// Pass expressions directly for aliases, raw SQL, aggregates:
Select::create()
    ->select(
        Expr::col('email', 'contact'),
        Expr::raw('UPPER(name)'),
        Expr::count('*')->as('total'),
    )
    ->from('users');
```

`distinct()` toggles the `DISTINCT` modifier:

```php
Select::create()->distinct()->select('email')->from('users');
// SELECT DISTINCT `email` FROM `users`
```

If you omit `select()` entirely you get `SELECT *`.

[↑ Back to top](#)

## FROM

```php
Select::create()->from('users');                 // FROM `users`
Select::create()->from('users', 'u');            // FROM `users` AS `u`

// Subquery in FROM — alias is required.
$sub = Select::create()->select('id')->from('orders');
Select::create()->from($sub, 'recent_orders');
// FROM (SELECT `id` FROM `orders`) AS `recent_orders`
```

[↑ Back to top](#)

## JOIN family

Every join type has a dedicated method. All accept either a table name or a subquery, an optional alias, and an `ExpressionInterface` ON predicate.

```php
$on = Expr::binary('u.role_id', Binary::Eq, Expr::ref('r.id'));

Select::create()->from('users', 'u')->join('roles', 'r', $on);          // INNER
Select::create()->from('users', 'u')->leftJoin('roles', 'r', $on);
Select::create()->from('users', 'u')->rightJoin('roles', 'r', $on);
Select::create()->from('users', 'u')->fullJoin('roles', 'r', $on);
Select::create()->from('users', 'u')->crossJoin('roles', 'r');           // no ON
```

**NATURAL** joins auto-pair columns of the same name:

```php
Select::create()->from('users')->naturalJoin('roles');
Select::create()->from('users')->naturalLeftJoin('roles');
Select::create()->from('users')->naturalRightJoin('roles');
Select::create()->from('users')->naturalFullJoin('roles');
```

**USING** joins take a column list (each becomes a bare identifier):

```php
Select::create()->from('users', 'u')->joinUsing('accounts', ['account_id'], 'a');
// FROM `users` AS `u` INNER JOIN `accounts` AS `a` USING (`account_id`)
```

Variants: `leftJoinUsing`, `rightJoinUsing`, `fullJoinUsing`.

[↑ Back to top](#)

## LATERAL joins

`LATERAL` lets the right-hand subquery reference columns from earlier FROM items — common with set-returning functions and correlated subqueries.

```php
$recentOrders = Select::create()
    ->select('order_id')
    ->from('orders')
    ->where(Expr::binary('orders.user_id', Binary::Eq, Expr::ref('u.id')))
    ->limit(5);

Select::create()
    ->select('u.id', 'recent.order_id')
    ->from('users', 'u')
    ->lateralJoin($recentOrders, 'recent', Expr::raw('TRUE'));
// FROM `users` AS `u` INNER JOIN LATERAL (SELECT ...) AS `recent` ON TRUE
```

Variants: `leftLateralJoin()`, `crossLateralJoin()` (no `ON` needed). Supported on PostgreSQL and MariaDB 10.2+.

[↑ Back to top](#)

## WHERE

```php
Select::create()
    ->from('users')
    ->where(Expr::binary('active', Binary::Eq, 1));
// WHERE (`active` = 1)

// Compose with andWhere() / orWhere():
Select::create()
    ->from('users')
    ->where(Expr::binary('active', Binary::Eq, 1))
    ->andWhere(Expr::binary('role', Binary::Eq, 'admin'))
    ->orWhere(Expr::binary('email', Binary::Like, '%@example.com'));
// WHERE (((`active` = 1) AND (`role` = 'admin')) OR (`email` LIKE '%@example.com'))
```

`andWhere()` is an alias for `where()`; both AND-compose. `orWhere()` OR-composes. For more control over grouping, build the predicate explicitly with `Expr::and()` / `Expr::or()` and pass it to `where()` once.

[↑ Back to top](#)

## GROUP BY (with extensions)

```php
Select::create()
    ->select('region', Expr::count('*')->as('total'))
    ->from('sales')
    ->groupBy('region');
// GROUP BY `region`

// With grouping extensions
Select::create()
    ->select('region', 'product', Expr::sum('amount'))
    ->from('sales')
    ->groupBy(Expr::rollup('region', 'product'));
// GROUP BY ROLLUP (`region`, `product`)

Select::create()
    ->select('region', 'product', Expr::sum('amount'))
    ->from('sales')
    ->groupBy(Expr::groupingSets(['region', 'product'], ['region'], []));
// GROUP BY GROUPING SETS ((`region`, `product`), (`region`), ())
```

You can mix plain columns and grouping extensions in one call: `->groupBy('region', Expr::rollup('product'))`.

[↑ Back to top](#)

## HAVING

```php
Select::create()
    ->select('user_id', Expr::count('*')->as('orders'))
    ->from('orders')
    ->groupBy('user_id')
    ->having(Expr::binary(Expr::raw('COUNT(*)'), Binary::Gt, 5));
// HAVING (COUNT(*) > 5)
```

Successive `having()` calls AND-compose.

[↑ Back to top](#)

## ORDER BY

```php
Select::create()
    ->from('users')
    ->orderBy('name', Direction::ASC)
    ->orderBy('created_at', Direction::DESC, Nulls::LAST);
// ORDER BY `name` ASC, `created_at` DESC NULLS LAST
```

`Direction` cases: `ASC`, `DESC`. `Nulls` cases: `FIRST`, `LAST` (optional).

[↑ Back to top](#)

## LIMIT and OFFSET

```php
Select::create()->from('users')->limit(20)->offset(40);
// LIMIT 20 OFFSET 40
```

Both reject negative values with `InvalidArgumentException`.

[↑ Back to top](#)

## Common Table Expressions (WITH)

```php
$totals = Select::create()
    ->select('user_id', Expr::count('*')->as('total'))
    ->from('orders')
    ->groupBy('user_id');

Select::create()
    ->with('order_totals', $totals)
    ->select('user_id')
    ->from('order_totals')
    ->where(Expr::binary('total', Binary::Gt, 10));
// WITH `order_totals` AS (SELECT ...) SELECT `user_id` FROM `order_totals` WHERE ...
```

Multiple `with()` calls produce a comma-separated `WITH` list. Pass an explicit column-name list as the third argument when you want to override the body's projection names: `->with('t', $query, ['a', 'b'])`.

**Recursive** CTEs use `withRecursive()` and typically a `Set::union()` body combining base case + recursive step:

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

A single recursive CTE in the list promotes the whole `WITH` clause to `WITH RECURSIVE`.

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

See [Expressions — Window functions](expressions.md#window-functions-over) for the full `Window` API.

[↑ Back to top](#)

## Subqueries

Anywhere `string|Select` is accepted (`from()`, joins) you can pass a `Select`. For scalar subqueries in projections or predicates use `Expr::subquery()` or `Expr::exists()`.

```php
$activeIds = Select::create()->select('id')->from('users')
    ->where(Expr::binary('active', Binary::Eq, 1));

Select::create()
    ->select('order_id')
    ->from('orders')
    ->where(Expr::binary('user_id', Binary::In, Expr::subquery($activeIds)));
```

[↑ Back to top](#)

## Rendering

```php
$query = Select::create()->select('id')->from('users');

(string) $query;                                    // default dialect
$query->toSql(new PostgresDialect());               // double quotes, etc.
$query->prepare(new DefaultDialect());              // PreparedStatement
```

[↑ Back to top](#)
