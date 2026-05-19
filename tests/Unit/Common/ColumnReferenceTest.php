<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\ColumnReference;

final class ColumnReferenceTest extends TestCase {

    public function test_renders_unqualified_identifier(): void {
        $this->assertSame('`id`', (string) new ColumnReference('id'));
    }

    public function test_renders_qualified_identifier(): void {
        $this->assertSame('`users`.`id`', (string) new ColumnReference('users.id'));
    }

    public function test_preserves_star(): void {
        $this->assertSame('*', (string) new ColumnReference('*'));
    }

    public function test_preserves_qualified_star(): void {
        $this->assertSame('`u`.*', (string) new ColumnReference('u.*'));
    }
}
