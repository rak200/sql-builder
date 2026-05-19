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

    public function test_create_table_emits_keyword_and_quoted_name(): void {
        $sql = (string) Table::create('users');

        $this->assertStringStartsWith('CREATE TABLE ', $sql);
        $this->assertStringContainsString('users', $sql);
    }

    public function test_create_table_includes_column_definitions(): void {
        $sql = (string) Table::create('users')
            ->column(Column::create('id', DataType::BigInt))
            ->column(Column::create('name', DataType::VarChar)->length(100));

        $this->assertStringContainsString('`id` BIGINT NULL', $sql);
        $this->assertStringContainsString('`name` VARCHAR(100) NULL', $sql);
    }

    public function test_columns_plural_accepts_multiple_columns(): void {
        $sql = (string) Table::create('t')
            ->columns(
                Column::create('a', DataType::Int),
                Column::create('b', DataType::Int)
            );

        $this->assertStringContainsString('`a` INT NULL', $sql);
        $this->assertStringContainsString('`b` INT NULL', $sql);
    }

    public function test_create_table_includes_constraint(): void {
        $sql = (string) Table::create('users')
            ->column(Column::create('id', DataType::Int))
            ->constraint(PrimaryKey::create()->columns(['id']));

        $this->assertStringContainsString('PRIMARY KEY ("id")', $sql);
    }

    public function test_alter_add_column(): void {
        $sql = (string) Table::alter('users')->addColumn(Column::create('age', DataType::Int));

        $this->assertStringStartsWith('ALTER TABLE ', $sql);
        $this->assertStringContainsString('ADD COLUMN `age` INT NULL', $sql);
    }

    public function test_alter_drop_column(): void {
        $sql = (string) Table::alter('users')->dropColumn('legacy');

        $this->assertStringContainsString('DROP COLUMN', $sql);
        $this->assertStringContainsString('legacy', $sql);
    }

    public function test_alter_rename_column(): void {
        $sql = (string) Table::alter('users')->renameColumn('email', 'email_address');

        $this->assertStringContainsString('RENAME COLUMN', $sql);
        $this->assertStringContainsString('email', $sql);
        $this->assertStringContainsString('email_address', $sql);
    }

    public function test_alter_rename_to(): void {
        $sql = (string) Table::alter('old')->renameTo('new');

        $this->assertStringContainsString('RENAME TO', $sql);
        $this->assertStringContainsString('new', $sql);
    }

    public function test_alter_modify_column(): void {
        $sql = (string) Table::alter('users')
            ->modifyColumn(Column::create('email', DataType::VarChar)->length(320));

        $this->assertStringContainsString('MODIFY COLUMN `email` VARCHAR(320) NULL', $sql);
    }

    public function test_alter_drop_constraint(): void {
        $sql = (string) Table::alter('users')->dropConstraint('pk_users');

        $this->assertStringContainsString('DROP CONSTRAINT', $sql);
        $this->assertStringContainsString('pk_users', $sql);
    }

    public function test_alter_without_operations_throws(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Table::alter('users');
    }

    public function test_alter_only_methods_reject_create_mode(): void {
        $this->expectException(InvalidArgumentException::class);
        Table::create('users')->dropColumn('id');
    }

    public function test_alter_combines_multiple_operations_with_commas(): void {
        $sql = (string) Table::alter('users')
            ->addColumn(Column::create('a', DataType::Int))
            ->addColumn(Column::create('b', DataType::Int));

        $this->assertStringContainsString('ADD COLUMN `a` INT NULL, ADD COLUMN `b` INT NULL', $sql);
    }
}
