<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Ddl\Schema;

final class SchemaTest extends TestCase {

    public function testCreateEmitsCreateSchema(): void {
        $this->assertSame('CREATE SCHEMA `reporting`', (string) Schema::create('reporting'));
    }

    public function testCreateWithIfNotExists(): void {
        $this->assertSame(
            'CREATE SCHEMA IF NOT EXISTS `reporting`',
            (string) Schema::create('reporting')->ifNotExists()
        );
    }

    public function testCreateWithAuthorization(): void {
        $this->assertSame(
            'CREATE SCHEMA `reporting` AUTHORIZATION `analytics`',
            (string) Schema::create('reporting')->authorization('analytics')
        );
    }

    public function testCreateWithIfNotExistsAndAuthorization(): void {
        $this->assertSame(
            'CREATE SCHEMA IF NOT EXISTS `reporting` AUTHORIZATION `analytics`',
            (string) Schema::create('reporting')->ifNotExists()->authorization('analytics')
        );
    }

    public function testDropEmitsDropSchema(): void {
        $this->assertSame('DROP SCHEMA `legacy`', (string) Schema::drop('legacy'));
    }

    public function testDropWithIfExistsCascade(): void {
        $this->assertSame(
            'DROP SCHEMA IF EXISTS `legacy` CASCADE',
            (string) Schema::drop('legacy')->ifExists()->cascade()
        );
    }

    public function testDropWithRestrict(): void {
        $this->assertSame(
            'DROP SCHEMA `legacy` RESTRICT',
            (string) Schema::drop('legacy')->restrict()
        );
    }

    public function testCascadeAndRestrictAreMutuallyExclusive(): void {
        $schema = Schema::drop('legacy')->cascade()->restrict();
        $this->assertSame('DROP SCHEMA `legacy` RESTRICT', (string) $schema);

        $schema = Schema::drop('legacy')->restrict()->cascade();
        $this->assertSame('DROP SCHEMA `legacy` CASCADE', (string) $schema);
    }

    public function testAlterRenamesSchema(): void {
        $this->assertSame(
            'ALTER SCHEMA `old_name` RENAME TO `new_name`',
            (string) Schema::alter('old_name')->renameTo('new_name')
        );
    }

    public function testAlterWithoutRenameThrows(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Schema::alter('x');
    }

    public function testRenameToOutsideAlterModeThrows(): void {
        $this->expectException(InvalidArgumentException::class);
        Schema::create('x')->renameTo('y');
    }

    public function testNameSetterChangesName(): void {
        $schema = Schema::create('first')->name('second');
        $this->assertSame('CREATE SCHEMA `second`', (string) $schema);
    }
}
