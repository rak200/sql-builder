<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Prepared;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\Operator\Math as ArithmeticOperator;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary as BinaryOperator;
use Rak200\SqlBuilder\Common\Expr as Expression;
use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dml\Delete;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Update;

final class PrepareDmlTest extends TestCase {

    public function testSelectWhereBindsScalarValue(): void {
        $stmt = Select::create()
            ->from('users')
            ->where(Expression::binary('id', BinaryOperator::Eq, 42))
            ->prepare(new DefaultDialect());

        $this->assertSame('SELECT * FROM `users` WHERE (`id` = ?)', $stmt->sql);
        $this->assertSame([42], $stmt->parameters);
    }

    public function testSelectWhereOnPostgresUsesDollarN(): void {
        $stmt = Select::create()
            ->from('users')
            ->where(Expression::binary('id', BinaryOperator::Eq, 42))
            ->prepare(new PostgresDialect());

        $this->assertSame('SELECT * FROM "users" WHERE ("id" = $1)', $stmt->sql);
        $this->assertSame([42], $stmt->parameters);
    }

    public function testInsertMultiRowAppendsEachValueAsAnonymousPlaceholder(): void {
        $stmt = Insert::create()
            ->into('users')
            ->columns('name', 'age')
            ->values('alice', 30)
            ->values('bob',   25)
            ->prepare(new DefaultDialect());

        $this->assertSame(
            'INSERT INTO `users` (`name`, `age`) VALUES (?, ?), (?, ?)',
            $stmt->sql
        );
        $this->assertSame(['alice', 30, 'bob', 25], $stmt->parameters);
    }

    public function testUpdateSetCollectsValuesInOrder(): void {
        $stmt = Update::create()
            ->table('users')
            ->set('name', 'alice')
            ->set('age', 30)
            ->where(Expression::binary('id', BinaryOperator::Eq, 5))
            ->prepare(new DefaultDialect());

        $this->assertSame(
            'UPDATE `users` SET `name` = ?, `age` = ? WHERE (`id` = ?)',
            $stmt->sql
        );
        $this->assertSame(['alice', 30, 5], $stmt->parameters);
    }

    public function testDeleteWhereBindsScalar(): void {
        $stmt = Delete::create()
            ->from('users')
            ->where(Expression::binary('id', BinaryOperator::Eq, 5))
            ->prepare(new DefaultDialect());

        $this->assertSame('DELETE FROM `users` WHERE (`id` = ?)', $stmt->sql);
        $this->assertSame([5], $stmt->parameters);
    }

    public function testRawExpressionStaysVerbatimInBindMode(): void {
        $stmt = Update::create()
            ->table('users')
            ->set('updated_at', Expression::raw('NOW()'))
            ->set('age', 30)
            ->prepare(new DefaultDialect());

        $this->assertSame(
            'UPDATE `users` SET `updated_at` = NOW(), `age` = ?',
            $stmt->sql
        );
        $this->assertSame([30], $stmt->parameters);
    }

    public function testLimitAndOffsetStayInlinedAsIntegers(): void {
        $stmt = Select::create()
            ->from('users')
            ->where(Expression::binary('id', BinaryOperator::Gt, 0))
            ->limit(10)
            ->offset(20)
            ->prepare(new DefaultDialect());

        $this->assertSame(
            'SELECT * FROM `users` WHERE (`id` > ?) LIMIT 10 OFFSET 20',
            $stmt->sql
        );
        $this->assertSame([0], $stmt->parameters);
    }

    public function testExistsSubqueryPropagatesBinder(): void {
        $inner = Select::create()
            ->from('orders')
            ->where(Expression::binary('user_id', BinaryOperator::Eq, 7));

        $stmt = Select::create()
            ->from('users')
            ->where(Expression::exists($inner))
            ->prepare(new DefaultDialect());

        $this->assertSame(
            'SELECT * FROM `users` WHERE EXISTS ((SELECT * FROM `orders` WHERE (`user_id` = ?)))',
            $stmt->sql
        );
        $this->assertSame([7], $stmt->parameters);
    }

    public function testCaseExpressionBranchesBindLiterals(): void {
        $case = Expression::case()
            ->when(Expression::binary('age', BinaryOperator::Lt, 18), 'minor')
            ->when(Expression::binary('age', BinaryOperator::Lt, 65), 'adult')
            ->else('senior');

        $stmt = Select::create()
            ->select($case->as('bucket'))
            ->from('users')
            ->prepare(new DefaultDialect());

        $this->assertSame(
            'SELECT CASE WHEN (`age` < ?) THEN ? WHEN (`age` < ?) THEN ? ELSE ? END AS `bucket` FROM `users`',
            $stmt->sql
        );
        $this->assertSame([18, 'minor', 65, 'adult', 'senior'], $stmt->parameters);
    }

