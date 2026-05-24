<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary as BinaryOperator;
use Rak200\SqlBuilder\Common\Expr as Expression;
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Ddl\Table;
use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\Postgres15Dialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Merge;
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
        $on = Expression::binary('u.role_id', BinaryOperator::Eq, Expression::ref('r.id'));

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
            ->where(Expression::binary('roles.user_id', BinaryOperator::Eq, Expression::ref('u.id')));

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
                Expression::binary('status', BinaryOperator::Eq, Expression::val('active')),
                Expression::binary('role', BinaryOperator::Eq, Expression::val('admin'))
            ),
            Expression::binary('email', BinaryOperator::Like, Expression::val('%@example.com'))
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
            ->having(Expression::binary(Expression::raw('COUNT(*)'), BinaryOperator::Gt, 5))
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
            ->where(Expression::binary('u.id', BinaryOperator::Eq, 7))
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

    public function testMergePropagatesDialectToTargetSourceAndBranches(): void {
        $source = Select::create()->select('id', 'name')->from('staging');

        $sql = Merge::create()
            ->into('users', 't')
            ->using($source, 's')
            ->on(Expression::binary('t.id', BinaryOperator::Eq, Expression::ref('s.id')))
            ->whenMatchedUpdate(['name' => Expression::ref('s.name')])
            ->whenNotMatchedInsert(['id', 'name'], [Expression::ref('s.id'), Expression::ref('s.name')])
            ->toSql(new Postgres15Dialect());

        $this->assertStringContainsString('MERGE INTO "users" AS "t"', $sql);
        $this->assertStringContainsString('USING (SELECT "id", "name" FROM "staging") AS "s"', $sql);
        $this->assertStringContainsString('ON ("t"."id" = "s"."id")', $sql);
        $this->assertStringContainsString('UPDATE SET "name" = "s"."name"', $sql);
        $this->assertStringContainsString('INSERT ("id", "name") VALUES ("s"."id", "s"."name")', $sql);
        $this->assertStringNotContainsString('`', $sql);
    }

    public function testGroupingExtensionsPropagateDialect(): void {
        $sql = Select::create()
            ->select('region', Expression::sum('amount'))
            ->from('sales')
            ->groupBy(Expression::groupingSets(['region', 'product'], ['region'], []))
            ->toSql(new PostgresDialect());

        $this->assertStringContainsString(
            'GROUP BY GROUPING SETS (("region", "product"), ("region"), ())',
            $sql
        );
        $this->assertStringNotContainsString('`', $sql);
    }

    public function testLateralJoinPropagatesDialect(): void {
        $sub = Select::create()
            ->select('id')
            ->from('orders')
            ->where(Expression::binary('orders.user_id', BinaryOperator::Eq, Expression::ref('u.id')));

        $sql = Select::create()
            ->select('u.id', 'r.id')
            ->from('users', 'u')
            ->leftLateralJoin($sub, 'r', Expression::raw('TRUE'))
            ->toSql(new PostgresDialect());

        $this->assertStringContainsString('LEFT JOIN LATERAL (SELECT "id" FROM "orders"', $sql);
        $this->assertStringContainsString('"orders"."user_id" = "u"."id"', $sql);
        $this->assertStringContainsString('AS "r" ON TRUE', $sql);
        $this->assertStringNotContainsString('`', $sql);
    }

    public function testOnConflictTranslationOnMariaDb(): void {
        $sql = Insert::create()
            ->into('users')
            ->columns('id', 'email')
            ->values(1, "user's email")
            ->onConflict('id')
            ->doUpdate(['email' => Expression::raw('VALUES(email)')])
            ->toSql(new MariaDbDialect());

        $this->assertStringContainsString('ON DUPLICATE KEY UPDATE `email` = VALUES(email)', $sql);
        $this->assertStringContainsString("'user''s email'", $sql);
        $this->assertStringNotContainsString('ON CONFLICT', $sql);
        $this->assertStringNotContainsString('"', $sql);
    }

    public function testDefaultDialectStillMatchesToString(): void {
        $select = Select::create()
            ->select('u.name', Expression::count('*')->as('total'))
            ->from('users', 'u')
            ->where(Expression::binary('u.active', BinaryOperator::Eq, true))
            ->groupBy('u.name')
            ->orderBy('total');

        $this->assertSame((string) $select, $select->toSql(new DefaultDialect()));
        $this->assertSame((string) $select, $select->toSql(Dialect::default()));
    }
}
