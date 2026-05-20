<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dialect;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\BinaryOperator;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\Window;
use Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresDialect;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Set;

/**
 * Verifies that CTE, window, and CASE expressions inherit each dialect's
 * identifier quoting and value-escape rules when rendered with toSql().
 */
final class SelectExtensionsDialectTest extends TestCase {

    public function testCteRendersWithPostgresQuoting(): void {
        $body = Select::create()->select('id')->from('users');

        $sql = Select::create()
            ->with('user_ids', $body, ['uid'])
            ->select('uid')
            ->from('user_ids')
            ->toSql(new PostgresDialect());

        $this->assertSame(
            'WITH "user_ids" ("uid") AS (SELECT "id" FROM "users") SELECT "uid" FROM "user_ids"',
            $sql
        );
    }

    public function testRecursiveCteSetBodyRendersWithDialect(): void {
        $base = Select::create()->select(Expression::value(1));
        $step = Select::create()->select(Expression::raw('n + 1'))->from('numbers')->where(
            Expression::binary('n', BinaryOperator::Lt, 5)
        );
        $body = Set::create($base)->union($step, all: true);

        $sql = Select::create()
            ->withRecursive('numbers', $body, ['n'])
            ->select('n')
            ->from('numbers')
            ->toSql(new PostgresDialect());

        $this->assertStringStartsWith(
            'WITH RECURSIVE "numbers" ("n") AS ((SELECT 1) UNION ALL (',
            $sql
        );
        $this->assertStringContainsString('"numbers"', $sql);
        $this->assertStringNotContainsString('`', $sql);
    }

    public function testWindowExpressionRendersWithPostgresQuoting(): void {
        $sql = Select::create()
            ->select(
                'user_id',
                Expression::over(
                    Expression::sum('amount'),
                    Window::create()->partitionBy('user_id')->orderBy('paid_at')
                )->as('running_total')
            )
            ->from('payments')
            ->toSql(new PostgresDialect());

        $this->assertSame(
            'SELECT "user_id", SUM("amount") AS "SUM" OVER (PARTITION BY "user_id" ORDER BY "paid_at" ASC) AS "running_total" FROM "payments"',
            $sql
        );
    }

    public function testCaseExpressionRendersWithPostgresQuoting(): void {
        $sql = Select::create()
            ->select(
                'id',
                Expression::case('status')
                    ->when('active', 1)
                    ->when('pending', 0)
                    ->else(-1)
                    ->as('status_code')
            )
            ->from('users')
            ->toSql(new PostgresDialect());

        $this->assertSame(
            'SELECT "id", CASE "status" WHEN \'active\' THEN 1 WHEN \'pending\' THEN 0 ELSE -1 END AS "status_code" FROM "users"',
            $sql
        );
    }

    public function testCteFlattensSchemaPrefixOnMariaDb(): void {
        $body = Select::create()->select('id')->from('reporting.events');

        $sql = Select::create()
            ->with('source', $body)
            ->select('id')
            ->from('source')
            ->toSql(new MariaDbDialect());

        $this->assertStringContainsString('FROM `reporting_events`', $sql);
        $this->assertStringContainsString('WITH `source` AS', $sql);
    }
}
