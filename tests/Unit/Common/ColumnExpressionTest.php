<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\ColumnExpression;

final class ColumnExpressionTest extends TestCase {

    public function test_renders_simple_identifier(): void {
        $this->assertSame('`name`', (string) new ColumnExpression('name'));
    }

    public function test_renders_qualified_identifier(): void {
        $this->assertSame('`u`.`id`', (string) new ColumnExpression('u.id'));
    }

    public function test_appends_alias_when_set(): void {
        $this->assertSame('`email` AS `e`', (string) new ColumnExpression('email', 'e'));
    }

    public function test_from_array_with_integer_keys_yields_unaliased_columns(): void {
        $columns = ColumnExpression::fromArray(['id', 'name']);

        $this->assertCount(2, $columns);
        $this->assertSame('`id`',   (string) $columns[0]);
        $this->assertSame('`name`', (string) $columns[1]);
    }

    public function test_from_array_with_string_keys_uses_key_as_column_and_value_as_alias(): void {
        $columns = ColumnExpression::fromArray(['u.name' => 'user_name']);

        $this->assertSame('`u`.`name` AS `user_name`', (string) $columns[0]);
    }

    public function test_from_array_with_alias_is_key(): void {
        $columns = ColumnExpression::fromArray(['user_name' => 'u.name'], aliasIsKey: true);

        $this->assertSame('`u`.`name` AS `user_name`', (string) $columns[0]);
    }
}
