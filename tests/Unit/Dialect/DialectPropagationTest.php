<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Ddl\Table;
use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Set;
use Rak200\SqlBuilder\Dml\Update;

/**
 * Verifies that a non-default dialect propagates through every layer of
 * nested rendering — subqueries in FROM, EXISTS, JOIN ON, set operations,
 * INSERT ... SELECT, multi-row VALUES, and DDL — without leaking the default
 * dialect's quoting into intermediate fragments.
 */
final class DialectPropagationTest extends TestCase {

    public function testSubqueryInFromInheritsDialect(): void {
        $inner = Select::create()->select('id')->from('users');
        $outer = Select::create()->select('id')->from($inner, 't');

        $sql = $outer->toSql(new PostgresDialect());

        $this->assertSame('SELECT "id" FROM (SELECT "id" FROM "users") AS "t"', $sql);
        $this->assertStringNotContainsString('`', $sql);
    }

    public function testJoinOnConditionUsesDialectQuoting(): void {
        $on = Expression::binary('u.role_id', BinaryOperator::Equal, Expression::ref('r.id'));

        $sql = Select::create()
            ->select('u.name')
            ->from('users', 'u')
            ->join('roles', 'r', $on)
            ->toSql(new PostgresDialect());

        $this->assertSame(
            'SELECT "u"."name" FROM "users" AS "u" INNER JOIN "roles" AS "r" ON ("u"."role_id" = "r"."id")',
            $sql
        );
    }

    public function testJoinUsingPropagatesIdentifierQuoting(): void {
        $sql = Select::create()
            ->from('users', 'u')
            ->joinUsing('accounts', ['account_id'], 'a')
            ->toSql(new PostgresDialect());

        $this->assertStringContainsString('USING ("account_id")', $sql);
        $this->assertStringNotContainsString('`', $sql);
    }

    public function testExistsSubqueryUsesDialect(): void {
        $sub = Select::create()
            ->select('1')
            ->from('roles')
            ->where(Expression::binary('roles.user_id', BinaryOperator::Equal, Expression::ref('u.id')));

        $sql = Select::create()
            ->select('u.id')
            ->from('users', 'u')
            ->where(Expression::exists($sub))
            ->toSql(new PostgresDialect());

        $this->assertStringContainsString('EXISTS ((SELECT', $sql);
        $this->assertStringContainsString('"roles"."user_id"', $sql);
        $this->assertStringContainsString('"u"."id"', $sql);
        $this->assertStringNotContainsString('`', $sql);
    }

    public function testNestedAndOrRendersWithDialect(): void {
        $condition = Expression::or(
            Expression::and(
                Expression::binary('status', BinaryOperator::Equal, Expression::value('active')),
                Expression::binary('role', BinaryOperator::Equal, Expression::value('admin'))
            ),
            Expression::binary('email', BinaryOperator::Like, Expression::value('%@example.com'))
        );

        $sql = Select::create()
            ->select('id')
            ->from('users')
            ->where($condition)
            ->toSql(new PostgresDialect());

        $this->assertSame(
            'SELECT "id" FROM "users" WHERE ((("status" = \'active\') AND ("role" = \'admin\')) OR ("email" LIKE \'%@example.com\'))',
            $sql
        );
    }

    public function testGroupByAndOrderByPropagateDialect(): void {
        $sql = Select::create()
            ->select(Expression::count('*'))
            ->from('orders')
            ->groupBy('customer_id')
            ->having(Expression::binary(Expression::raw('COUNT(*)'), BinaryOperator::GreaterThan, 5))
            ->orderBy('customer_id')
            ->toSql(new PostgresDialect());

        $this->assertStringContainsString('GROUP BY "customer_id"', $sql);
        $this->assertStringContainsString('HAVING (COUNT(*) > 5)', $sql);
        $this->assertStringContainsString('ORDER BY "customer_id" ASC', $sql);
        $this->assertStringNotContainsString('`', $sql);
    }

    public function testSetUnionPropagatesDialect(): void {
        $a = Select::create()->select('id')->from('users');
        $b = Select::create()->select('id')->from('admins');

        $sql = Set::create($a)->union($b)->toSql(new PostgresDialect());

        $this->assertSame('(SELECT "id" FROM "users") UNION (SELECT "id" FROM "admins")', $sql);
    }

    public function testInsertSelectPropagatesDialect(): void {
        $source = Select::create()->select('id', 'name')->from('staging_users');

        $sql = Insert::create()
            ->into('users')
            ->columns('id', 'name')
            ->select($source)
            ->toSql(new PostgresDialect());

        $this->assertSame(
            'INSERT INTO "users" ("id", "name") SELECT "id", "name" FROM "staging_users"',
            $sql
        );
    }

    public function testMultiRowValuesUsePostgresStringEscape(): void {
        $sql = Insert::create()
            ->into('users')
            ->columns('name', 'tag')
            ->values("it's me", 'a\\b')
            ->values('plain', "quote''d")
            ->toSql(new PostgresDialect());

        $this->assertStringContainsString("('it''s me', 'a\\b')", $sql);
        $this->assertStringContainsString("('plain', 'quote''''d')", $sql);
        $this->assertStringNotContainsString('\\\\', $sql);
    }

    public function testUpdateAssignmentsAndWhereUseDialect(): void {
        $sql = Update::create()
            ->table('users', 'u')
            ->set('name', "O'Brien")
            ->set('updated_at', Expression::raw('NOW()'))
            ->where(Expression::binary('u.id', BinaryOperator::Equal, 7))
            ->toSql(new PostgresDialect());

        $this->assertSame(
            "UPDATE \"users\" AS \"u\" SET \"name\" = 'O''Brien', \"updated_at\" = NOW() WHERE (\"u\".\"id\" = 7)",
            $sql
        );
    }

    public function testDdlColumnRendersWithDialect(): void {
        $sql = Column::create('user_id', DataType::BigInt)
            ->nullable(false)
            ->default(0)
            ->toSql(new PostgresDialect());

        $this->assertSame('"user_id" BIGINT NOT NULL DEFAULT 0', $sql);
    }

    public function testDdlTableRendersColumnsWithDialect(): void {
        $sql = Table::create('users')
            ->column(Column::create('id', DataType::Int)->nullable(false))
            ->column(Column::create('email', DataType::VarChar)->length(255))
            ->toSql(new PostgresDialect());

        $this->assertStringContainsString('"id" INT NOT NULL', $sql);
        $this->assertStringContainsString('"email" VARCHAR(255) NULL', $sql);
        $this->assertStringNotContainsString('`', $sql);
    }

    public function testMariaDbSubqueryUsesBackticks(): void {
        $inner = Select::create()->select('id')->from('users');
        $outer = Select::create()->select('id')->from($inner, 't');

        $sql = $outer->toSql(new MariaDbDialect());

        $this->assertSame('SELECT `id` FROM (SELECT `id` FROM `users`) AS `t`', $sql);
        $this->assertStringNotContainsString('"', $sql);
    }

    public function testDefaultDialectStillMatchesToString(): void {
        $select = Select::create()
            ->select('u.name', Expression::count('*')->as('total'))
            ->from('users', 'u')
            ->where(Expression::binary('u.active', BinaryOperator::Equal, true))
            ->groupBy('u.name')
            ->orderBy('total');

        $this->assertSame((string) $select, $select->toSql(new DefaultDialect()));
        $this->assertSame((string) $select, $select->toSql(Dialect::default()));
    }
}
