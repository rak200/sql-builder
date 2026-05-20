<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\Window;

final class WindowTest extends TestCase {

    public function testEmptyWindowRendersBareParentheses(): void {
        $expr = Expression::over(Expression::sum('amount'), Window::create());
        $this->assertSame('SUM(`amount`) AS `SUM` OVER ()', (string) $expr);
    }

    public function testPartitionByOnly(): void {
        $expr = Expression::over(
            Expression::avg('price'),
            Window::create()->partitionBy('category_id')
        );

        $this->assertSame(
            'AVG(`price`) AS `AVG` OVER (PARTITION BY `category_id`)',
            (string) $expr
        );
    }

    public function testOrderByOnly(): void {
        $expr = Expression::over(
            Expression::func('ROW_NUMBER'),
            Window::create()->orderBy('created_at', SortDirection::DESC)
        );

        $this->assertSame(
            'ROW_NUMBER() OVER (ORDER BY `created_at` DESC)',
            (string) $expr
        );
    }

    public function testPartitionByAndOrderByCombined(): void {
        $expr = Expression::over(
            Expression::sum('amount'),
            Window::create()
                ->partitionBy('user_id')
                ->orderBy('paid_at')
        );

        $this->assertSame(
            'SUM(`amount`) AS `SUM` OVER (PARTITION BY `user_id` ORDER BY `paid_at` ASC)',
            (string) $expr
        );
    }

    public function testRowsBetweenFrame(): void {
        $expr = Expression::over(
            Expression::sum('amount'),
            Window::create()
                ->partitionBy('user_id')
                ->orderBy('paid_at')
                ->rows('BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW')
        );

        $this->assertStringEndsWith(
            'ROWS BETWEEN UNBOUNDED PRECEDING AND CURRENT ROW)',
            (string) $expr
        );
    }

    public function testRangeAndGroupsFramesPrependKeyword(): void {
        $range  = Window::create()->orderBy('x')->range('BETWEEN 5 PRECEDING AND CURRENT ROW');
        $groups = Window::create()->orderBy('x')->groups('UNBOUNDED PRECEDING');

        $this->assertStringContainsString('RANGE BETWEEN 5 PRECEDING AND CURRENT ROW',  (string) $range);
        $this->assertStringContainsString('GROUPS UNBOUNDED PRECEDING',                 (string) $groups);
    }

    public function testRawFrameClause(): void {
        $win = Window::create()->orderBy('x')->frame('ROWS UNBOUNDED PRECEDING');
        $this->assertStringEndsWith('ROWS UNBOUNDED PRECEDING)', (string) $win);
    }

    public function testWindowExpressionAlias(): void {
        $expr = Expression::over(
            Expression::sum('amount'),
            Window::create()->partitionBy('user_id')
        )->as('running_total');

        $this->assertStringEndsWith(' AS `running_total`', (string) $expr);
    }

    public function testMultipleArgumentsToFunctionExpression(): void {
        $expr = Expression::over(
            Expression::func('LAG', Expression::ref('amount'), 1, 0),
            Window::create()->partitionBy('user_id')->orderBy('paid_at')
        );

        $this->assertSame(
            'LAG(`amount`, 1, 0) OVER (PARTITION BY `user_id` ORDER BY `paid_at` ASC)',
            (string) $expr
        );
    }
}
