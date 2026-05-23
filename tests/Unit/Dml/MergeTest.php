<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Dml;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Dml\Merge;
use Rak200\SqlBuilder\Dml\Select;

final class MergeTest extends TestCase {

    public function testFullMergeRoundTrip(): void {
        $merge = Merge::create()
            ->into('target', 't')
            ->using('source', 's')
            ->on(Expr::binary('t.id', Binary::Eq, Expr::ref('s.id')))
            ->whenMatchedUpdate(['name' => Expr::ref('s.name')])
            ->whenNotMatchedInsert(['id', 'name'], [Expr::ref('s.id'), Expr::ref('s.name')]);

        $sql = (string) $merge;

        $this->assertSame(
            'MERGE INTO `target` AS `t` USING `source` AS `s` '
            . 'ON (`t`.`id` = `s`.`id`) '
            . 'WHEN MATCHED THEN UPDATE SET `name` = `s`.`name` '
            . 'WHEN NOT MATCHED THEN INSERT (`id`, `name`) VALUES (`s`.`id`, `s`.`name`)',
            $sql
        );
    }

    public function testWhenMatchedDelete(): void {
        $sql = (string) Merge::create()
            ->into('t')->using('s')
            ->on(Expr::raw('TRUE'))
            ->whenMatchedDelete(Expr::binary('s.flag', Binary::Eq, true));

        $this->assertStringContainsString('WHEN MATCHED AND (`s`.`flag` = TRUE) THEN DELETE', $sql);
    }

    public function testWhenDoNothing(): void {
        $sql = (string) Merge::create()
            ->into('t')->using('s')
            ->on(Expr::raw('TRUE'))
            ->whenDoNothing(matched: false);

        $this->assertStringContainsString('WHEN NOT MATCHED THEN DO NOTHING', $sql);
    }

    public function testUsingSubquery(): void {
        $source = Select::create()->select('id')->from('staging');

        $sql = (string) Merge::create()
            ->into('t')->using($source, 'src')
            ->on(Expr::raw('TRUE'))
            ->whenMatchedDelete();

        $this->assertStringContainsString('USING (SELECT `id` FROM `staging`) AS `src`', $sql);
    }

    public function testInsertColumnValueMismatchRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        Merge::create()->whenNotMatchedInsert(['a', 'b'], [1]);
    }

    public function testMissingTargetRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Merge::create()->using('s')->on(Expr::raw('TRUE'))->whenMatchedDelete();
    }

    public function testMissingClausesRejected(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Merge::create()->into('t')->using('s')->on(Expr::raw('TRUE'));
    }

    public function testReturning(): void {
        $sql = (string) Merge::create()
            ->into('t')->using('s')
            ->on(Expr::raw('TRUE'))
            ->whenMatchedDelete()
            ->returning('id');

        $this->assertStringContainsString('RETURNING `id`', $sql);
    }
}
