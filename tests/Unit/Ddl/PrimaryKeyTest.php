<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Ddl\PrimaryKey;

final class PrimaryKeyTest extends TestCase {

    public function test_unnamed_primary_key(): void {
        $this->assertSame('PRIMARY KEY ("id")', (string) PrimaryKey::create()->columns(['id']));
    }

    public function test_named_primary_key(): void {
        $sql = (string) PrimaryKey::create('pk_users')->columns(['id']);

        $this->assertSame('CONSTRAINT "pk_users" PRIMARY KEY ("id")', $sql);
    }

    public function test_composite_primary_key(): void {
        $this->assertSame('PRIMARY KEY ("a", "b")', (string) PrimaryKey::create()->columns(['a', 'b']));
    }

    public function test_no_columns_omits_parenthesis(): void {
        $sql = (string) PrimaryKey::create();

        $this->assertSame('PRIMARY KEY', $sql);
    }
}
