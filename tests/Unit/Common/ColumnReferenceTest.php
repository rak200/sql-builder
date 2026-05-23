<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Reference\Column as ColumnReference;

final class ColumnReferenceTest extends TestCase {

    public function testRendersUnqualifiedIdentifier(): void {
        $this->assertSame('`id`', (string) new ColumnReference('id'));
    }

    public function testRendersQualifiedIdentifier(): void {
        $this->assertSame('`users`.`id`', (string) new ColumnReference('users.id'));
    }

    public function testPreservesStar(): void {
        $this->assertSame('*', (string) new ColumnReference('*'));
    }

    public function testPreservesQualifiedStar(): void {
        $this->assertSame('`u`.*', (string) new ColumnReference('u.*'));
    }
}
