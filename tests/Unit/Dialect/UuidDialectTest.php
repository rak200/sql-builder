<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Update;

final class UuidDialectTest extends TestCase {

    private const UUID = 'a1b2c3d4-e5f6-7788-99aa-bbccddeeff00';

    // --- INSERT VALUES ----------------------------------------------------

    public function testInsertValueWrappingDefault(): void {
        $sql = Insert::create()
            ->into('users')
            ->columns('id', 'name')
            ->values(Expression::uuid(self::UUID), 'alice')
            ->toSql(new DefaultDialect());

        $this->assertSame(
            "INSERT INTO `users` (`id`, `name`) VALUES ('" . self::UUID . "', 'alice')",
            $sql
        );
    }

    public function testInsertValueWrappingPostgresCasts(): void {
        $sql = Insert::create()
            ->into('users')
            ->columns('id', 'name')
            ->values(Expression::uuid(self::UUID), 'alice')
            ->toSql(new PostgresDialect());

        $this->assertSame(
            'INSERT INTO "users" ("id", "name") VALUES (\'' . self::UUID . '\'::uuid, \'alice\')',
            $sql
        );
    }

    public function testInsertValueWrappingMariaDbWrapsUuidToBin(): void {
        $sql = Insert::create()
            ->into('users')
            ->columns('id', 'name')
            ->values(Expression::uuid(self::UUID), 'alice')
            ->toSql(new MariaDbDialect());

        $this->assertSame(
            "INSERT INTO `users` (`id`, `name`) VALUES (UUID_TO_BIN('" . self::UUID . "'), 'alice')",
            $sql
        );
    }

    // --- UPDATE SET -------------------------------------------------------

    public function testUpdateSetUuidValueOnMariaDb(): void {
        $sql = Update::create()
            ->table('users')
            ->set('id', Expression::uuid(self::UUID))
            ->where(Expression::binary('legacy_id', BinaryOperator::Eq, 1))
            ->toSql(new MariaDbDialect());

        $this->assertSame(
            "UPDATE `users` SET `id` = UUID_TO_BIN('" . self::UUID . "') WHERE (`legacy_id` = 1)",
            $sql
        );
    }

    // --- WHERE comparison -------------------------------------------------

    public function testWhereComparisonOnPostgresCastsRightSide(): void {
        $sql = Select::create()
            ->from('users')
            ->where(Expression::binary('id', BinaryOperator::Eq, Expression::uuid(self::UUID)))
            ->toSql(new PostgresDialect());

        $this->assertSame(
            'SELECT * FROM "users" WHERE ("id" = \'' . self::UUID . '\'::uuid)',
            $sql
        );
    }

    public function testWhereComparisonOnMariaDbWrapsRightSide(): void {
        $sql = Select::create()
            ->from('users')
            ->where(Expression::binary('id', BinaryOperator::Eq, Expression::uuid(self::UUID)))
            ->toSql(new MariaDbDialect());

        $this->assertSame(
            "SELECT * FROM `users` WHERE (`id` = UUID_TO_BIN('" . self::UUID . "'))",
            $sql
        );
    }

    // --- JOIN ON between two UUID columns --------------------------------

    public function testJoinOnTwoUuidColumnsStaysBareEverywhere(): void {
        $join = Expression::binary('u.id', BinaryOperator::Eq, 'o.user_id');
        $builder = fn() => Select::create()
            ->from('users', 'u')
            ->join('orders', 'o', $join);

        $this->assertSame(
            'SELECT * FROM `users` AS `u` INNER JOIN `orders` AS `o` ON (`u`.`id` = `o`.`user_id`)',
            $builder()->toSql(new DefaultDialect())
        );
        $this->assertSame(
            'SELECT * FROM "users" AS "u" INNER JOIN "orders" AS "o" ON ("u"."id" = "o"."user_id")',
            $builder()->toSql(new PostgresDialect())
        );
        $this->assertSame(
            'SELECT * FROM `users` AS `u` INNER JOIN `orders` AS `o` ON (`u`.`id` = `o`.`user_id`)',
            $builder()->toSql(new MariaDbDialect())
        );
    }

    // --- SELECT projection -----------------------------------------------

