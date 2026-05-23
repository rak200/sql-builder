<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Expression\Column as ColumnExpression;
use Rak200\SqlBuilder\Common\Expr as Expression;
use Rak200\SqlBuilder\Common\Expression\Param as ParameterExpression;
use Rak200\SqlBuilder\Common\Expression\UuidInput as UuidInputExpression;
use Rak200\SqlBuilder\Common\Expression\UuidOutput as UuidOutputExpression;
use Rak200\SqlBuilder\Common\Expression\Value as ValueExpression;

final class UuidExpressionTest extends TestCase {

    public function testUuidFactoryNormalizesStringToValueExpression(): void {
        $expr = Expression::uuid('a1b2c3d4-e5f6-7788-99aa-bbccddeeff00');

        $this->assertInstanceOf(UuidInputExpression::class, $expr);
        $this->assertInstanceOf(ValueExpression::class, $expr->inner);
        $this->assertSame('a1b2c3d4-e5f6-7788-99aa-bbccddeeff00', $expr->inner->value);
    }

    public function testUuidFactoryPassesThroughExpressionInterface(): void {
        $param = Expression::param('uid');
        $expr  = Expression::uuid($param);

        $this->assertInstanceOf(UuidInputExpression::class, $expr);
        $this->assertSame($param, $expr->inner);
    }

    public function testUuidColumnFactoryWrapsColumnExpression(): void {
        $expr = Expression::uuidColumn('id', 'user_id');

        $this->assertInstanceOf(UuidOutputExpression::class, $expr);
        $this->assertInstanceOf(ColumnExpression::class, $expr->inner);
        $this->assertSame('id', $expr->inner->name);
        $this->assertSame('user_id', $expr->inner->alias);
    }

    public function testDefaultDialectRendersUuidInputVerbatim(): void {
        $sql = (string) Expression::uuid('a1b2c3d4-e5f6-7788-99aa-bbccddeeff00');

        $this->assertSame("'a1b2c3d4-e5f6-7788-99aa-bbccddeeff00'", $sql);
    }

    public function testDefaultDialectRendersUuidColumnVerbatim(): void {
        $sql = (string) Expression::uuidColumn('id');

        $this->assertSame('`id`', $sql);
    }

    public function testDefaultDialectRendersUuidColumnWithAlias(): void {
        $sql = (string) Expression::uuidColumn('id', 'user_id');

        $this->assertSame('`id` AS `user_id`', $sql);
    }

    public function testUuidInputAcceptsParameterExpression(): void {
        $expr = Expression::uuid(Expression::param('uid'));

        $this->assertInstanceOf(ParameterExpression::class, $expr->inner);
    }
}