    public function testPostgresPositionalKeyReusesAcrossSelectList(): void {
        $stmt = Select::create()
            ->select(Expression::param(1)->as('price'))
            ->select(Expression::param(2)->as('qtd'))
            ->select(
                Expression::binary(
                    Expression::param(1),
                    ArithmeticOperator::Mul,
                    Expression::param(2)
                )->as('total')
            )
            ->prepare(new PostgresDialect());

        $this->assertSame(
            'SELECT $1 AS "price", $2 AS "qtd", ($1 * $2) AS "total"',
            $stmt->sql
        );
        $this->assertSame([null, null], $stmt->parameters);
    }

    public function testMariaDbPositionalKeyDuplicatesValuePerOccurrence(): void {
        $stmt = Select::create()
            ->select(Expression::param(1, 'P')->as('price'))
            ->select(Expression::param(2, 'Q')->as('qtd'))
            ->select(
                Expression::binary(
                    Expression::param(1, 'P'),
                    ArithmeticOperator::Mul,
                    Expression::param(2, 'Q')
                )->as('total')
            )
            ->prepare(new DefaultDialect());

        $this->assertSame(
            'SELECT ? AS `price`, ? AS `qtd`, (? * ?) AS `total`',
            $stmt->sql
        );
        $this->assertSame(['P', 'Q', 'P', 'Q'], $stmt->parameters);
    }

    public function testNamedKeysReuseAndCarryDefaultValue(): void {
        $stmt = Select::create()
            ->from('orders')
            ->where(
                Expression::and(
                    Expression::binary('user_id', BinaryOperator::Eq, Expression::param('uid', 7)),
                    Expression::binary('owner_id', BinaryOperator::Eq, Expression::param('uid', 7))
                )
            )
            ->prepare(new DefaultDialect());

        $this->assertSame(
            'SELECT * FROM `orders` WHERE ((`user_id` = :uid) AND (`owner_id` = :uid))',
            $stmt->sql
        );
        $this->assertSame(['uid' => 7], $stmt->parameters);
    }

    public function testJoinWithSixPostgresPlaceholders(): void {
        $stmt = Select::create()
            ->from('users', 'u')
            ->join('orders', 'o', Expression::binary('u.id', BinaryOperator::Eq, 'o.user_id'))
            ->where(Expression::and(
                Expression::binary('u.active',  BinaryOperator::Eq, Expression::val(true)),
                Expression::binary('u.country', BinaryOperator::Eq, Expression::val('BR')),
                Expression::binary('o.total',   BinaryOperator::Gt, Expression::val(100)),
                Expression::binary('o.status',  BinaryOperator::Ne, Expression::val('cancelled')),
                Expression::binary('o.created', BinaryOperator::Ge, Expression::val('2024-01-01')),
                Expression::binary('o.region',  BinaryOperator::Eq, Expression::val('south')),
            ))
            ->prepare(new PostgresDialect());

        $this->assertStringContainsString('$1', $stmt->sql);
        $this->assertStringContainsString('$6', $stmt->sql);
        $this->assertStringNotContainsString('$7', $stmt->sql);
        $this->assertSame(
            [true, 'BR', 100, 'cancelled', '2024-01-01', 'south'],
            $stmt->parameters
        );
    }

    public function testParameterExpressionWithoutBinderThrows(): void {
        $expr = Expression::param('p');

        $this->expectException(\LogicException::class);
        (string) $expr;
    }

    public function testToStringStillInlinesValuesAfterPrepareOnSameSingleton(): void {
        // Ensure withBinder() did not mutate the shared default singleton.
        $select = Select::create()
            ->from('t')
            ->where(Expression::binary('id', BinaryOperator::Eq, 1));

        $select->prepare(\Rak200\SqlBuilder\Dialect\Dialect::default());

        $this->assertSame(
            'SELECT * FROM `t` WHERE (`id` = 1)',
            (string) $select
        );
    }

    public function testDefaultValueOnParameterExpressionIsBound(): void {
        $stmt = Select::create()
            ->from('t')
            ->where(Expression::binary('id', BinaryOperator::Eq, Expression::param('id', 99)))
            ->prepare(new DefaultDialect());

        $this->assertSame('SELECT * FROM `t` WHERE (`id` = :id)', $stmt->sql);
        $this->assertSame(['id' => 99], $stmt->parameters);
    }
}
