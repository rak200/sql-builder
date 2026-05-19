<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\TableReference;
use Rak200\SqlBuilder\Dml\Select;

final class TableReferenceTest extends TestCase {

    public function test_table_name_without_alias(): void {
        $this->assertSame('`users`', (string) new TableReference('users'));
    }

    public function test_table_name_with_alias(): void {
        $this->assertSame('`users` AS `u`', (string) new TableReference('users', 'u'));
    }

    public function test_subquery_requires_alias(): void {
        $this->expectException(InvalidArgumentException::class);

        new TableReference(Select::create()->select('1'));
    }

    public function test_subquery_with_alias_renders_with_parentheses(): void {
        $select = Select::create()->select('id')->from('users');
        $ref    = new TableReference($select, 'u');

        $this->assertSame("($select) AS `u`", (string) $ref);
    }
}
