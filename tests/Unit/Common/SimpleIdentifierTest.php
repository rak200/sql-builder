<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\SimpleIdentifier;

final class SimpleIdentifierTest extends TestCase {

    public function test_accepts_unqualified_name(): void {
        $this->assertSame('`id`', (string) new SimpleIdentifier('id'));
    }

    public function test_rejects_qualified_name(): void {
        $this->expectException(InvalidArgumentException::class);

        new SimpleIdentifier('u.id');
    }

    public function test_rejects_name_with_special_characters(): void {
        $this->expectException(InvalidArgumentException::class);

        new SimpleIdentifier('id;DROP');
    }
}
