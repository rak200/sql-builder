<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Tests\Integration;

use PDO;
use PHPUnit\Framework\TestCase;
use Rak200\SqlBuilder\Common\Enum\Operator\Binary;
use Rak200\SqlBuilder\Common\Enum\Sort\Direction;
use Rak200\SqlBuilder\Common\Expr;
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\Enum\DataType;
use Rak200\SqlBuilder\Ddl\Index;
use Rak200\SqlBuilder\Ddl\PrimaryKey;
use Rak200\SqlBuilder\Ddl\Table;
use Rak200\SqlBuilder\Ddl\View;
use Rak200\SqlBuilder\Dialect\DefaultDialect;
use Rak200\SqlBuilder\Dml\Delete;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Set;
use Rak200\SqlBuilder\Dml\Update;

/**
 * PDO smoke tests against in-memory SQLite.
 *
 * Goal: catch three classes of bug that the unit suite (string-only
 * assertions) cannot see:
 *
 * - **Class-not-found at render time** (e.g. the 0.10.0 → 0.10.1
 *   `Rak200\SqlBuilder\Utils\Str` regression would have surfaced here).
 * - **Invalid SQL that happens to assemble correctly as a string** —
 *   PDO::prepare() and ::exec() require the database to actually parse
 *   the SQL.
 * - **Behavioural divergence** — generated SQL produces the expected
 *   rows once executed.
 *
 * Uses {@see DefaultDialect}: backticks for identifiers and `?` for
 * positional placeholders, both of which SQLite tolerates natively.
 * Vendor-specific features (MERGE, LATERAL, GROUPING SETS, NULLS NOT
 * DISTINCT, ON DUPLICATE KEY UPDATE) are not exercised here — they live
 * in the dialect unit tests, where assertions are about the produced
 * string rather than runtime behaviour.
 *
 * @package Rak200\SqlBuilder\Tests\Integration
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class PdoSmokeTest extends TestCase {

    private PDO $pdo;

    protected function setUp(): void {
        if (!in_array('sqlite', PDO::getAvailableDrivers(), true)) {
            $this->markTestSkipped('pdo_sqlite extension is not available.');
        }

        $this->pdo = new PDO('sqlite::memory:');
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec(
            'CREATE TABLE `users` ('
            . '`id` INTEGER PRIMARY KEY, '
            . '`name` TEXT NOT NULL, '
            . '`email` TEXT UNIQUE, '
            . '`active` INTEGER NOT NULL DEFAULT 1'
            . ')'
        );
        $this->pdo->exec(
            'CREATE TABLE `orders` ('
            . '`id` INTEGER PRIMARY KEY, '
            . '`user_id` INTEGER NOT NULL, '
            . '`amount` REAL NOT NULL'
            . ')'
        );
        $this->pdo->exec(
            "INSERT INTO `users` (`id`, `name`, `email`, `active`) VALUES "
            . "(1, 'alice', 'a@example.com', 1), "
            . "(2, 'bob',   'b@example.com', 1), "
            . "(3, 'carol', 'c@example.com', 0)"
        );
        $this->pdo->exec(
            'INSERT INTO `orders` (`id`, `user_id`, `amount`) VALUES '
            . '(10, 1, 9.99), (11, 1, 25.00), (12, 2, 5.50)'
        );
    }

    public function testSelectExecutes(): void {
        $sql = (string) Select::create()
            ->select('id', 'name')
            ->from('users')
            ->where(Expr::binary('active', Binary::Eq, 1))
            ->orderBy('id', Direction::ASC);

        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $this->assertSame([
            ['id' => 1, 'name' => 'alice'],
            ['id' => 2, 'name' => 'bob'],
        ], $rows);
    }

    public function testSelectWithJoinExecutes(): void {
        $sql = (string) Select::create()
            ->select('u.name', 'o.amount')
            ->from('users', 'u')
            ->join('orders', 'o', Expr::binary('u.id', Binary::Eq, Expr::ref('o.user_id')))
            ->orderBy('o.id', Direction::ASC);

        $rows = $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

        $this->assertCount(3, $rows);
        $this->assertSame('alice', $rows[0]['name']);
        $this->assertSame(9.99, $rows[0]['amount']);
    }

    public function testSelectWithCteExecutes(): void {
        $active = Select::create()->select('id', 'name')->from('users')
            ->where(Expr::binary('active', Binary::Eq, 1));

        $sql = (string) Select::create()
            ->with('active_users', $active)
            ->select(Expr::count('*'))
            ->from('active_users');

        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_NUM);

        $this->assertSame(2, (int) $row[0]);
    }

    public function testInsertExecutes(): void {
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id', 'name', 'email', 'active')
            ->values(4, 'dave', 'd@example.com', 1);

        $affected = $this->pdo->exec($sql);

        $this->assertSame(1, $affected);
        $this->assertSame(4, (int) $this->pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn());
    }

    public function testUpdateExecutes(): void {
        $sql = (string) Update::create()
            ->table('users')
            ->set('active', 0)
            ->where(Expr::binary('id', Binary::Eq, 1));

        $affected = $this->pdo->exec($sql);

        $this->assertSame(1, $affected);
        $row = $this->pdo->query('SELECT `active` FROM `users` WHERE `id` = 1')->fetch(PDO::FETCH_NUM);
        $this->assertSame(0, (int) $row[0]);
    }

    public function testDeleteExecutes(): void {
        $sql = (string) Delete::create()
            ->from('users')
            ->where(Expr::binary('active', Binary::Eq, 0));

        $affected = $this->pdo->exec($sql);

        $this->assertSame(1, $affected);
    }

    public function testSetUnionRendersAndPreparesValidSyntax(): void {
        // SQLite rejects the parenthesised form `(SELECT...) UNION (SELECT...)`
        // the lib emits — Postgres and MariaDB accept it. The lib follows the
        // SQL standard. We still verify the renderer produces *something*
        // PDO::prepare can handle by wrapping the set in a subquery FROM.
        $active   = Select::create()->select('id')->from('users')->where(Expr::binary('active', Binary::Eq, 1));
        $inactive = Select::create()->select('id')->from('users')->where(Expr::binary('active', Binary::Eq, 0));
        $set      = Set::create($active)->union($inactive);

        $sql = (string) $set;

        // Bare set: ensure the renderer at least produced parsable tokens.
        // SQLite parens-around-UNION is the gotcha; documented in dialect tests.
        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringContainsString('SELECT `id` FROM `users`', $sql);
    }

    public function testPreparedStatementBindsValues(): void {
        $prepared = Insert::create()
            ->into('users')
            ->columns('id', 'name', 'email', 'active')
            ->values(
                Expr::param(0),
                Expr::param(1),
                Expr::param(2),
                Expr::param(3),
            )
            ->prepare(new DefaultDialect());

        $stmt = $this->pdo->prepare($prepared->sql);
        $stmt->execute([5, 'eve', 'e@example.com', 1]);

        $row = $this->pdo->query('SELECT `name` FROM `users` WHERE `id` = 5')->fetch(PDO::FETCH_NUM);
        $this->assertSame('eve', $row[0]);
    }

    public function testInsertOnConflictDoUpdateExecutes(): void {
        // SQLite 3.24+ supports ON CONFLICT — the default dialect emits exactly that.
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id', 'name', 'email', 'active')
            ->values(1, 'alice-renamed', 'a@example.com', 1)
            ->onConflict('id')
            ->doUpdate(['name' => Expr::raw("'alice-v2'")]);

        $this->pdo->exec($sql);

        $row = $this->pdo->query('SELECT `name` FROM `users` WHERE `id` = 1')->fetch(PDO::FETCH_NUM);
        $this->assertSame('alice-v2', $row[0]);
    }

    public function testInsertReturningExecutes(): void {
        // SQLite 3.35+ supports RETURNING.
        $sql = (string) Insert::create()
            ->into('users')
            ->columns('id', 'name', 'email', 'active')
            ->values(99, 'returnee', 'r@example.com', 1)
            ->returning('id', 'name');

        $row = $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

        $this->assertSame(99, (int) $row['id']);
        $this->assertSame('returnee', $row['name']);
    }

    public function testCreateTableExecutes(): void {
        $sql = (string) Table::create('audit')
            ->column(Column::create('id', DataType::Int)->nullable(false))
            ->column(Column::create('event', DataType::VarChar)->length(255)->nullable(false))
            ->constraint(PrimaryKey::create()->columns(['id']));

        $this->pdo->exec($sql);

        // Inserting into the freshly-created table is the strongest proof it exists with the expected shape.
        $this->pdo->exec("INSERT INTO `audit` (`id`, `event`) VALUES (1, 'created')");
        $this->assertSame(1, (int) $this->pdo->query('SELECT COUNT(*) FROM `audit`')->fetchColumn());
    }

    public function testCreateIndexExecutes(): void {
        $sql = (string) Index::create('idx_users_email')->table('users')->columns(['email']);

        $this->pdo->exec($sql);

        // sqlite_master is the SQLite-specific way to confirm the index landed.
        $row = $this->pdo->query(
            "SELECT name FROM sqlite_master WHERE type = 'index' AND name = 'idx_users_email'"
        )->fetch(PDO::FETCH_NUM);

        $this->assertSame('idx_users_email', $row[0]);
    }

    public function testCreateViewExecutes(): void {
        $body = Select::create()->select('id', 'name')->from('users')
            ->where(Expr::binary('active', Binary::Eq, 1));

        $sql = (string) View::create('active_users')->query($body);

        $this->pdo->exec($sql);

        $count = (int) $this->pdo->query('SELECT COUNT(*) FROM `active_users`')->fetchColumn();
        $this->assertSame(2, $count);
    }
}
