<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Ddl\Index;

final class IndexTest extends TestCase {

    public function test_basic_index(): void {
        $sql = (string) Index::create('idx_users_email')->table('users')->columns(['email']);

        $this->assertSame('CREATE INDEX "idx_users_email" ON "users" ("email")', $sql);
    }

    public function test_unique_index(): void {
        $sql = (string) Index::create('idx_users_email')->table('users')->columns(['email'])->unique();

        $this->assertSame('CREATE UNIQUE INDEX "idx_users_email" ON "users" ("email")', $sql);
    }

    public function test_composite_index(): void {
        $sql = (string) Index::create('idx_x')->table('t')->columns(['a', 'b']);

        $this->assertStringContainsString('("a", "b")', $sql);
    }
}
