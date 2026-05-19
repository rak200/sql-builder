<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Ddl\PrimaryKey;
use Rak200\SqlBuilder\Ddl\Table;

final class TableTest extends TestCase {

    public function testCreateTableEmitsKeywordAndQuotedName(): void {
        $sql = (string) Table::create('users');

        $this->assertStringStartsWith('CREATE TABLE ', $sql);
        $this->assertStringContainsString('users', $sql);
    }

    public function testCreateTableIncludesColumnDefinitions(): void {
        $sql = (string) Table::create('users')
            ->column(Column::create('id', DataType::BigInt))
            ->column(Column::create('name', DataType::VarChar)->length(100));

        $this->assertStringContainsString('`id` BIGINT NULL', $sql);
        $this->assertStringContainsString('`name` VARCHAR(100) NULL', $sql);
    }

    public function testColumnsPluralAcceptsMultipleColumns(): void {
        $sql = (string) Table::create('t')
            ->columns(
                Column::create('a', DataType::Int),
                Column::create('b', DataType::Int)
            );

        $this->assertStringContainsString('`a` INT NULL', $sql);
        $this->assertStringContainsString('`b` INT NULL', $sql);
    }

    public function testCreateTableIncludesConstraint(): void {
        $sql = (string) Table::create('users')
            ->column(Column::create('id', DataType::Int))
            ->constraint(PrimaryKey::create()->columns(['id']));

        $this->assertStringContainsString('PRIMARY KEY ("id")', $sql);
    }

    public function testAlterAddColumn(): void {
        $sql = (string) Table::alter('users')->addColumn(Column::create('age', DataType::Int));

        $this->assertStringStartsWith('ALTER TABLE ', $sql);
        $this->assertStringContainsString('ADD COLUMN `age` INT NULL', $sql);
    }

    public function testAlterDropColumn(): void {
        $sql = (string) Table::alter('users')->dropColumn('legacy');

        $this->assertStringContainsString('DROP COLUMN', $sql);
        $this->assertStringContainsString('legacy', $sql);
    }

    public function testAlterRenameColumn(): void {
        $sql = (string) Table::alter('users')->renameColumn('email', 'email_address');

        $this->assertStringContainsString('RENAME COLUMN', $sql);
        $this->assertStringContainsString('email', $sql);
        $this->assertStringContainsString('email_address', $sql);
    }

    public function testAlterRenameTo(): void {
        $sql = (string) Table::alter('old')->renameTo('new');

        $this->assertStringContainsString('RENAME TO', $sql);
        $this->assertStringContainsString('new', $sql);
    }

    public function testAlterModifyColumn(): void {
        $sql = (string) Table::alter('users')
            ->modifyColumn(Column::create('email', DataType::VarChar)->length(320));

        $this->assertStringContainsString('MODIFY COLUMN `email` VARCHAR(320) NULL', $sql);
    }

    public function testAlterDropConstraint(): void {
        $sql = (string) Table::alter('users')->dropConstraint('pk_users');

        $this->assertStringContainsString('DROP CONSTRAINT', $sql);
        $this->assertStringContainsString('pk_users', $sql);
    }

    public function testAlterWithoutOperationsThrows(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Table::alter('users');
    }

    public function testAlterOnlyMethodsRejectCreateMode(): void {
        $this->expectException(InvalidArgumentException::class);
        Table::create('users')->dropColumn('id');
    }

    public function testAlterCombinesMultipleOperationsWithCommas(): void {
        $sql = (string) Table::alter('users')
            ->addColumn(Column::create('a', DataType::Int))
            ->addColumn(Column::create('b', DataType::Int));

        $this->assertStringContainsString('ADD COLUMN `a` INT NULL, ADD COLUMN `b` INT NULL', $sql);
    }
}
