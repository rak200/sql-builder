<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Ddl\Index;

final class IndexTest extends TestCase {

    public function testBasicIndex(): void {
        $sql = (string) Index::create('idx_users_email')->table('users')->columns(['email']);

        $this->assertSame('CREATE INDEX `idx_users_email` ON `users` (`email`)', $sql);
    }

    public function testUniqueIndex(): void {
        $sql = (string) Index::create('idx_users_email')->table('users')->columns(['email'])->unique();

        $this->assertSame('CREATE UNIQUE INDEX `idx_users_email` ON `users` (`email`)', $sql);
    }

    public function testCompositeIndex(): void {
        $sql = (string) Index::create('idx_x')->table('t')->columns(['a', 'b']);

        $this->assertStringContainsString('(`a`, `b`)', $sql);
    }
}
