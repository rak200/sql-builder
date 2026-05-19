<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Ddl\PrimaryKey;

final class PrimaryKeyTest extends TestCase {

    public function testUnnamedPrimaryKey(): void {
        $this->assertSame('PRIMARY KEY ("id")', (string) PrimaryKey::create()->columns(['id']));
    }

    public function testNamedPrimaryKey(): void {
        $sql = (string) PrimaryKey::create('pk_users')->columns(['id']);

        $this->assertSame('CONSTRAINT "pk_users" PRIMARY KEY ("id")', $sql);
    }

    public function testCompositePrimaryKey(): void {
        $this->assertSame('PRIMARY KEY ("a", "b")', (string) PrimaryKey::create()->columns(['a', 'b']));
    }

    public function testNoColumnsOmitsParenthesis(): void {
        $sql = (string) PrimaryKey::create();

        $this->assertSame('PRIMARY KEY', $sql);
    }
}
