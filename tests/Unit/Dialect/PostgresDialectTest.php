<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary as BinaryOperator;
use Rak200\SqlBuilder\Common\Expr as Expression;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dialect\UnsupportedFeatureException;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;

final class PostgresDialectTest extends TestCase {

    public function testIdentifierUsesDoubleQuotes(): void {
        $dialect = new PostgresDialect();
        $this->assertSame('"name"', $dialect->quoteIdentifier('name'));
        $this->assertSame('"users"."id"', $dialect->quoteIdentifier('users.id'));
        $this->assertSame('*', $dialect->quoteIdentifier('*'));
    }

    public function testValueOnlyEscapesSingleQuote(): void {
        $dialect = new PostgresDialect();
        $this->assertSame("'it''s'", $dialect->quoteValue("it's"));
        $this->assertSame("'a\\b'", $dialect->quoteValue('a\\b'));
    }

    public function testSelectUsesDoubleQuotedIdentifiers(): void {
        $sql = Select::create()
            ->select('id')
            ->from('users')
            ->where(Expression::binary('id', BinaryOperator::Eq, 1))
            ->toSql(new PostgresDialect());

        $this->assertSame('SELECT "id" FROM "users" WHERE ("id" = 1)', $sql);
    }

    public function testInsertRejectsOnDuplicateKeyUpdate(): void {
        $insert = Insert::create()
            ->into('users')
            ->columns('id')
            ->values(1)
            ->onDuplicateKeyUpdate('id', Expression::raw('1'));

        $this->expectException(UnsupportedFeatureException::class);
        $insert->toSql(new PostgresDialect());
    }
}
