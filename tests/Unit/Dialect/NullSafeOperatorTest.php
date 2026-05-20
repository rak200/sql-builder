<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;

/**
 * Null-safe binary operators across dialects.
 *
 * The default and PostgreSQL dialects emit the SQL-standard
 * `IS [NOT] DISTINCT FROM` form. MariaDB rewrites them to the native
 * spaceship operator (`<=>`) — equal as `(a <=> b)` and not-equal as
 * `NOT (a <=> b)`.
 */
final class NullSafeOperatorTest extends TestCase {

    public function testDefaultEmitsIsNotDistinctFrom(): void {
        $expr = Expression::binary('a', BinaryOperator::NullSafeEq, Expression::ref('b'));
        $this->assertSame('(`a` IS NOT DISTINCT FROM `b`)', (string) $expr);
    }

    public function testDefaultEmitsIsDistinctFromForNotEqual(): void {
        $expr = Expression::binary('a', BinaryOperator::NullSafeNe, Expression::ref('b'));
        $this->assertSame('(`a` IS DISTINCT FROM `b`)', (string) $expr);
    }

    public function testNullLiteralRoundtrips(): void {
        $expr = Expression::binary('a', BinaryOperator::NullSafeEq, Expression::value(null));
        $this->assertSame('(`a` IS NOT DISTINCT FROM NULL)', (string) $expr);
    }

    public function testPostgresEmitsIsNotDistinctFromWithDoubleQuotes(): void {
        $expr = Expression::binary('a', BinaryOperator::NullSafeEq, Expression::ref('b'));
        $this->assertSame('("a" IS NOT DISTINCT FROM "b")', $expr->toSql(new PostgresDialect()));
    }

    public function testMariaDbRewritesEqualToSpaceship(): void {
        $expr = Expression::binary('a', BinaryOperator::NullSafeEq, Expression::ref('b'));
        $this->assertSame('(`a` <=> `b`)', $expr->toSql(new MariaDbDialect()));
    }

    public function testMariaDbRewritesNotEqualToNotSpaceship(): void {
        $expr = Expression::binary('a', BinaryOperator::NullSafeNe, Expression::ref('b'));
        $this->assertSame('NOT (`a` <=> `b`)', $expr->toSql(new MariaDbDialect()));
    }

    public function testMariaDbNullSafeAgainstNullLiteral(): void {
        $expr = Expression::binary('email', BinaryOperator::NullSafeEq, Expression::value(null));
        $this->assertSame('(`email` <=> NULL)', $expr->toSql(new MariaDbDialect()));
    }

    public function testAliasIsPreservedAcrossDialects(): void {
        $expr = Expression::binary('a', BinaryOperator::NullSafeEq, Expression::ref('b'))->as('match');

        $this->assertSame(
            '(`a` IS NOT DISTINCT FROM `b`) AS `match`',
            $expr->toSql(Dialect::default())
        );
        $this->assertSame(
            '(`a` <=> `b`) AS `match`',
            $expr->toSql(new MariaDbDialect())
        );
    }

    public function testOrdinaryOperatorsStillFallThroughOnMariaDb(): void {
        $expr = Expression::binary('a', BinaryOperator::Eq, Expression::ref('b'));
        $this->assertSame('(`a` = `b`)', $expr->toSql(new MariaDbDialect()));
    }
}
