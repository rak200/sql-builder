<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dml;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\SortDirection;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Set;

final class SetTest extends TestCase {

    private function selectFrom(string $table): Select {
        return Select::create()->select('id')->from($table);
    }

    public function test_create_wraps_single_query_in_parentheses(): void {
        $a   = $this->selectFrom('a');
        $set = Set::create($a);

        $this->assertSame("($a)", (string) $set);
    }

    public function test_union(): void {
        $a = $this->selectFrom('a');
        $b = $this->selectFrom('b');

        $this->assertSame("($a) UNION ($b)", (string) Set::create($a)->union($b));
    }

    public function test_union_all(): void {
        $a = $this->selectFrom('a');
        $b = $this->selectFrom('b');

        $this->assertSame("($a) UNION ALL ($b)", (string) Set::create($a)->union($b, all: true));
    }

    public function test_except(): void {
        $a = $this->selectFrom('a');
        $b = $this->selectFrom('b');

        $this->assertSame("($a) EXCEPT ($b)", (string) Set::create($a)->except($b));
    }

    public function test_intersect(): void {
        $a = $this->selectFrom('a');
        $b = $this->selectFrom('b');

        $this->assertSame("($a) INTERSECT ($b)", (string) Set::create($a)->intersect($b));
    }

    public function test_chained_operators(): void {
        $a = $this->selectFrom('a');
        $b = $this->selectFrom('b');
        $c = $this->selectFrom('c');
        $d = $this->selectFrom('d');

        $sql = (string) Set::create($a)->union($b)->except($c)->intersect($d);

        $this->assertSame("($a) UNION ($b) EXCEPT ($c) INTERSECT ($d)", $sql);
    }

    public function test_order_by_limit_offset_apply_to_combined_result(): void {
        $a = $this->selectFrom('a');
        $b = $this->selectFrom('b');

        $sql = (string) Set::create($a)
            ->union($b)
            ->orderBy('id', SortDirection::DESC)
            ->limit(10)
            ->offset(5);

        $this->assertSame("($a) UNION ($b) ORDER BY `id` DESC LIMIT 10 OFFSET 5", $sql);
    }

    public function test_limit_rejects_negative(): void {
        $this->expectException(InvalidArgumentException::class);
        Set::create($this->selectFrom('a'))->limit(-1);
    }

    public function test_offset_rejects_negative(): void {
        $this->expectException(InvalidArgumentException::class);
        Set::create($this->selectFrom('a'))->offset(-1);
    }
}
