[← Docs index](README.md)

# MERGE

```php
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Dml\Merge;
use Rak200\SqlBuilder\Dml\Select;
```

SQL:2003 `MERGE` statement — combines INSERT / UPDATE / DELETE in one atomic write keyed on a join predicate. Accepted on the default dialect and on `Postgres15Dialect`; rejected on older Postgres and on every MariaDB dialect.

## Anatomy

```sql
MERGE INTO target [AS alias]
USING source [AS alias]
ON condition
WHEN MATCHED [AND pred] THEN UPDATE SET col = val, ...
WHEN MATCHED [AND pred] THEN DELETE
WHEN NOT MATCHED [AND pred] THEN INSERT (cols) VALUES (vals)
[RETURNING ...]
```

Branches are emitted in the order you add them.

[↑ Back to top](#)

## Basic upsert pattern

```php
Merge::create()
    ->into('target', 't')
    ->using('source', 's')
    ->on(Expr::binary('t.id', Binary::Eq, Expr::ref('s.id')))
    ->whenMatchedUpdate(['name' => Expr::ref('s.name')])
    ->whenNotMatchedInsert(['id', 'name'], [Expr::ref('s.id'), Expr::ref('s.name')]);
// MERGE INTO `target` AS `t`
//   USING `source` AS `s`
//   ON (`t`.`id` = `s`.`id`)
//   WHEN MATCHED THEN UPDATE SET `name` = `s`.`name`
//   WHEN NOT MATCHED THEN INSERT (`id`, `name`) VALUES (`s`.`id`, `s`.`name`)
```

[↑ Back to top](#)

## Using a subquery as the source

```php
$source = Select::create()
    ->select('id', 'name', 'amount')
    ->from('staging')
    ->where(Expr::binary('imported_at', Binary::Is, null));

Merge::create()
    ->into('orders', 'o')
    ->using($source, 's')
    ->on(Expr::binary('o.id', Binary::Eq, Expr::ref('s.id')))
    ->whenMatchedUpdate(['amount' => Expr::ref('s.amount')])
    ->whenNotMatchedInsert(
        ['id', 'name', 'amount'],
        [Expr::ref('s.id'), Expr::ref('s.name'), Expr::ref('s.amount')]
    );
```

[↑ Back to top](#)

## Branch helpers

```php
// Conditional update
->whenMatchedUpdate(
    ['name' => Expr::ref('s.name')],
    predicate: Expr::binary('s.priority', Binary::Gt, 5)
)

// Delete branch (with optional predicate)
->whenMatchedDelete()
->whenMatchedDelete(predicate: Expr::binary('s.deleted', Binary::Eq, true))

// Insert branch (with optional predicate)
->whenNotMatchedInsert(['id', 'name'], [Expr::ref('s.id'), Expr::ref('s.name')])
->whenNotMatchedInsert(
    ['id'], [Expr::ref('s.id')],
    predicate: Expr::binary('s.active', Binary::Eq, true)
)

// DO NOTHING — supported in both branches
->whenDoNothing(matched: true)                       // WHEN MATCHED THEN DO NOTHING
->whenDoNothing(matched: false, predicate: $expr)    // WHEN NOT MATCHED AND ... THEN DO NOTHING
```

[↑ Back to top](#)

## RETURNING

```php
Merge::create()
    ->into('users')
    ->using('staging')
    ->on(Expr::raw('TRUE'))
    ->whenMatchedDelete()
    ->returning('id');
// ... RETURNING `id`
```

PostgreSQL 17+ supports `RETURNING` on MERGE; on earlier engines the clause is emitted unconditionally and the database itself rejects it at parse time.

[↑ Back to top](#)

## Validation

Render-time checks fire from the renderer; calling `__toString()` / `toSql()` without one of these raises `InvalidArgumentException`:

- `into()` — target required
- `using()` — source required
- `on()` — join condition required
- at least one `whenMatchedXxx()` / `whenNotMatchedInsert()` / `whenDoNothing()`

`whenNotMatchedInsert()` also throws if the columns and values arrays have different lengths.

[↑ Back to top](#)

## Dialect support

| Dialect | Behaviour |
|---------|-----------|
| `DefaultDialect` | Renders the standard SQL:2003 form |
| `Postgres15Dialect` | Renders the standard form (PG 15+ supports MERGE) |
| `PostgresDialect` (base, < 15) | `UnsupportedFeatureException` — use `Insert::onConflict()` |
| `MariaDbDialect`, `MariaDb105Dialect` | `UnsupportedFeatureException` — neither engine implements MERGE |

For MariaDB / MySQL / Postgres < 15, use [`Insert::onConflict()`](insert.md#portable-upsert--onconflict) for upsert behaviour.

[↑ Back to top](#)
