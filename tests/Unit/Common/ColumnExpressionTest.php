<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\ColumnExpression;

final class ColumnExpressionTest extends TestCase {

    public function testRendersSimpleIdentifier(): void {
        $this->assertSame('`name`', (string) new ColumnExpression('name'));
    }

    public function testRendersQualifiedIdentifier(): void {
        $this->assertSame('`u`.`id`', (string) new ColumnExpression('u.id'));
    }

    public function testAppendsAliasWhenSet(): void {
        $this->assertSame('`email` AS `e`', (string) new ColumnExpression('email', 'e'));
    }

    public function testFromArrayWithIntegerKeysYieldsUnaliasedColumns(): void {
        $columns = ColumnExpression::fromArray(['id', 'name']);

        $this->assertCount(2, $columns);
        $this->assertSame('`id`',   (string) $columns[0]);
        $this->assertSame('`name`', (string) $columns[1]);
    }

    public function testFromArrayWithStringKeysUsesKeyAsColumnAndValueAsAlias(): void {
        $columns = ColumnExpression::fromArray(['u.name' => 'user_name']);

        $this->assertSame('`u`.`name` AS `user_name`', (string) $columns[0]);
    }

    public function testFromArrayWithAliasIsKey(): void {
        $columns = ColumnExpression::fromArray(['user_name' => 'u.name'], aliasIsKey: true);

        $this->assertSame('`u`.`name` AS `user_name`', (string) $columns[0]);
    }
}
