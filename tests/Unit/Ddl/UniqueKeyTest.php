<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Ddl\UniqueKey;

final class UniqueKeyTest extends TestCase {

    public function testUnnamedUnique(): void {
        $this->assertSame('UNIQUE (`email`)', (string) UniqueKey::create()->columns(['email']));
    }

    public function testNamedUnique(): void {
        $sql = (string) UniqueKey::create('uq_email')->columns(['email']);

        $this->assertSame('CONSTRAINT `uq_email` UNIQUE (`email`)', $sql);
    }

    public function testCompositeUnique(): void {
        $this->assertSame('UNIQUE (`a`, `b`)', (string) UniqueKey::create()->columns(['a', 'b']));
    }
}
