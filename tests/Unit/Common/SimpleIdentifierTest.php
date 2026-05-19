<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\SimpleIdentifier;

final class SimpleIdentifierTest extends TestCase {

    public function testAcceptsUnqualifiedName(): void {
        $this->assertSame('`id`', (string) new SimpleIdentifier('id'));
    }

    public function testRejectsQualifiedName(): void {
        $this->expectException(InvalidArgumentException::class);

        new SimpleIdentifier('u.id');
    }

    public function testRejectsNameWithSpecialCharacters(): void {
        $this->expectException(InvalidArgumentException::class);

        new SimpleIdentifier('id;DROP');
    }
}
