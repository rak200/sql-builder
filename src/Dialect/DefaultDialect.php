<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect;

use Rak200\SqlBuilder\Common\BinaryExpression;
use Rak200\SqlBuilder\Common\ColumnExpression;
use Rak200\SqlBuilder\Common\ColumnReference;
use Rak200\SqlBuilder\Common\ExistsExpression;
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
use Rak200\SqlBuilder\Dialect\Renderer\Common\BinaryExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\ColumnExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\ColumnReferenceRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\ExistsExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\FunctionExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\JoinRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\OrderRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\RawExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\SimpleIdentifierRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\SubqueryExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\TableReferenceRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\UnaryExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\ValueExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\CheckRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\ColumnRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\ForeignKeyRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\IndexRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\PrimaryKeyRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\SequenceRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\TableRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\UniqueKeyRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\ViewRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\DeleteRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\InsertRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\SelectRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\SetRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\UpdateRenderer;
use Rak200\SqlBuilder\Dml\Delete;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Dml\Set;
use Rak200\SqlBuilder\Dml\Update;

/**
 * Permissive baseline dialect.
 *
 * Permits every feature the builders expose. Serves as the default for
 * `__toString()` and as the inheritance root for vendor- or version-specific
 * dialects. Renderers are lazily instantiated so subclasses can swap any one
 * of them by overriding its `xxxRenderer()` accessor.
 *
 * @package Rak200\SqlBuilder\Dialect
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class DefaultDialect extends Dialect {

    // --- Common renderers ---------------------------------------------------
    protected ?BinaryExpressionRenderer   $binaryExpressionRenderer   = null;
    protected ?UnaryExpressionRenderer    $unaryExpressionRenderer    = null;
    protected ?ColumnExpressionRenderer   $columnExpressionRenderer   = null;
    protected ?ColumnReferenceRenderer    $columnReferenceRenderer    = null;
    protected ?ValueExpressionRenderer    $valueExpressionRenderer    = null;
    protected ?RawExpressionRenderer      $rawExpressionRenderer      = null;
    protected ?FunctionExpressionRenderer $functionExpressionRenderer = null;
    protected ?ExistsExpressionRenderer   $existsExpressionRenderer   = null;
    protected ?SubqueryExpressionRenderer $subqueryExpressionRenderer = null;
    protected ?SimpleIdentifierRenderer   $simpleIdentifierRenderer   = null;
    protected ?TableReferenceRenderer     $tableReferenceRenderer     = null;
    protected ?OrderRenderer              $orderRenderer              = null;
    protected ?JoinRenderer               $joinRenderer               = null;

    // --- DML renderers ------------------------------------------------------
    protected ?SelectRenderer $selectRenderer = null;
    protected ?InsertRenderer $insertRenderer = null;
    protected ?UpdateRenderer $updateRenderer = null;
    protected ?DeleteRenderer $deleteRenderer = null;
    protected ?SetRenderer    $setRenderer    = null;

    // --- DDL renderers ------------------------------------------------------
    protected ?TableRenderer      $tableRenderer      = null;
    protected ?ColumnRenderer     $columnRenderer     = null;
    protected ?ViewRenderer       $viewRenderer       = null;
    protected ?SequenceRenderer   $sequenceRenderer   = null;
    protected ?IndexRenderer      $indexRenderer      = null;
    protected ?PrimaryKeyRenderer $primaryKeyRenderer = null;
    protected ?UniqueKeyRenderer  $uniqueKeyRenderer  = null;
    protected ?ForeignKeyRenderer $foreignKeyRenderer = null;
    protected ?CheckRenderer      $checkRenderer      = null;

    /** {@inheritdoc} */
    public function quoteIdentifier(string $identifier): string {
        $quoted = '`' . str_replace('.', '`.`', $identifier) . '`';
        return str_replace(['`*`', '``'], ['*', '`'], $quoted);
    }

    /** {@inheritdoc} */
    public function quoteValue(mixed $value): string {
        return match (true) {
            $value === null  => 'NULL',
            is_int($value)   => (string) $value,
            is_float($value) => (string) $value,
            is_bool($value)  => $value ? 'TRUE' : 'FALSE',
            default          => "'" . str_replace(
                ['\\',   "'"],
                ['\\\\', "''"],
                mb_convert_encoding((string) $value, 'UTF-8', 'UTF-8')
            ) . "'",
        };
    }

    // --- DML --------------------------------------------------------------

    public function renderSelect(Select $component): string {
        return $this->selectRenderer()->render($component);
    }

    public function renderInsert(Insert $component): string {
        return $this->insertRenderer()->render($component);
    }

    public function renderUpdate(Update $component): string {
        return $this->updateRenderer()->render($component);
    }

    public function renderDelete(Delete $component): string {
        return $this->deleteRenderer()->render($component);
    }

    public function renderSet(Set $component): string {
        return $this->setRenderer()->render($component);
    }

    // --- DDL --------------------------------------------------------------

    public function renderTable(Table $component): string {
        return $this->tableRenderer()->render($component);
    }

    public function renderColumn(Column $component): string {
        return $this->columnRenderer()->render($component);
    }

    public function renderView(View $component): string {
        return $this->viewRenderer()->render($component);
    }

    public function renderSequence(Sequence $component): string {
        return $this->sequenceRenderer()->render($component);
    }

    public function renderIndex(Index $component): string {
        return $this->indexRenderer()->render($component);
    }

    public function renderPrimaryKey(PrimaryKey $component): string {
        return $this->primaryKeyRenderer()->render($component);
    }

    public function renderUniqueKey(UniqueKey $component): string {
        return $this->uniqueKeyRenderer()->render($component);
    }

    public function renderForeignKey(ForeignKey $component): string {
        return $this->foreignKeyRenderer()->render($component);
    }

    public function renderCheck(Check $component): string {
        return $this->checkRenderer()->render($component);
    }

    // --- Common -----------------------------------------------------------

    public function renderBinaryExpression(BinaryExpression $component): string {
        return $this->binaryExpressionRenderer()->render($component);
    }

    public function renderUnaryExpression(UnaryExpression $component): string {
        return $this->unaryExpressionRenderer()->render($component);
    }

    public function renderColumnExpression(ColumnExpression $component): string {
        return $this->columnExpressionRenderer()->render($component);
    }

    public function renderColumnReference(ColumnReference $component): string {
        return $this->columnReferenceRenderer()->render($component);
    }

    public function renderValueExpression(ValueExpression $component): string {
        return $this->valueExpressionRenderer()->render($component);
    }

    public function renderRawExpression(RawExpression $component): string {
        return $this->rawExpressionRenderer()->render($component);
    }

    public function renderFunctionExpression(FunctionExpression $component): string {
        return $this->functionExpressionRenderer()->render($component);
    }

    public function renderExistsExpression(ExistsExpression $component): string {
        return $this->existsExpressionRenderer()->render($component);
    }

    public function renderSubqueryExpression(SubqueryExpression $component): string {
        return $this->subqueryExpressionRenderer()->render($component);
    }

    public function renderSimpleIdentifier(SimpleIdentifier $component): string {
        return $this->simpleIdentifierRenderer()->render($component);
    }

    public function renderTableReference(TableReference $component): string {
        return $this->tableReferenceRenderer()->render($component);
    }

    public function renderOrder(Order $component): string {
        return $this->orderRenderer()->render($component);
    }

    public function renderJoin(Join $component): string {
        return $this->joinRenderer()->render($component);
    }

    // --- Renderer factories (override-points) -----------------------------

    protected function selectRenderer(): SelectRenderer {
        return $this->selectRenderer ??= new SelectRenderer($this);
    }

    protected function insertRenderer(): InsertRenderer {
        return $this->insertRenderer ??= new InsertRenderer($this);
    }

    protected function updateRenderer(): UpdateRenderer {
        return $this->updateRenderer ??= new UpdateRenderer($this);
    }

    protected function deleteRenderer(): DeleteRenderer {
        return $this->deleteRenderer ??= new DeleteRenderer($this);
    }

    protected function setRenderer(): SetRenderer {
        return $this->setRenderer ??= new SetRenderer($this);
    }

    protected function tableRenderer(): TableRenderer {
        return $this->tableRenderer ??= new TableRenderer($this);
    }

    protected function columnRenderer(): ColumnRenderer {
        return $this->columnRenderer ??= new ColumnRenderer($this);
    }

    protected function viewRenderer(): ViewRenderer {
        return $this->viewRenderer ??= new ViewRenderer($this);
    }

    protected function sequenceRenderer(): SequenceRenderer {
        return $this->sequenceRenderer ??= new SequenceRenderer($this);
    }

    protected function indexRenderer(): IndexRenderer {
        return $this->indexRenderer ??= new IndexRenderer($this);
    }

    protected function primaryKeyRenderer(): PrimaryKeyRenderer {
        return $this->primaryKeyRenderer ??= new PrimaryKeyRenderer($this);
    }

    protected function uniqueKeyRenderer(): UniqueKeyRenderer {
        return $this->uniqueKeyRenderer ??= new UniqueKeyRenderer($this);
    }

    protected function foreignKeyRenderer(): ForeignKeyRenderer {
        return $this->foreignKeyRenderer ??= new ForeignKeyRenderer($this);
    }

    protected function checkRenderer(): CheckRenderer {
        return $this->checkRenderer ??= new CheckRenderer($this);
    }

    protected function binaryExpressionRenderer(): BinaryExpressionRenderer {
        return $this->binaryExpressionRenderer ??= new BinaryExpressionRenderer($this);
    }

    protected function unaryExpressionRenderer(): UnaryExpressionRenderer {
        return $this->unaryExpressionRenderer ??= new UnaryExpressionRenderer($this);
    }

    protected function columnExpressionRenderer(): ColumnExpressionRenderer {
        return $this->columnExpressionRenderer ??= new ColumnExpressionRenderer($this);
    }

    protected function columnReferenceRenderer(): ColumnReferenceRenderer {
        return $this->columnReferenceRenderer ??= new ColumnReferenceRenderer($this);
    }

    protected function valueExpressionRenderer(): ValueExpressionRenderer {
        return $this->valueExpressionRenderer ??= new ValueExpressionRenderer($this);
    }

    protected function rawExpressionRenderer(): RawExpressionRenderer {
        return $this->rawExpressionRenderer ??= new RawExpressionRenderer($this);
    }

    protected function functionExpressionRenderer(): FunctionExpressionRenderer {
        return $this->functionExpressionRenderer ??= new FunctionExpressionRenderer($this);
    }

    protected function existsExpressionRenderer(): ExistsExpressionRenderer {
        return $this->existsExpressionRenderer ??= new ExistsExpressionRenderer($this);
    }

    protected function subqueryExpressionRenderer(): SubqueryExpressionRenderer {
        return $this->subqueryExpressionRenderer ??= new SubqueryExpressionRenderer($this);
    }

    protected function simpleIdentifierRenderer(): SimpleIdentifierRenderer {
        return $this->simpleIdentifierRenderer ??= new SimpleIdentifierRenderer($this);
    }

    protected function tableReferenceRenderer(): TableReferenceRenderer {
        return $this->tableReferenceRenderer ??= new TableReferenceRenderer($this);
    }

    protected function orderRenderer(): OrderRenderer {
        return $this->orderRenderer ??= new OrderRenderer($this);
    }

    protected function joinRenderer(): JoinRenderer {
        return $this->joinRenderer ??= new JoinRenderer($this);
    }
}
