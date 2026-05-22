<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Prepared;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Prepared\Binder;

final class BinderTest extends TestCase {

    public function testAnonymousBindsAreFreshPlaceholders(): void {
        $binder = new Binder();

        $this->assertSame('?', $binder->bind('a'));
        $this->assertSame('?', $binder->bind('b'));
        $this->assertSame(['a', 'b'], $binder->values());
    }

    public function testPositionalKeyEmitsFreshPlaceholderPerOccurrenceOnDefault(): void {
        $binder = new Binder();

        $this->assertSame('?', $binder->bind(10, 1));
        $this->assertSame('?', $binder->bind(20, 2));
        $this->assertSame('?', $binder->bind(10, 1));
        $this->assertSame('?', $binder->bind(20, 2));

        $this->assertSame([10, 20, 10, 20], $binder->values());
    }

    public function testNamedKeyReusesPlaceholderAndStoresValueOnce(): void {
        $binder = new Binder();

        $this->assertSame(':price', $binder->bind(100, 'price'));
        $this->assertSame(':qtd',   $binder->bind(5,   'qtd'));
        $this->assertSame(':price', $binder->bind(999, 'price'));

        $this->assertSame(['price' => 100, 'qtd' => 5], $binder->values());
    }
}
