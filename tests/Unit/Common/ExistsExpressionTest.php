<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\ExistsExpression;
use Rak200\SqlBuilder\Common\SubqueryExpression;
use Rak200\SqlBuilder\Dml\Select;

final class ExistsExpressionTest extends TestCase {

    public function testWrapsSubqueryWithExists(): void {
        $select  = Select::create()->select('1')->from('users');
        $expr    = new ExistsExpression(new SubqueryExpression($select));

        $this->assertSame("EXISTS (($select))", (string) $expr);
    }
}
