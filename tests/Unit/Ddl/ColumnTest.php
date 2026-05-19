<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Ddl\Sequence;

final class ColumnTest extends TestCase {

    public function test_minimal_column_renders_nullable(): void {
        $this->assertSame('`id` BIGINT NULL', (string) Column::create('id', DataType::BigInt));
    }

    public function test_length_is_emitted_in_parens(): void {
        $sql = (string) Column::create('email', DataType::VarChar)->length(255);

        $this->assertSame('`email` VARCHAR(255) NULL', $sql);
    }

    public function test_not_null(): void {
        $sql = (string) Column::create('id', DataType::Int)->nullable(false);

        $this->assertSame('`id` INT NOT NULL', $sql);
    }

    public function test_default_quotes_string_value(): void {
        $sql = (string) Column::create('country', DataType::VarChar)->length(2)->default('BR');

        $this->assertSame("`country` VARCHAR(2) NULL DEFAULT 'BR'", $sql);
    }

    public function test_default_passes_through_expressions(): void {
        $sql = (string) Column::create('created_at', DataType::DateTime)->default(Expression::raw('CURRENT_TIMESTAMP'));

        $this->assertSame('`created_at` DATETIME NULL DEFAULT CURRENT_TIMESTAMP', $sql);
    }

    public function test_auto_increment(): void {
        $sql = (string) Column::create('id', DataType::Int)->nullable(false)->autoIncrement();

        $this->assertSame('`id` INT NOT NULL AUTO_INCREMENT', $sql);
    }

    public function test_primary_key_flag(): void {
        $sql = (string) Column::create('id', DataType::Int)->nullable(false)->primaryKey();

        $this->assertSame('`id` INT NOT NULL PRIMARY KEY', $sql);
    }

    public function test_sequence_default_emits_nextval_expression(): void {
        $seq = Sequence::create('order_id_seq');
        $sql = (string) Column::create('id', DataType::BigInt)->nullable(false)->sequence($seq);

        $this->assertStringContainsString('DEFAULT NEXTVAL(', $sql);
        $this->assertStringContainsString('order_id_seq', $sql);
    }
}
