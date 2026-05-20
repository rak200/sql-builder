<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Enum\ForeignKeyAction;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Ddl\ForeignKey;
use Rak200\SqlBuilder\Ddl\Index;
use Rak200\SqlBuilder\Ddl\Schema;
use Rak200\SqlBuilder\Ddl\Sequence;
use Rak200\SqlBuilder\Ddl\Table;
use Rak200\SqlBuilder\Ddl\View;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;
use Rak200\SqlBuilder\Dml\Delete;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Update;

/**
 * Covers Schema DDL across dialects and the MariaDB schema-as-table-prefix
 * simulation. The default and Postgres dialects render schemas literally;
 * MariaDB refuses to emit any schema-level DDL and instead flattens
 * `schema.table` references to `schema_table` at quoting time.
 */
final class SchemaDialectTest extends TestCase {

    // -------------------------------------------------------------------
    // Default dialect (backticks)
    // -------------------------------------------------------------------

    public function testCreateSchemaUnderDefault(): void {
        $this->assertSame('CREATE SCHEMA `reporting`', (string) Schema::create('reporting'));
    }

    // -------------------------------------------------------------------
    // PostgreSQL: schemas are first-class; identifiers use double quotes
    // -------------------------------------------------------------------

    public function testPostgresRendersCreateSchema(): void {
        $sql = Schema::create('reporting')->ifNotExists()->authorization('analytics')->toSql(new PostgresDialect());
        $this->assertSame('CREATE SCHEMA IF NOT EXISTS "reporting" AUTHORIZATION "analytics"', $sql);
    }

    public function testPostgresRendersDropSchemaCascade(): void {
        $sql = Schema::drop('legacy')->ifExists()->cascade()->toSql(new PostgresDialect());
        $this->assertSame('DROP SCHEMA IF EXISTS "legacy" CASCADE', $sql);
    }

    public function testPostgresRendersAlterRename(): void {
        $sql = Schema::alter('old')->renameTo('new')->toSql(new PostgresDialect());
        $this->assertSame('ALTER SCHEMA "old" RENAME TO "new"', $sql);
    }

    public function testPostgresPreservesQualifiedTableReference(): void {
        $sql = Select::create()
            ->select('id')
            ->from('reporting.events')
            ->toSql(new PostgresDialect());
        $this->assertSame('SELECT "id" FROM "reporting"."events"', $sql);
    }

    public function testPostgresPreservesQualifiedColumnReference(): void {
        $sql = Select::create()
            ->select(Expression::ref('reporting.events.id'))
            ->from('reporting.events')
            ->toSql(new PostgresDialect());
        $this->assertSame(
            'SELECT "reporting"."events"."id" FROM "reporting"."events"',
            $sql
        );
    }

    // -------------------------------------------------------------------
    // MariaDB: schema-level DDL is refused
    // -------------------------------------------------------------------

    public function testMariaDbRejectsCreateSchema(): void {
        $this->expectException(UnsupportedFeatureException::class);
        Schema::create('reporting')->toSql(new MariaDbDialect());
    }

    public function testMariaDbRejectsDropSchema(): void {
        $this->expectException(UnsupportedFeatureException::class);
        Schema::drop('legacy')->toSql(new MariaDbDialect());
    }

    public function testMariaDbRejectsAlterSchema(): void {
        $this->expectException(UnsupportedFeatureException::class);
        Schema::alter('old')->renameTo('new')->toSql(new MariaDbDialect());
    }

    // -------------------------------------------------------------------
    // MariaDB: schema.table is flattened to schema_table everywhere
    // -------------------------------------------------------------------

    public function testMariaDbFlattensFromClause(): void {
        $sql = Select::create()
            ->select('id')
            ->from('reporting.events')
            ->toSql(new MariaDbDialect());
        $this->assertSame('SELECT `id` FROM `reporting_events`', $sql);
    }

    public function testMariaDbFlattensFromClauseWithAlias(): void {
        $sql = Select::create()
            ->select('e.id')
            ->from('reporting.events', 'e')
            ->toSql(new MariaDbDialect());
        $this->assertSame('SELECT `e`.`id` FROM `reporting_events` AS `e`', $sql);
    }

