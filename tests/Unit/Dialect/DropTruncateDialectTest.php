<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Ddl\Index;
use Rak200\SqlBuilder\Ddl\Sequence;
use Rak200\SqlBuilder\Ddl\Table;
use Rak200\SqlBuilder\Ddl\View;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;

/**
 * Dialect-specific behaviour for DROP / TRUNCATE statements.
 */
final class DropTruncateDialectTest extends TestCase {

    // -------------------------------------------------------------------
    // PostgreSQL
    // -------------------------------------------------------------------

    public function testPostgresDropTableCascade(): void {
        $sql = Table::drop('users')->ifExists()->cascade()->toSql(new PostgresDialect());
        $this->assertSame('DROP TABLE IF EXISTS "users" CASCADE', $sql);
    }

    public function testPostgresTruncateRestartIdentityCascade(): void {
        $sql = Table::truncate('users')->restartIdentity()->cascade()->toSql(new PostgresDialect());
        $this->assertSame('TRUNCATE TABLE "users" RESTART IDENTITY CASCADE', $sql);
    }

    public function testPostgresDropIndexCascade(): void {
        $sql = Index::drop('idx_users_email')->ifExists()->cascade()->toSql(new PostgresDialect());
        $this->assertSame('DROP INDEX IF EXISTS "idx_users_email" CASCADE', $sql);
    }

    public function testPostgresDropSequenceCascade(): void {
        $sql = Sequence::drop('order_id_seq')->ifExists()->cascade()->toSql(new PostgresDialect());
        $this->assertSame('DROP SEQUENCE IF EXISTS "order_id_seq" CASCADE', $sql);
    }

    public function testPostgresDropView(): void {
        $sql = View::drop('active_users')->ifExists()->cascade()->toSql(new PostgresDialect());
        $this->assertSame('DROP VIEW IF EXISTS "active_users" CASCADE', $sql);
    }

    // -------------------------------------------------------------------
    // MariaDB — DROP TABLE / DROP VIEW / DROP SEQUENCE follow the default
    // -------------------------------------------------------------------

    public function testMariaDbDropTableCascadeStillEmitted(): void {
        // MariaDB accepts CASCADE / RESTRICT on DROP TABLE as no-op syntax.
        $sql = Table::drop('users')->ifExists()->cascade()->toSql(new MariaDbDialect());
        $this->assertSame('DROP TABLE IF EXISTS `users` CASCADE', $sql);
    }

    public function testMariaDbDropViewFollowsDefault(): void {
        $sql = View::drop('active_users')->ifExists()->toSql(new MariaDbDialect());
        $this->assertSame('DROP VIEW IF EXISTS `active_users`', $sql);
    }

    public function testMariaDbFlattensDropTableSchemaPrefix(): void {
        $sql = Table::drop('reporting.events')->ifExists()->toSql(new MariaDbDialect());
        $this->assertSame('DROP TABLE IF EXISTS `reporting_events`', $sql);
    }

    // -------------------------------------------------------------------
    // MariaDB — DROP INDEX requires `ON table`
    // -------------------------------------------------------------------

    public function testMariaDbDropIndexRequiresTable(): void {
        $this->expectException(UnsupportedFeatureException::class);
        Index::drop('idx')->toSql(new MariaDbDialect());
    }

    public function testMariaDbDropIndexEmitsOnTable(): void {
        $sql = Index::drop('idx_users_email')->table('users')->toSql(new MariaDbDialect());
        $this->assertSame('DROP INDEX `idx_users_email` ON `users`', $sql);
    }

    public function testMariaDbDropIndexIfExists(): void {
        $sql = Index::drop('idx_users_email')->table('users')->ifExists()->toSql(new MariaDbDialect());
        $this->assertSame('DROP INDEX IF EXISTS `idx_users_email` ON `users`', $sql);
    }

    public function testMariaDbDropIndexRejectsCascade(): void {
        $this->expectException(UnsupportedFeatureException::class);
        Index::drop('idx')->table('users')->cascade()->toSql(new MariaDbDialect());
    }

    public function testMariaDbDropIndexFlattensTablePrefix(): void {
        $sql = Index::drop('idx')->table('reporting.events')->toSql(new MariaDbDialect());
        $this->assertSame('DROP INDEX `idx` ON `reporting_events`', $sql);
    }

    // -------------------------------------------------------------------
    // MariaDB — TRUNCATE rejects PostgreSQL-only modifiers
    // -------------------------------------------------------------------

    public function testMariaDbTruncateBare(): void {
        $sql = Table::truncate('users')->toSql(new MariaDbDialect());
        $this->assertSame('TRUNCATE TABLE `users`', $sql);
    }

    public function testMariaDbTruncateRejectsRestartIdentity(): void {
        $this->expectException(UnsupportedFeatureException::class);
        Table::truncate('users')->restartIdentity()->toSql(new MariaDbDialect());
    }

    public function testMariaDbTruncateRejectsContinueIdentity(): void {
        $this->expectException(UnsupportedFeatureException::class);
        Table::truncate('users')->continueIdentity()->toSql(new MariaDbDialect());
    }

    public function testMariaDbTruncateRejectsCascade(): void {
        $this->expectException(UnsupportedFeatureException::class);
        Table::truncate('users')->cascade()->toSql(new MariaDbDialect());
    }

    public function testMariaDbTruncateRejectsRestrict(): void {
        $this->expectException(UnsupportedFeatureException::class);
        Table::truncate('users')->restrict()->toSql(new MariaDbDialect());
    }

    public function testMariaDbTruncateFlattensSchemaPrefix(): void {
        $sql = Table::truncate('reporting.events')->toSql(new MariaDbDialect());
        $this->assertSame('TRUNCATE TABLE `reporting_events`', $sql);
    }
}
