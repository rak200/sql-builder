<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\RawExpression;
use Rak200\SqlBuilder\Ddl\Sequence;

final class SequenceTest extends TestCase {

    public function test_create_minimal(): void {
        $sql = (string) Sequence::create('s');

        $this->assertStringStartsWith('CREATE SEQUENCE', $sql);
        $this->assertStringContainsString('s', $sql);
    }

    public function test_create_with_if_not_exists(): void {
        $sql = (string) Sequence::create('s')->ifNotExists();

        $this->assertStringContainsString('IF NOT EXISTS', $sql);
    }

    public function test_create_with_options(): void {
        $sql = (string) Sequence::create('s')
            ->startWith(100)
            ->incrementBy(2)
            ->minValue(10)
            ->maxValue(1000)
            ->cache(20)
            ->cycle();

        $this->assertStringContainsString('START WITH 100',  $sql);
        $this->assertStringContainsString('INCREMENT BY 2',  $sql);
        $this->assertStringContainsString('MINVALUE 10',     $sql);
        $this->assertStringContainsString('MAXVALUE 1000',   $sql);
        $this->assertStringContainsString('CACHE 20',        $sql);
        $this->assertStringContainsString('CYCLE',           $sql);
    }

    public function test_no_min_no_max_no_cache_no_cycle(): void {
        $sql = (string) Sequence::create('s')->noMinValue()->noMaxValue()->noCache()->noCycle();

        $this->assertStringContainsString('NO MINVALUE', $sql);
        $this->assertStringContainsString('NO MAXVALUE', $sql);
        $this->assertStringContainsString('NO CACHE',    $sql);
        $this->assertStringContainsString('NO CYCLE',    $sql);
    }

    public function test_increment_zero_throws(): void {
        $this->expectException(InvalidArgumentException::class);
        Sequence::create('s')->incrementBy(0);
    }

    public function test_cache_must_be_positive(): void {
        $this->expectException(InvalidArgumentException::class);
        Sequence::create('s')->cache(0);
    }

    public function test_alter_requires_options(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Sequence::alter('s');
    }

    public function test_alter_with_restart_with_value(): void {
        $sql = (string) Sequence::alter('s')->restart(500);

        $this->assertStringStartsWith('ALTER SEQUENCE', $sql);
        $this->assertStringContainsString('RESTART WITH 500', $sql);
    }

    public function test_alter_with_default_restart(): void {
        $sql = (string) Sequence::alter('s')->restart();

        $this->assertStringEndsWith('RESTART', $sql);
    }

    public function test_restart_requires_alter_mode(): void {
        $this->expectException(InvalidArgumentException::class);
        Sequence::create('s')->restart(1);
    }

    public function test_next_val_returns_raw_expression(): void {
        $nextVal = Sequence::create('s')->nextVal();

        $this->assertInstanceOf(RawExpression::class, $nextVal);
        $this->assertStringContainsString('NEXTVAL', (string) $nextVal);
        $this->assertStringContainsString('s', (string) $nextVal);
    }
}
