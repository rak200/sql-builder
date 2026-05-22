<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Prepared;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Dialect\Postgres\PostgresBinder;

final class PostgresBinderTest extends TestCase {

    public function testAnonymousBindsAreNumbered(): void {
        $binder = new PostgresBinder();

        $this->assertSame('$1', $binder->bind('a'));
        $this->assertSame('$2', $binder->bind('b'));
        $this->assertSame('$3', $binder->bind('c'));
        $this->assertSame(['a', 'b', 'c'], $binder->values());
    }

    public function testPositionalKeyReusesNumberedPlaceholder(): void {
        $binder = new PostgresBinder();

        $this->assertSame('$1', $binder->bind(100, 1));
        $this->assertSame('$2', $binder->bind(5,   2));
        $this->assertSame('$1', $binder->bind(999, 1));
        $this->assertSame('$2', $binder->bind(888, 2));

        $this->assertSame([100, 5], $binder->values());
    }

    public function testNamedKeyEmitsColonPlaceholderLikeDefaultBinder(): void {
        $binder = new PostgresBinder();

        $this->assertSame(':price', $binder->bind(100, 'price'));
        $this->assertSame(':price', $binder->bind(999, 'price'));

        $this->assertSame(['price' => 100], $binder->values());
    }
}
