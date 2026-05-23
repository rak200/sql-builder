<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Expression\Raw as RawExpression;
use Rak200\SqlBuilder\Ddl\Sequence;

final class SequenceTest extends TestCase {

    public function testCreateMinimal(): void {
        $sql = (string) Sequence::create('s');

        $this->assertStringStartsWith('CREATE SEQUENCE', $sql);
        $this->assertStringContainsString('s', $sql);
    }

    public function testCreateWithIfNotExists(): void {
        $sql = (string) Sequence::create('s')->ifNotExists();

        $this->assertStringContainsString('IF NOT EXISTS', $sql);
    }

    public function testCreateWithOptions(): void {
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

    public function testNoMinNoMaxNoCacheNoCycle(): void {
        $sql = (string) Sequence::create('s')->noMinValue()->noMaxValue()->noCache()->noCycle();

        $this->assertStringContainsString('NO MINVALUE', $sql);
        $this->assertStringContainsString('NO MAXVALUE', $sql);
        $this->assertStringContainsString('NO CACHE',    $sql);
        $this->assertStringContainsString('NO CYCLE',    $sql);
    }

    public function testIncrementZeroThrows(): void {
        $this->expectException(InvalidArgumentException::class);
        Sequence::create('s')->incrementBy(0);
    }

    public function testCacheMustBePositive(): void {
        $this->expectException(InvalidArgumentException::class);
        Sequence::create('s')->cache(0);
    }

    public function testAlterRequiresOptions(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) Sequence::alter('s');
    }

    public function testAlterWithRestartWithValue(): void {
        $sql = (string) Sequence::alter('s')->restart(500);

        $this->assertStringStartsWith('ALTER SEQUENCE', $sql);
        $this->assertStringContainsString('RESTART WITH 500', $sql);
    }

    public function testAlterWithDefaultRestart(): void {
        $sql = (string) Sequence::alter('s')->restart();

        $this->assertStringEndsWith('RESTART', $sql);
    }

    public function testRestartRequiresAlterMode(): void {
        $this->expectException(InvalidArgumentException::class);
        Sequence::create('s')->restart(1);
    }

    public function testNextValReturnsRawExpression(): void {
        $nextVal = Sequence::create('s')->nextVal();

        $this->assertInstanceOf(RawExpression::class, $nextVal);
        $this->assertStringContainsString('NEXTVAL', (string) $nextVal);
        $this->assertStringContainsString('s', (string) $nextVal);
    }
}
