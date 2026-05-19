<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Ddl\UniqueKey;

final class UniqueKeyTest extends TestCase {

    public function test_unnamed_unique(): void {
        $this->assertSame('UNIQUE ("email")', (string) UniqueKey::create()->columns(['email']));
    }

    public function test_named_unique(): void {
        $sql = (string) UniqueKey::create('uq_email')->columns(['email']);

        $this->assertSame('CONSTRAINT "uq_email" UNIQUE ("email")', $sql);
    }

    public function test_composite_unique(): void {
        $this->assertSame('UNIQUE ("a", "b")', (string) UniqueKey::create()->columns(['a', 'b']));
    }
}
