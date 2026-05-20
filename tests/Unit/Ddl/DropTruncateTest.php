<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Ddl\Index;
use Rak200\SqlBuilder\Ddl\Sequence;
use Rak200\SqlBuilder\Ddl\Table;
use Rak200\SqlBuilder\Ddl\View;
use Rak200\SqlBuilder\Dml\Select;

/**
 * Covers DROP / TRUNCATE behaviour on the default (permissive) dialect
 * for each DDL builder.
 */
final class DropTruncateTest extends TestCase {

    // -------------------------------------------------------------------
    // DROP TABLE
    // -------------------------------------------------------------------

    public function testDropTable(): void {
        $this->assertSame('DROP TABLE `users`', (string) Table::drop('users'));
    }

    public function testDropTableIfExists(): void {
        $this->assertSame(
            'DROP TABLE IF EXISTS `users`',
            (string) Table::drop('users')->ifExists()
        );
    }

    public function testDropTableCascade(): void {
        $this->assertSame(
            'DROP TABLE IF EXISTS `users` CASCADE',
            (string) Table::drop('users')->ifExists()->cascade()
        );
    }

    public function testDropTableRestrict(): void {
        $this->assertSame(
            'DROP TABLE `users` RESTRICT',
            (string) Table::drop('users')->restrict()
        );
    }

    public function testCascadeAndRestrictAreMutuallyExclusiveOnDropTable(): void {
        $sql = (string) Table::drop('users')->cascade()->restrict();
        $this->assertSame('DROP TABLE `users` RESTRICT', $sql);
    }

    // -------------------------------------------------------------------
    // TRUNCATE TABLE
    // -------------------------------------------------------------------

    public function testTruncateTable(): void {
        $this->assertSame('TRUNCATE TABLE `users`', (string) Table::truncate('users'));
    }

    public function testTruncateRestartIdentity(): void {
        $this->assertSame(
            'TRUNCATE TABLE `users` RESTART IDENTITY',
            (string) Table::truncate('users')->restartIdentity()
        );
    }

    public function testTruncateContinueIdentity(): void {
        $this->assertSame(
            'TRUNCATE TABLE `users` CONTINUE IDENTITY',
            (string) Table::truncate('users')->continueIdentity()
        );
    }

    public function testTruncateRestartAndContinueAreMutuallyExclusive(): void {
        $sql = (string) Table::truncate('users')->restartIdentity()->continueIdentity();
        $this->assertSame('TRUNCATE TABLE `users` CONTINUE IDENTITY', $sql);
    }

    public function testTruncateCascadeAndIdentityCombined(): void {
        $this->assertSame(
            'TRUNCATE TABLE `users` RESTART IDENTITY CASCADE',
            (string) Table::truncate('users')->restartIdentity()->cascade()
        );
    }

    public function testAlterMethodOnDropModeThrows(): void {
        $this->expectException(InvalidArgumentException::class);
        Table::drop('users')->addColumn(\Rak200\SqlBuilder\Ddl\Column::create(
            'x',
            \Rak200\SqlBuilder\Ddl\Enum\DataType::Int
        ));
    }

    // -------------------------------------------------------------------
    // DROP VIEW
    // -------------------------------------------------------------------

    public function testDropView(): void {
        $this->assertSame('DROP VIEW `active_users`', (string) View::drop('active_users'));
    }

    public function testDropViewIfExistsCascade(): void {
        $this->assertSame(
            'DROP VIEW IF EXISTS `active_users` CASCADE',
            (string) View::drop('active_users')->ifExists()->cascade()
        );
    }

    public function testDropViewRestrict(): void {
        $this->assertSame(
            'DROP VIEW `active_users` RESTRICT',
            (string) View::drop('active_users')->restrict()
        );
    }

    public function testViewInCreateModeStillRequiresQuery(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) View::create('x');
    }

    // -------------------------------------------------------------------
    // DROP INDEX (default dialect: Postgres-style)
    // -------------------------------------------------------------------

    public function testDropIndex(): void {
        $this->assertSame('DROP INDEX "idx_users_email"', (string) Index::drop('idx_users_email'));
    }

    public function testDropIndexIfExistsCascade(): void {
        $this->assertSame(
            'DROP INDEX IF EXISTS "idx_users_email" CASCADE',
            (string) Index::drop('idx_users_email')->ifExists()->cascade()
        );
    }

    public function testDropIndexIgnoresTableOnDefault(): void {
        // On the default (Postgres-style) dialect, the parent table is not
        // emitted on DROP INDEX even when set on the builder.
        $this->assertSame(
            'DROP INDEX "idx"',
            (string) Index::drop('idx')->table('users')
        );
    }

    // -------------------------------------------------------------------
    // DROP SEQUENCE
    // -------------------------------------------------------------------

    public function testDropSequence(): void {
        $this->assertSame('DROP SEQUENCE "`order_id_seq`"', (string) Sequence::drop('order_id_seq'));
    }

    public function testDropSequenceIfExistsCascade(): void {
        $this->assertSame(
            'DROP SEQUENCE IF EXISTS "`order_id_seq`" CASCADE',
            (string) Sequence::drop('order_id_seq')->ifExists()->cascade()
        );
    }

    public function testDropSequenceRestrict(): void {
        $this->assertSame(
            'DROP SEQUENCE "`order_id_seq`" RESTRICT',
            (string) Sequence::drop('order_id_seq')->restrict()
        );
    }

    public function testRestartStillRejectedOutsideAlter(): void {
        $this->expectException(InvalidArgumentException::class);
        Sequence::drop('x')->restart(1);
    }

    // -------------------------------------------------------------------
    // CREATE-mode tests still work after the mode refactor
    // -------------------------------------------------------------------

    public function testCreateViewStillRenders(): void {
        $sql = (string) View::create('v')->query(Select::create()->select('1'));
        $this->assertStringStartsWith('CREATE VIEW ', $sql);
    }

    public function testCreateSequenceStillRenders(): void {
        $sql = (string) Sequence::create('seq')->startWith(10);
        $this->assertStringContainsString('CREATE SEQUENCE ', $sql);
        $this->assertStringContainsString('START WITH 10', $sql);
    }
}
