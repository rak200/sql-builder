<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\ForeignKeyAction;
use Rak200\SqlBuilder\Ddl\ForeignKey;

final class ForeignKeyTest extends TestCase {

    public function test_basic_foreign_key(): void {
        $sql = (string) ForeignKey::create('fk_users_role')
            ->columns(['role_id'])
            ->references('roles', ['id']);

        $this->assertSame(
            'CONSTRAINT "fk_users_role" FOREIGN KEY ("role_id") REFERENCES "roles" ("id")',
            $sql
        );
    }

    public function test_on_delete(): void {
        $sql = (string) ForeignKey::create('fk_x')
            ->columns(['parent_id'])
            ->references('parents', ['id'])
            ->onDelete(ForeignKeyAction::CASCADE);

        $this->assertStringEndsWith('ON DELETE CASCADE', $sql);
    }

    public function test_on_update(): void {
        $sql = (string) ForeignKey::create('fk_x')
            ->columns(['parent_id'])
            ->references('parents', ['id'])
            ->onUpdate(ForeignKeyAction::SET_NULL);

        $this->assertStringEndsWith('ON UPDATE SET NULL', $sql);
    }

    public function test_on_delete_and_on_update(): void {
        $sql = (string) ForeignKey::create('fk_x')
            ->columns(['parent_id'])
            ->references('parents', ['id'])
            ->onDelete(ForeignKeyAction::RESTRICT)
            ->onUpdate(ForeignKeyAction::NO_ACTION);

        $this->assertStringContainsString('ON DELETE RESTRICT', $sql);
        $this->assertStringContainsString('ON UPDATE NO ACTION', $sql);
    }

    public function test_composite_foreign_key(): void {
        $sql = (string) ForeignKey::create('fk_x')
            ->columns(['a_id', 'b_id'])
            ->references('parents', ['a', 'b']);

        $this->assertStringContainsString('("a_id", "b_id")', $sql);
        $this->assertStringContainsString('REFERENCES "parents" ("a", "b")', $sql);
    }
}
