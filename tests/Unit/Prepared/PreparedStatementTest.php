<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Prepared;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Prepared\PreparedStatement;

final class PreparedStatementTest extends TestCase {

    public function testExposesSqlAndParameters(): void {
        $stmt = new PreparedStatement('SELECT * FROM `t` WHERE `id` = ?', [42]);

        $this->assertSame('SELECT * FROM `t` WHERE `id` = ?', $stmt->sql);
        $this->assertSame([42], $stmt->parameters);
    }

    public function testParametersAreMutableForRebinding(): void {
        $stmt = new PreparedStatement('SELECT * FROM `t` WHERE `id` = ?', [42]);
        $stmt->parameters = [99];

        $this->assertSame([99], $stmt->parameters);
    }
}
