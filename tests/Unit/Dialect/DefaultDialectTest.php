<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Expr as Expression;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary as BinaryOperator;
use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;

final class DefaultDialectTest extends TestCase {

    public function testDefaultReturnsSingleton(): void {
        $this->assertSame(Dialect::default(), Dialect::default());
        $this->assertInstanceOf(DefaultDialect::class, Dialect::default());
    }

    public function testQuoteIdentifierUsesBackticks(): void {
        $this->assertSame('`name`', Dialect::default()->quoteIdentifier('name'));
        $this->assertSame('`users`.`id`', Dialect::default()->quoteIdentifier('users.id'));
        $this->assertSame('*', Dialect::default()->quoteIdentifier('*'));
    }

    public function testQuoteValueEscapesBackslashesAndQuotes(): void {
        $dialect = Dialect::default();
        $this->assertSame('NULL', $dialect->quoteValue(null));
        $this->assertSame('42', $dialect->quoteValue(42));
        $this->assertSame('TRUE', $dialect->quoteValue(true));
        $this->assertSame("'it''s'", $dialect->quoteValue("it's"));
        $this->assertSame("'a\\\\b'", $dialect->quoteValue('a\\b'));
    }

    public function testToSqlRendersWithDefault(): void {
        $sql = Select::create()->select('id')->from('users')->toSql(Dialect::default());
        $this->assertSame('SELECT `id` FROM `users`', $sql);
    }

    public function testToStringMatchesToSqlWithDefault(): void {
        $select = Select::create()
            ->select('u.name')
            ->from('users', 'u')
            ->where(Expression::binary('u.id', BinaryOperator::Eq, 1));

        $this->assertSame((string) $select, $select->toSql(Dialect::default()));
    }

    public function testInsertRendersOnDuplicateKeyUpdateUnderDefault(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id', 'name')
            ->values(1, 'Alice')
            ->onDuplicateKeyUpdate('name', Expression::raw('VALUES(name)'));

        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE', $sql);
        $this->assertStringContainsString('VALUES(name)', $sql);
    }
}
