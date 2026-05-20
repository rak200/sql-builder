<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect;

use Rak200\SqlBuilder\Common\BinaryExpression;
use Rak200\SqlBuilder\Common\ColumnExpression;
use Rak200\SqlBuilder\Common\ColumnReference;
use Rak200\SqlBuilder\Common\ExistsExpression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\FunctionExpression;
use Rak200\SqlBuilder\Common\Join;
use Rak200\SqlBuilder\Common\Order;
use Rak200\SqlBuilder\Common\RawExpression;
use Rak200\SqlBuilder\Common\SimpleIdentifier;
use Rak200\SqlBuilder\Common\SubqueryExpression;
use Rak200\SqlBuilder\Common\TableReference;
use Rak200\SqlBuilder\Common\UnaryExpression;
use Rak200\SqlBuilder\Common\ValueExpression;
use Rak200\SqlBuilder\Ddl\Check;
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\ForeignKey;
use Rak200\SqlBuilder\Ddl\Index;
use Rak200\SqlBuilder\Ddl\PrimaryKey;
use Rak200\SqlBuilder\Ddl\Sequence;
use Rak200\SqlBuilder\Ddl\Table;
use Rak200\SqlBuilder\Ddl\UniqueKey;
use Rak200\SqlBuilder\Ddl\View;
use Rak200\SqlBuilder\Dialect\Dsn\DsnParser;
use Rak200\SqlBuilder\Dml\Delete;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Set;
use Rak200\SqlBuilder\Dml\Update;

/**
 * Abstract base for SQL dialects.
 *
 * A dialect knows how to render every builder component to a SQL string. The
 * default dialect ({@see DefaultDialect}) permits every feature; database- and
 * version-specific dialects subclass it and override only the renderers that
 * need to deviate (e.g. PostgreSQL uses double-quoted identifiers and rejects
 * `ON DUPLICATE KEY UPDATE`).
 *
 * Dialects are stateless after construction and may be shared safely. The
 * default singleton is exposed via {@see Dialect::default()}; runtime
 * selection from a PDO-style DSN is provided by {@see Dialect::fromDsn()}.
 *
 * @package Rak200\SqlBuilder\Dialect
 * @author rak200 <rak.ricardo@windowslive.com>
 */
abstract class Dialect {

    private static ?Dialect $default = null;

    /**
     * Quote a SQL identifier (column name, table name, etc).
     *
     * @param string $identifier The identifier to quote.
     * @return string The quoted identifier.
     */
    abstract public function quoteIdentifier(string $identifier): string;

    /**
     * Quote a SQL value for safe inclusion in SQL queries.
     *
     * @param mixed $value The value to quote.
     * @return string The quoted value.
     */
    abstract public function quoteValue(mixed $value): string;

    // --- DML ----------------------------------------------------------------

    abstract public function renderSelect(Select $component): string;
    abstract public function renderInsert(Insert $component): string;
    abstract public function renderUpdate(Update $component): string;
    abstract public function renderDelete(Delete $component): string;
    abstract public function renderSet(Set $component): string;

    // --- DDL ----------------------------------------------------------------

    abstract public function renderTable(Table $component): string;
    abstract public function renderColumn(Column $component): string;
    abstract public function renderView(View $component): string;
    abstract public function renderSequence(Sequence $component): string;
    abstract public function renderIndex(Index $component): string;
    abstract public function renderPrimaryKey(PrimaryKey $component): string;
    abstract public function renderUniqueKey(UniqueKey $component): string;
    abstract public function renderForeignKey(ForeignKey $component): string;
    abstract public function renderCheck(Check $component): string;

    // --- Common -------------------------------------------------------------

    abstract public function renderBinaryExpression(BinaryExpression $component): string;
    abstract public function renderUnaryExpression(UnaryExpression $component): string;
    abstract public function renderColumnExpression(ColumnExpression $component): string;
    abstract public function renderColumnReference(ColumnReference $component): string;
    abstract public function renderValueExpression(ValueExpression $component): string;
    abstract public function renderRawExpression(RawExpression $component): string;
    abstract public function renderFunctionExpression(FunctionExpression $component): string;
    abstract public function renderExistsExpression(ExistsExpression $component): string;
    abstract public function renderSubqueryExpression(SubqueryExpression $component): string;
    abstract public function renderSimpleIdentifier(SimpleIdentifier $component): string;
    abstract public function renderTableReference(TableReference $component): string;
    abstract public function renderOrder(Order $component): string;
    abstract public function renderJoin(Join $component): string;

    /**
     * Polymorphic dispatch by concrete expression type.
     *
     * Used by renderers to render nested {@see ExpressionInterface} instances
     * without each one duplicating the type-to-renderer switch.
     *
     * @param ExpressionInterface $expression Any builder component.
     * @return string SQL produced by the matching typed renderer.
     */
    public function renderExpression(ExpressionInterface $expression): string {
        return match (true) {
            $expression instanceof ExistsExpression   => $this->renderExistsExpression($expression),
            $expression instanceof BinaryExpression   => $this->renderBinaryExpression($expression),
            $expression instanceof UnaryExpression    => $this->renderUnaryExpression($expression),
            $expression instanceof ColumnExpression   => $this->renderColumnExpression($expression),
            $expression instanceof ColumnReference    => $this->renderColumnReference($expression),
            $expression instanceof ValueExpression    => $this->renderValueExpression($expression),
            $expression instanceof RawExpression      => $this->renderRawExpression($expression),
            $expression instanceof FunctionExpression => $this->renderFunctionExpression($expression),
            $expression instanceof SubqueryExpression => $this->renderSubqueryExpression($expression),
            $expression instanceof SimpleIdentifier   => $this->renderSimpleIdentifier($expression),
            $expression instanceof TableReference     => $this->renderTableReference($expression),
            $expression instanceof Select             => $this->renderSelect($expression),
            $expression instanceof Set                => $this->renderSet($expression),
            $expression instanceof Insert             => $this->renderInsert($expression),
            $expression instanceof Update             => $this->renderUpdate($expression),
            $expression instanceof Delete             => $this->renderDelete($expression),
            $expression instanceof Table              => $this->renderTable($expression),
            $expression instanceof Column             => $this->renderColumn($expression),
            $expression instanceof View               => $this->renderView($expression),
            $expression instanceof Sequence           => $this->renderSequence($expression),
            $expression instanceof Index              => $this->renderIndex($expression),
            $expression instanceof PrimaryKey         => $this->renderPrimaryKey($expression),
            $expression instanceof UniqueKey          => $this->renderUniqueKey($expression),
            $expression instanceof ForeignKey         => $this->renderForeignKey($expression),
            $expression instanceof Check              => $this->renderCheck($expression),
            default                                   => (string) $expression,
        };
    }

    /**
     * Default singleton, lazily instantiated.
     *
     * @return self The shared {@see DefaultDialect} instance.
     */
    public static function default(): self {
        return self::$default ??= new DefaultDialect();
    }

    /**
     * Resolve a dialect from a PDO-style DSN.
     *
     * Unknown schemes fall back to {@see DefaultDialect}. A `version`
     * query-string parameter, when recognised, selects a version-specific
     * subclass (e.g. `mariadb://h/db?version=10.5` → MariaDb105).
     *
     * @param string $dsn The DSN string.
     * @return self The matching dialect.
     */
    public static function fromDsn(string $dsn): self {
        return DsnParser::parse($dsn);
    }
}
