<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Unit\Ddl;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\CheckOption;
use Rak200\SqlBuilder\Ddl\View;
use Rak200\SqlBuilder\Dml\Select;

final class ViewTest extends TestCase {

    private function someSelect(): Select {
        return Select::create()->select('id')->from('users');
    }

    public function testRequiresQuery(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) View::create('v');
    }

    public function testOrReplaceAndIfNotExistsAreMutuallyExclusive(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) View::create('v')->orReplace()->ifNotExists()->query($this->someSelect());
    }

    public function testMinimalCreateViewIncludesSelectBody(): void {
        $select = $this->someSelect();
        $sql    = (string) View::create('active_users')->query($select);

        $this->assertStringStartsWith('CREATE VIEW', $sql);
        $this->assertStringContainsString('active_users', $sql);
        $this->assertStringContainsString('AS ' . $select, $sql);
    }

    public function testOrReplaceEmitsKeyword(): void {
        $sql = (string) View::create('v')->orReplace()->query($this->someSelect());

        $this->assertStringContainsString('CREATE OR REPLACE', $sql);
    }

    public function testTemporaryEmitsKeyword(): void {
        $sql = (string) View::create('v')->temporary()->query($this->someSelect());

        $this->assertStringContainsString('TEMPORARY VIEW', $sql);
    }

    public function testIfNotExistsEmitsKeyword(): void {
        $sql = (string) View::create('v')->ifNotExists()->query($this->someSelect());

        $this->assertStringContainsString('IF NOT EXISTS', $sql);
    }

    public function testExplicitColumnList(): void {
        $sql = (string) View::create('v')->columns('id', 'name')->query($this->someSelect());

        $this->assertStringContainsString('("id", "name")', $sql);
    }

    public function testWithCheckOptionDefault(): void {
        $sql = (string) View::create('v')->query($this->someSelect())->withCheckOption();

        $this->assertStringEndsWith('WITH CHECK OPTION', $sql);
    }

    public function testWithCheckOptionCascaded(): void {
        $sql = (string) View::create('v')->query($this->someSelect())->withCheckOption(CheckOption::CASCADED);

        $this->assertStringEndsWith('WITH CASCADED CHECK OPTION', $sql);
    }

    public function testWithCheckOptionLocal(): void {
        $sql = (string) View::create('v')->query($this->someSelect())->withCheckOption(CheckOption::LOCAL);

        $this->assertStringEndsWith('WITH LOCAL CHECK OPTION', $sql);
    }
}
