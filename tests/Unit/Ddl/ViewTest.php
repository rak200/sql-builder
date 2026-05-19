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

    public function test_requires_query(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) View::create('v');
    }

    public function test_or_replace_and_if_not_exists_are_mutually_exclusive(): void {
        $this->expectException(InvalidArgumentException::class);
        (string) View::create('v')->orReplace()->ifNotExists()->query($this->someSelect());
    }

    public function test_minimal_create_view_includes_select_body(): void {
        $select = $this->someSelect();
        $sql    = (string) View::create('active_users')->query($select);

        $this->assertStringStartsWith('CREATE VIEW', $sql);
        $this->assertStringContainsString('active_users', $sql);
        $this->assertStringContainsString('AS ' . $select, $sql);
    }

    public function test_or_replace_emits_keyword(): void {
        $sql = (string) View::create('v')->orReplace()->query($this->someSelect());

        $this->assertStringContainsString('CREATE OR REPLACE', $sql);
    }

    public function test_temporary_emits_keyword(): void {
        $sql = (string) View::create('v')->temporary()->query($this->someSelect());

        $this->assertStringContainsString('TEMPORARY VIEW', $sql);
    }

    public function test_if_not_exists_emits_keyword(): void {
        $sql = (string) View::create('v')->ifNotExists()->query($this->someSelect());

        $this->assertStringContainsString('IF NOT EXISTS', $sql);
    }

    public function test_explicit_column_list(): void {
        $sql = (string) View::create('v')->columns('id', 'name')->query($this->someSelect());

        $this->assertStringContainsString('("id", "name")', $sql);
    }

    public function test_with_check_option_default(): void {
        $sql = (string) View::create('v')->query($this->someSelect())->withCheckOption();

        $this->assertStringEndsWith('WITH CHECK OPTION', $sql);
    }

    public function test_with_check_option_cascaded(): void {
        $sql = (string) View::create('v')->query($this->someSelect())->withCheckOption(CheckOption::CASCADED);

        $this->assertStringEndsWith('WITH CASCADED CHECK OPTION', $sql);
    }

    public function test_with_check_option_local(): void {
        $sql = (string) View::create('v')->query($this->someSelect())->withCheckOption(CheckOption::LOCAL);

        $this->assertStringEndsWith('WITH LOCAL CHECK OPTION', $sql);
    }
}
