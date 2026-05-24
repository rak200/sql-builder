[← Docs index](README.md)

# DDL — Schema definition

```php
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Common\Enum\ForeignKeyAction;
use Rak200\SqlBuilder\Common\Enum\CheckOption;
use Rak200\SqlBuilder\Ddl\Check;
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Ddl\ForeignKey;
use Rak200\SqlBuilder\Ddl\Index;
use Rak200\SqlBuilder\Ddl\PrimaryKey;
use Rak200\SqlBuilder\Ddl\Schema;
use Rak200\SqlBuilder\Ddl\Sequence;
use Rak200\SqlBuilder\Ddl\Table;
use Rak200\SqlBuilder\Ddl\UniqueKey;
use Rak200\SqlBuilder\Ddl\View;
```

Each DDL builder has factory methods for the statement variant it can emit:

| Builder | Factories | Notes |
|---------|-----------|-------|
| `Table` | `create()`, `alter()`, `drop()`, `truncate()` | CREATE / ALTER queue operations; DROP / TRUNCATE carry modifiers |
| `View` | `create()`, `drop()` | |
| `Index` | `create()`, `drop()` | DROP on MariaDB requires parent table — set via `->table()` |
| `Sequence` | `create()`, `alter()`, `drop()` | `alter()` enables `restart()` |
| `Schema` | `create()`, `alter()`, `drop()` | On MariaDB simulated as table-name prefixing — see [Dialects](dialects.md) |

Constraint builders (`PrimaryKey`, `UniqueKey`, `ForeignKey`, `Check`) have only `create()`; the constraint name is optional (omit for unnamed) and they're added to a `Table` via `->constraint()` / `->constraints()`.

## Contents