    public function testSelectProjectionDefaultPassesThrough(): void {
        $sql = Select::create()
            ->select(Expression::uuidColumn('id', 'user_id'))
            ->from('users')
            ->toSql(new DefaultDialect());

        $this->assertSame('SELECT `id` AS `user_id` FROM `users`', $sql);
    }

    public function testSelectProjectionPostgresPassesThrough(): void {
        $sql = Select::create()
            ->select(Expression::uuidColumn('id', 'user_id'))
            ->from('users')
            ->toSql(new PostgresDialect());

        $this->assertSame('SELECT "id" AS "user_id" FROM "users"', $sql);
    }

    public function testSelectProjectionMariaDbAppliesBinToUuid(): void {
        $sql = Select::create()
            ->select(Expression::uuidColumn('id', 'user_id'))
            ->from('users')
            ->toSql(new MariaDbDialect());

        $this->assertSame(
            'SELECT BIN_TO_UUID(`id`) AS `user_id` FROM `users`',
            $sql
        );
    }

    public function testSelectProjectionMariaDbWithoutAlias(): void {
        $sql = Select::create()
            ->select(Expression::uuidColumn('id'))
            ->from('users')
            ->toSql(new MariaDbDialect());

        $this->assertSame('SELECT BIN_TO_UUID(`id`) FROM `users`', $sql);
    }

    // --- prepare() across dialects ---------------------------------------

    public function testPrepareDefaultEmitsBarePlaceholder(): void {
        $stmt = Select::create()
            ->from('users')
            ->where(Expression::binary(
                'id',
                BinaryOperator::Eq,
                Expression::uuid(Expression::param('uid', self::UUID))
            ))
            ->prepare(new DefaultDialect());

        $this->assertSame(
            'SELECT * FROM `users` WHERE (`id` = :uid)',
            $stmt->sql
        );
        $this->assertSame(['uid' => self::UUID], $stmt->parameters);
    }

    public function testPreparePostgresCastsPlaceholder(): void {
        $stmt = Select::create()
            ->from('users')
            ->where(Expression::binary(
                'id',
                BinaryOperator::Eq,
                Expression::uuid(Expression::param('uid', self::UUID))
            ))
            ->prepare(new PostgresDialect());

        $this->assertSame(
            'SELECT * FROM "users" WHERE ("id" = :uid::uuid)',
            $stmt->sql
        );
        $this->assertSame(['uid' => self::UUID], $stmt->parameters);
    }

    public function testPrepareMariaDbWrapsPlaceholder(): void {
        $stmt = Select::create()
            ->from('users')
            ->where(Expression::binary(
                'id',
                BinaryOperator::Eq,
                Expression::uuid(Expression::param('uid', self::UUID))
            ))
            ->prepare(new MariaDbDialect());

        $this->assertSame(
            'SELECT * FROM `users` WHERE (`id` = UUID_TO_BIN(:uid))',
            $stmt->sql
        );
        $this->assertSame(['uid' => self::UUID], $stmt->parameters);
    }

    public function testPreparePostgresPositionalKey(): void {
        $stmt = Select::create()
            ->from('users')
            ->where(Expression::binary(
                'id',
                BinaryOperator::Eq,
                Expression::uuid(Expression::param(1, self::UUID))
            ))
            ->prepare(new PostgresDialect());

        $this->assertSame(
            'SELECT * FROM "users" WHERE ("id" = $1::uuid)',
            $stmt->sql
        );
        $this->assertSame([self::UUID], $stmt->parameters);
    }

    // --- Postgres no-cast for column-ref inner ---------------------------

    public function testPostgresDoesNotCastWhenInnerIsColumnReference(): void {
        $sql = Select::create()
            ->from('users', 'u')
            ->join('orders', 'o', Expression::binary(
                'u.id',
                BinaryOperator::Eq,
                Expression::uuid(Expression::ref('o.user_id'))
            ))
            ->toSql(new PostgresDialect());

        $this->assertSame(
            'SELECT * FROM "users" AS "u" INNER JOIN "orders" AS "o" ON ("u"."id" = "o"."user_id")',
            $sql
        );
    }
}
