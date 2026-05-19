<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\NullsPlacement;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Common\Order;

final class OrderTest extends TestCase {

    public function testDefaultsToAscending(): void {
        $this->assertSame('`name` ASC', (string) new Order('name'));
    }

    public function testDescending(): void {
        $this->assertSame('`name` DESC', (string) new Order('name', SortDirection::DESC));
    }

    public function testNullsFirst(): void {
        $order = new Order('name', SortDirection::ASC, NullsPlacement::FIRST);

        $this->assertSame('`name` ASC NULLS FIRST', (string) $order);
    }

    public function testNullsLastViaFluentSetter(): void {
        $order = (new Order('name', SortDirection::DESC))->nullsLast();

        $this->assertSame('`name` DESC NULLS LAST', (string) $order);
    }

    public function testNullsFirstViaFluentSetter(): void {
        $order = (new Order('name'))->nullsFirst();

        $this->assertSame('`name` ASC NULLS FIRST', (string) $order);
    }
}
