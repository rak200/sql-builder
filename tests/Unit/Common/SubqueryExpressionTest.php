<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Common;

use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\SubqueryExpression;
use Rak200\SqlBuilder\Dml\Select;

final class SubqueryExpressionTest extends TestCase {

    public function test_wraps_select_in_parentheses(): void {
        $select  = Select::create()->select('id')->from('users');
        $subquery = new SubqueryExpression($select);

        $this->assertSame("($select)", (string) $subquery);
    }

    public function test_appends_alias_when_provided(): void {
        $select  = Select::create()->select('id')->from('users');
        $subquery = new SubqueryExpression($select, 'u');

        $this->assertSame("($select) AS `u`", (string) $subquery);
    }
}
