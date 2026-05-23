<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Reference\Table as TableReference;
use Rak200\SqlBuilder\Dml\Select;

final class TableReferenceTest extends TestCase {

    public function testTableNameWithoutAlias(): void {
        $this->assertSame('`users`', (string) new TableReference('users'));
    }

    public function testTableNameWithAlias(): void {
        $this->assertSame('`users` AS `u`', (string) new TableReference('users', 'u'));
    }

    public function testSubqueryRequiresAlias(): void {
        $this->expectException(InvalidArgumentException::class);

        new TableReference(Select::create()->select('1'));
    }

    public function testSubqueryWithAliasRendersWithParentheses(): void {
        $select = Select::create()->select('id')->from('users');
        $ref    = new TableReference($select, 'u');

        $this->assertSame("($select) AS `u`", (string) $ref);
    }
}
