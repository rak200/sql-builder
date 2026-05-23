<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect;

use Rak200\SqlBuilder\Common\Expression\Binary as BinaryExpression;
use Rak200\SqlBuilder\Common\Expression\CaseWhen as CaseExpression;
use Rak200\SqlBuilder\Common\Expression\Column as ColumnExpression;
use Rak200\SqlBuilder\Common\Reference\Column as ColumnReference;
use Rak200\SqlBuilder\Common\Expression\Exists as ExistsExpression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\Expression\Func as FunctionExpression;
use Rak200\SqlBuilder\Common\Join;
use Rak200\SqlBuilder\Common\Order;
use Rak200\SqlBuilder\Common\Expression\Param as ParameterExpression;
use Rak200\SqlBuilder\Common\Expression\Raw as RawExpression;
use Rak200\SqlBuilder\Common\Reference\Identifier as SimpleIdentifier;
use Rak200\SqlBuilder\Common\Expression\Subquery as SubqueryExpression;
use Rak200\SqlBuilder\Common\Reference\Table as TableReference;
use Rak200\SqlBuilder\Common\Expression\Unary as UnaryExpression;
use Rak200\SqlBuilder\Common\Expression\UuidInput as UuidInputExpression;
use Rak200\SqlBuilder\Common\Expression\UuidOutput as UuidOutputExpression;
use Rak200\SqlBuilder\Common\Expression\Value as ValueExpression;
use Rak200\SqlBuilder\Common\Window;
use Rak200\SqlBuilder\Common\Expression\Window as WindowExpression;
use Rak200\SqlBuilder\Ddl\Check;
use Rak200\SqlBuilder\Ddl\Column;
use Rak200\SqlBuilder\Ddl\ForeignKey;
use Rak200\SqlBuilder\Ddl\Index;
use Rak200\SqlBuilder\Ddl\PrimaryKey;
use Rak200\SqlBuilder\Ddl\Schema;
use Rak200\SqlBuilder\Ddl\Sequence;
use Rak200\SqlBuilder\Ddl\Table;
use Rak200\SqlBuilder\Ddl\UniqueKey;
use Rak200\SqlBuilder\Ddl\View;
use Rak200\SqlBuilder\Dialect\Dsn\DsnParser;
use Rak200\SqlBuilder\Dml\Cte;
use Rak200\SqlBuilder\Dml\Delete;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Set;
use Rak200\SqlBuilder\Dml\Update;
use Rak200\SqlBuilder\Prepared\Binder;

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
     * Active parameter binder during a `prepare()` render, or null in
     * inline-rendering mode.
     *
     * Renderers consult this to decide whether to emit a placeholder via
     * the binder or to inline-quote the value through {@see quoteValue()}.
     * It is only non-null on a dialect instance returned by
     * {@see withBinder()} — the singleton from {@see default()} is never
     * mutated.
     */
    public private(set) ?Binder $binder = null;

    /**
     * Factory for the dialect's preferred binder.
     *
     * Default returns the MariaDB/MySQL-shaped {@see Binder} (`?`,
     * positional, no wire-level reuse). Postgres-flavoured dialects override
     * this to return a `$N`-emitting binder with positional reuse.
     *
     * @return Binder A fresh binder, ready to accumulate values.
     */
    public function newBinder(): Binder {
        return new Binder();
    }

    /**
     * Return a clone of this dialect with the given binder attached.
     *
     * Cloning isolates the binder state on a one-shot dialect instance so
     * the process-wide {@see default()} singleton (used by `__toString()`)
     * is never observed in bind mode. Renderer caches on the clone are
     * reset so newly-instantiated renderers point at the clone, not the
     * source dialect.
     *
     * @param Binder|null $binder Binder to attach, or null to clear.
     * @return static A clone configured with the given binder.
     */
    public function withBinder(?Binder $binder): static {
        $clone         = clone $this;
        $clone->binder = $binder;
        return $clone;
    }

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

    /**
     * Resolve a table name before it is quoted.
     *
     * Returns the input unchanged on the default dialect; vendor dialects
     * that lack first-class schema support (e.g.
     * {@see \Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect}) override this
     * to flatten `schema.table` into a single identifier such as
     * `schema_table`, preserving the multi-tenant intent of the caller.
     *
     * Renderers that emit a *table* identifier (CREATE/ALTER TABLE, FROM,
     * JOIN, REFERENCES, ON, CREATE VIEW, CREATE SEQUENCE, …) must run table
     * names through this hook before quoting.
     *
     * @param string $name Table name, optionally schema-qualified.
     * @return string Resolved name suitable for {@see quoteIdentifier()}.
     */
    public function resolveTableName(string $name): string {
        return $name;
    }

    /**
     * Resolve a column reference whose name may include a schema qualifier.
     *
     * Default: identity. MariaDB-style dialects override this to flatten the
     * leading two parts of three-part identifiers (`schema.table.column` →
     * `schema_table.column`) so that schema-simulated tables remain
     * addressable inside expressions.
     *
     * @param string $name Possibly qualified column reference.
     * @return string Resolved identifier suitable for {@see quoteIdentifier()}.
     */
    public function resolveColumnReference(string $name): string {
        return $name;
    }

    // --- DML ----------------------------------------------------------------

    abstract public function renderSelect(Select $component): string;
    abstract public function renderInsert(Insert $component): string;
    abstract public function renderUpdate(Update $component): string;
    abstract public function renderDelete(Delete $component): string;
    abstract public function renderSet(Set $component): string;
    abstract public function renderCte(Cte $component): string;

    // --- DDL ----------------------------------------------------------------

    abstract public function renderTable(Table $component): string;
    abstract public function renderColumn(Column $component): string;
    abstract public function renderView(View $component): string;
    abstract public function renderSequence(Sequence $component): string;
    abstract public function renderIndex(Index $component): string;
    abstract public function renderSchema(Schema $component): string;
    abstract public function renderPrimaryKey(PrimaryKey $component): string;
    abstract public function renderUniqueKey(UniqueKey $component): string;
    abstract public function renderForeignKey(ForeignKey $component): string;
    abstract public function renderCheck(Check $component): string;

    // --- Common -------------------------------------------------------------

    abstract public function renderBinaryExpression(BinaryExpression $component): string;
    abstract public function renderUnaryExpression(UnaryExpression $component): string;
    abstract public function renderCaseExpression(CaseExpression $component): string;
    abstract public function renderColumnExpression(ColumnExpression $component): string;
    abstract public function renderColumnReference(ColumnReference $component): string;
    abstract public function renderValueExpression(ValueExpression $component): string;
    abstract public function renderParameterExpression(ParameterExpression $component): string;
    abstract public function renderUuidInputExpression(UuidInputExpression $component): string;
    abstract public function renderUuidOutputExpression(UuidOutputExpression $component): string;
    abstract public function renderRawExpression(RawExpression $component): string;
    abstract public function renderFunctionExpression(FunctionExpression $component): string;
    abstract public function renderExistsExpression(ExistsExpression $component): string;
    abstract public function renderSubqueryExpression(SubqueryExpression $component): string;
    abstract public function renderSimpleIdentifier(SimpleIdentifier $component): string;
    abstract public function renderTableReference(TableReference $component): string;
    abstract public function renderOrder(Order $component): string;
    abstract public function renderJoin(Join $component): string;
    abstract public function renderWindow(Window $component): string;
    abstract public function renderWindowExpression(WindowExpression $component): string;

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
            $expression instanceof WindowExpression   => $this->renderWindowExpression($expression),
            $expression instanceof BinaryExpression   => $this->renderBinaryExpression($expression),
            $expression instanceof UnaryExpression    => $this->renderUnaryExpression($expression),
            $expression instanceof CaseExpression     => $this->renderCaseExpression($expression),
            $expression instanceof ColumnExpression   => $this->renderColumnExpression($expression),
            $expression instanceof ColumnReference    => $this->renderColumnReference($expression),
            $expression instanceof ValueExpression    => $this->renderValueExpression($expression),
            $expression instanceof ParameterExpression => $this->renderParameterExpression($expression),
            $expression instanceof UuidInputExpression  => $this->renderUuidInputExpression($expression),
            $expression instanceof UuidOutputExpression => $this->renderUuidOutputExpression($expression),
            $expression instanceof RawExpression      => $this->renderRawExpression($expression),
            $expression instanceof FunctionExpression => $this->renderFunctionExpression($expression),
            $expression instanceof SubqueryExpression => $this->renderSubqueryExpression($expression),
            $expression instanceof SimpleIdentifier   => $this->renderSimpleIdentifier($expression),
            $expression instanceof TableReference     => $this->renderTableReference($expression),
            $expression instanceof Select             => $this->renderSelect($expression),
            $expression instanceof Set                => $this->renderSet($expression),
            $expression instanceof Cte                => $this->renderCte($expression),
            $expression instanceof Insert             => $this->renderInsert($expression),
            $expression instanceof Update             => $this->renderUpdate($expression),
            $expression instanceof Delete             => $this->renderDelete($expression),
            $expression instanceof Table              => $this->renderTable($expression),
            $expression instanceof Column             => $this->renderColumn($expression),
            $expression instanceof View               => $this->renderView($expression),
            $expression instanceof Sequence           => $this->renderSequence($expression),
            $expression instanceof Index              => $this->renderIndex($expression),
            $expression instanceof Schema             => $this->renderSchema($expression),
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