- [Data types](#data-types)
- [CREATE TABLE](#create-table)
- [ALTER TABLE](#alter-table)
- [DROP / TRUNCATE TABLE](#drop--truncate-table)
- [Constraints](#constraints)
- [VIEW](#view)
- [SEQUENCE](#sequence)
- [INDEX](#index)
- [SCHEMA](#schema)

[↑ Back to top](#)

## Data types

`DataType` enum cases — pass to `Column::create()`:

```
Char, VarChar, Text, MediumText, LongText
TinyInt, SmallInt, Int, BigInt
Decimal, Float, Double
Boolean
Date, Time, DateTime, Timestamp
Json, Uuid, Binary, VarBinary, Blob
```

`VarChar`, `Char`, `Binary`, `VarBinary` honour `->length(n)`. `Uuid` is rendered as native `UUID` on Postgres / default and as `BINARY(16)` on MariaDB — see [Dialects — UUID](dialects.md#uuid-simulation).

[↑ Back to top](#)

## CREATE TABLE

```php
$table = Table::create('users')
    ->column(Column::create('id', DataType::BigInt)->autoIncrement()->nullable(false))
    ->column(Column::create('email', DataType::VarChar)->length(255)->nullable(false))
    ->column(Column::create('created_at', DataType::DateTime)->default(Expr::raw('NOW()')))
    ->constraint(PrimaryKey::create()->columns(['id']))
    ->constraint(UniqueKey::create('uq_users_email')->columns(['email']));

echo $table;
// CREATE TABLE `users` (
//   `id` BIGINT NOT NULL AUTO_INCREMENT,
//   `email` VARCHAR(255) NOT NULL,
//   `created_at` DATETIME NULL DEFAULT NOW(),
//   PRIMARY KEY (`id`),
//   CONSTRAINT `uq_users_email` UNIQUE (`email`)
// )
```

Use `columns(...)` / `constraints(...)` to pass multiple at once.

### Column options

```php
Column::create('id', DataType::Int)
    ->nullable(false)         // NOT NULL
    ->autoIncrement()         // AUTO_INCREMENT (default true)
    ->primaryKey()            // inline PRIMARY KEY
    ->default(0);             // DEFAULT 0 (scalar) or DEFAULT NOW() (expression)

Column::create('email', DataType::VarChar)->length(255);
Column::create('balance', DataType::Decimal)->length(15);    // for some types length is precision/scale
Column::create('id', DataType::BigInt)->sequence($seq);      // DEFAULT = NEXTVAL('seq')
```

`default()` accepts scalars (wrapped in `Expr::val()`), expressions (`Expr::raw('NOW()')`), or sequence-bound expressions via `->sequence(Sequence)`.

[↑ Back to top](#)

## ALTER TABLE

```php
Table::alter('users')
    ->addColumn(Column::create('phone', DataType::VarChar)->length(20))
    ->modifyColumn(Column::create('email', DataType::VarChar)->length(320)->nullable(false))
    ->renameColumn('email_address', 'email')
    ->dropColumn('legacy_flag')
    ->addColumn(Column::create('updated_at', DataType::DateTime))
    ->constraint(ForeignKey::create('fk_users_role')->columns(['role_id'])->references('roles', ['id']))
    ->dropConstraint('uq_legacy')
    ->renameTo('app_users');
// ALTER TABLE `users`
//   ADD COLUMN `phone` VARCHAR(20) NULL,
//   MODIFY COLUMN `email` VARCHAR(320) NOT NULL,
//   ...
//   RENAME TO `app_users`
```

In ALTER mode, `column()` and `constraint()` are aliases for the `addColumn()` / `addConstraint()` variants. Calling ALTER-only methods (`dropColumn`, `renameColumn`, `renameTo`, `modifyColumn`, `dropConstraint`) on a `Table::create()` builder throws.

[↑ Back to top](#)

## DROP / TRUNCATE TABLE

```php
Table::drop('users')->ifExists()->cascade();
// DROP TABLE IF EXISTS `users` CASCADE

Table::truncate('users')->restartIdentity()->cascade();
// TRUNCATE TABLE `users` RESTART IDENTITY CASCADE
```

Modifiers: `ifExists()`, `cascade()` / `restrict()` (mutually exclusive), `restartIdentity()` / `continueIdentity()` (mutually exclusive, TRUNCATE only).

MariaDB rejects PostgreSQL-only TRUNCATE modifiers (`RESTART IDENTITY`, `CONTINUE IDENTITY`, `CASCADE`, `RESTRICT`) with `UnsupportedFeatureException`.

[↑ Back to top](#)

## Constraints

### PrimaryKey

```php
PrimaryKey::create()->columns(['id']);
// PRIMARY KEY (`id`)

PrimaryKey::create('pk_users_id')->columns(['id']);
// CONSTRAINT `pk_users_id` PRIMARY KEY (`id`)

PrimaryKey::create()->columns(['tenant_id', 'id']);  // composite
// PRIMARY KEY (`tenant_id`, `id`)
```

### UniqueKey

```php
UniqueKey::create('uq_users_email')->columns(['email']);
// CONSTRAINT `uq_users_email` UNIQUE (`email`)

// Postgres 15+: NULLS [NOT] DISTINCT
UniqueKey::create('uq_users_email')->columns(['email'])->nullsNotDistinct();
// CONSTRAINT `uq_users_email` UNIQUE NULLS NOT DISTINCT (`email`)
```

`nullsDistinct()` / `nullsNotDistinct()` are accepted on the default dialect and on `Postgres15Dialect`; rejected on base Postgres (<15) and on every MariaDB dialect.

### ForeignKey

```php
ForeignKey::create('fk_users_role_id')
    ->columns(['role_id'])
    ->references('roles', ['id'])
    ->onDelete(ForeignKeyAction::CASCADE)
    ->onUpdate(ForeignKeyAction::RESTRICT);
// CONSTRAINT `fk_users_role_id` FOREIGN KEY (`role_id`)
//   REFERENCES `roles` (`id`) ON DELETE CASCADE ON UPDATE RESTRICT
```

`ForeignKeyAction` cases: `Cascade`, `Restrict`, `SetNull`, `SetDefault`, `NoAction`.

### Check

```php
Check::create('chk_users_age')->condition(Expr::binary('age', Binary::Ge, 0));
// CONSTRAINT `chk_users_age` CHECK ((`age` >= 0))

Check::create()->condition('LENGTH(email) > 5');
// CHECK (LENGTH(email) > 5)
```

Accepts a string (passed through verbatim, inside the `CHECK (...)`) or any `ExpressionInterface`.

[↑ Back to top](#)

## VIEW

```php
$body = Select::create()
    ->select('id', 'name')
    ->from('users')
    ->where(Expr::binary('active', Binary::Eq, 1));

View::create('active_users')
    ->orReplace()
    ->query($body);
// CREATE OR REPLACE VIEW `active_users` AS SELECT ...

View::create('active_users')
    ->ifNotExists()
    ->columns('id', 'name')                       // explicit column-name list
    ->query($body)
    ->withCheckOption(CheckOption::CASCADED);
// CREATE VIEW IF NOT EXISTS `active_users` (`id`, `name`) AS SELECT ... WITH CASCADED CHECK OPTION

View::drop('active_users')->ifExists();
// DROP VIEW IF EXISTS `active_users`
```

`orReplace()` and `ifNotExists()` are mutually exclusive.

[↑ Back to top](#)

## SEQUENCE

```php
Sequence::create('order_id_seq')
    ->startWith(1000)
    ->incrementBy(1)
    ->minValue(1000)
    ->noMaxValue()
    ->cache(20)
    ->cycle();
// CREATE SEQUENCE `order_id_seq` START WITH 1000 INCREMENT BY 1 MINVALUE 1000 NO MAXVALUE CACHE 20 CYCLE

// Use as a column default
$seq = Sequence::create('order_id_seq');
Table::create('orders')
    ->column(Column::create('id', DataType::BigInt)->nullable(false)->sequence($seq));

// ALTER (enables restart())
Sequence::alter('order_id_seq')->restart(5000);
// ALTER SEQUENCE `order_id_seq` RESTART WITH 5000
Sequence::alter('order_id_seq')->restart();  // restart with original START WITH

Sequence::drop('order_id_seq')->ifExists()->cascade();
// DROP SEQUENCE IF EXISTS `order_id_seq` CASCADE
```

`nextVal()` returns a `Raw` expression suitable as a DEFAULT or in any other expression context: `$seq->nextVal()` → `NEXTVAL('order_id_seq')`.

[↑ Back to top](#)

## INDEX

```php
Index::create('idx_users_email')
    ->table('users')
    ->columns(['email']);
// CREATE INDEX `idx_users_email` ON `users` (`email`)

Index::create('idx_users_email')
    ->table('users')
    ->columns(['email'])
    ->unique();
// CREATE UNIQUE INDEX `idx_users_email` ON `users` (`email`)

Index::drop('idx_users_email')->ifExists()->cascade();
// DROP INDEX IF EXISTS `idx_users_email` CASCADE
```

**MariaDB** requires `DROP INDEX` to name the parent table:

```php
Index::drop('idx_users_email')->table('users');
// MariaDB:  DROP INDEX `idx_users_email` ON `users`
```

Without `->table()`, MariaDB's renderer throws. MariaDB also rejects `cascade()` on `DROP INDEX`.

[↑ Back to top](#)

## SCHEMA

```php
Schema::create('reporting')->ifNotExists()->authorization('analytics');
// CREATE SCHEMA IF NOT EXISTS `reporting` AUTHORIZATION `analytics`

Schema::drop('legacy')->ifExists()->cascade();
// DROP SCHEMA IF EXISTS `legacy` CASCADE

Schema::alter('old_name')->renameTo('new_name');
// ALTER SCHEMA `old_name` RENAME TO `new_name`
```

MariaDB has no schema namespace independent of the database, so the `MariaDbDialect` rejects `CREATE/DROP/ALTER SCHEMA` with `UnsupportedFeatureException`. **Table references** through a `schema.table` identifier are silently flattened to `schema_table` on MariaDB — see [Dialects — Schema simulation](dialects.md#schema-simulation-mariadb).

[↑ Back to top](#)