    public function testMariaDbFlattensJoinTarget(): void {
        $on = Expression::binary('e.user_id', BinaryOperator::Eq, Expression::ref('u.id'));
        $sql = Select::create()
            ->from('reporting.events', 'e')
            ->join('auth.users', 'u', $on)
            ->toSql(new MariaDbDialect());
        $this->assertStringContainsString('FROM `reporting_events` AS `e`', $sql);
        $this->assertStringContainsString('INNER JOIN `auth_users` AS `u`', $sql);
    }

    public function testMariaDbFlattensThreePartColumnReference(): void {
        $sql = Select::create()
            ->select(Expression::ref('reporting.events.id'))
            ->from('reporting.events')
            ->toSql(new MariaDbDialect());
        $this->assertSame(
            'SELECT `reporting_events`.`id` FROM `reporting_events`',
            $sql
        );
    }

    public function testMariaDbLeavesPlainTableColumnRefAlone(): void {
        $sql = Select::create()
            ->select(Expression::ref('events.id'))
            ->from('events')
            ->toSql(new MariaDbDialect());
        $this->assertSame('SELECT `events`.`id` FROM `events`', $sql);
    }

    public function testMariaDbFlattensCreateTableName(): void {
        $sql = Table::create('reporting.events')
            ->column(Column::create('id', DataType::Int)->nullable(false))
            ->toSql(new MariaDbDialect());
        $this->assertStringContainsString('CREATE TABLE "`reporting_events`"', $sql);
    }

    public function testMariaDbFlattensAlterTableAndRename(): void {
        $sql = Table::alter('reporting.events')->renameTo('reporting.activities')->toSql(new MariaDbDialect());
        $this->assertSame(
            'ALTER TABLE "`reporting_events`" RENAME TO `reporting_activities`',
            $sql
        );
    }

    public function testMariaDbFlattensInsertTarget(): void {
        $sql = Insert::create()
            ->into('reporting.events')
            ->columns('id')
            ->values(1)
            ->toSql(new MariaDbDialect());
        $this->assertSame('INSERT INTO `reporting_events` (`id`) VALUES (1)', $sql);
    }

    public function testMariaDbFlattensUpdateTarget(): void {
        $sql = Update::create()
            ->table('reporting.events')
            ->set('processed', true)
            ->toSql(new MariaDbDialect());
        $this->assertSame('UPDATE `reporting_events` SET `processed` = TRUE', $sql);
    }

    public function testMariaDbFlattensDeleteTarget(): void {
        $sql = Delete::create()
            ->from('reporting.events')
            ->where(Expression::binary('id', BinaryOperator::Eq, 1))
            ->toSql(new MariaDbDialect());
        $this->assertSame('DELETE FROM `reporting_events` WHERE (`id` = 1)', $sql);
    }

    public function testMariaDbFlattensIndexTarget(): void {
        $sql = Index::create('idx_user')->table('auth.users')->columns(['user_id'])->toSql(new MariaDbDialect());
        $this->assertStringContainsString('ON "auth_users"', $sql);
    }

    public function testMariaDbFlattensForeignKeyReference(): void {
        $fk = ForeignKey::create('fk_user')
            ->columns(['user_id'])
            ->references('auth.users', ['id'])
            ->onDelete(ForeignKeyAction::CASCADE);
        $this->assertStringContainsString('REFERENCES "auth_users"', $fk->toSql(new MariaDbDialect()));
    }

    public function testMariaDbFlattensViewName(): void {
        $sql = View::create('reporting.active_users')
            ->query(Select::create()->select('id')->from('users'))
            ->toSql(new MariaDbDialect());
        $this->assertStringContainsString('VIEW "`reporting_active_users`"', $sql);
    }

    public function testMariaDbFlattensSequenceName(): void {
        $sql = Sequence::create('reporting.id_seq')->toSql(new MariaDbDialect());
        $this->assertSame('CREATE SEQUENCE "`reporting_id_seq`"', $sql);
    }
}
