<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect;

use Rak200\SqlBuilder\Common\Expression\Binary as BinaryExpression;
use Rak200\SqlBuilder\Common\Expression\CaseWhen as CaseExpression;
use Rak200\SqlBuilder\Common\Expression\Column as ColumnExpression;
use Rak200\SqlBuilder\Common\Reference\Column as ColumnReference;
use Rak200\SqlBuilder\Common\Expression\Exists as ExistsExpression;
use Rak200\SqlBuilder\Common\Expression\Func as FunctionExpression;
use Rak200\SqlBuilder\Common\Expression\Grouping as GroupingExpression;
use Rak200\SqlBuilder\Common\Join;
use Rak200\SqlBuilder\Common\Order;
use Rak200\SqlBuilder\Common\Expression\Raw as RawExpression;
use Rak200\SqlBuilder\Common\Reference\Identifier as SimpleIdentifier;
use Rak200\SqlBuilder\Common\Expression\Subquery as SubqueryExpression;
use Rak200\SqlBuilder\Common\Reference\Table as TableReference;
use Rak200\SqlBuilder\Common\Expression\Unary as UnaryExpression;
use Rak200\SqlBuilder\Common\Expression\Param as ParameterExpression;
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
use Rak200\SqlBuilder\Dialect\Renderer\Common\BinaryExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\CaseExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\ColumnExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\ColumnReferenceRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\ExistsExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\FunctionExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\GroupingExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\JoinRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\OrderRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\RawExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\SimpleIdentifierRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\SubqueryExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\TableReferenceRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\UnaryExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\ParameterExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\UuidInputExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\UuidOutputExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\ValueExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\WindowExpressionRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Common\WindowRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\CheckRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\ColumnRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\ForeignKeyRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\IndexRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\PrimaryKeyRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\SchemaRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\SequenceRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\TableRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\UniqueKeyRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Ddl\ViewRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\CteRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\DeleteRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\InsertRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\MergeRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\SelectRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\SetRenderer;
use Rak200\SqlBuilder\Dialect\Renderer\Dml\UpdateRenderer;
use Rak200\SqlBuilder\Dml\Cte;
use Rak200\SqlBuilder\Dml\Delete;
use Rak200\SqlBuilder\Dml\Insert;
use Rak200\SqlBuilder\Dml\Merge;
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
    protected ?CaseExpressionRenderer     $caseExpressionRenderer     = null;
    protected ?ColumnExpressionRenderer   $columnExpressionRenderer   = null;
    protected ?ColumnReferenceRenderer    $columnReferenceRenderer    = null;
    protected ?ValueExpressionRenderer    $valueExpressionRenderer    = null;
    protected ?ParameterExpressionRenderer $parameterExpressionRenderer = null;
    protected ?UuidInputExpressionRenderer  $uuidInputExpressionRenderer  = null;
    protected ?UuidOutputExpressionRenderer $uuidOutputExpressionRenderer = null;
    protected ?RawExpressionRenderer      $rawExpressionRenderer      = null;
    protected ?FunctionExpressionRenderer $functionExpressionRenderer = null;
    protected ?GroupingExpressionRenderer $groupingExpressionRenderer = null;
    protected ?ExistsExpressionRenderer   $existsExpressionRenderer   = null;
    protected ?SubqueryExpressionRenderer $subqueryExpressionRenderer = null;
    protected ?SimpleIdentifierRenderer   $simpleIdentifierRenderer   = null;
    protected ?TableReferenceRenderer     $tableReferenceRenderer     = null;
    protected ?OrderRenderer              $orderRenderer              = null;
    protected ?JoinRenderer               $joinRenderer               = null;
    protected ?WindowRenderer             $windowRenderer             = null;
    protected ?WindowExpressionRenderer   $windowExpressionRenderer   = null;

    // --- DML renderers ------------------------------------------------------
    protected ?SelectRenderer $selectRenderer = null;
    protected ?InsertRenderer $insertRenderer = null;
    protected ?UpdateRenderer $updateRenderer = null;
    protected ?DeleteRenderer $deleteRenderer = null;
    protected ?SetRenderer    $setRenderer    = null;
    protected ?CteRenderer    $cteRenderer    = null;
    protected ?MergeRenderer  $mergeRenderer  = null;

    // --- DDL renderers ------------------------------------------------------
    protected ?TableRenderer      $tableRenderer      = null;
    protected ?ColumnRenderer     $columnRenderer     = null;
    protected ?ViewRenderer       $viewRenderer       = null;
    protected ?SequenceRenderer   $sequenceRenderer   = null;
    protected ?IndexRenderer      $indexRenderer      = null;
    protected ?SchemaRenderer     $schemaRenderer     = null;
    protected ?PrimaryKeyRenderer $primaryKeyRenderer = null;
    protected ?UniqueKeyRenderer  $uniqueKeyRenderer  = null;
    protected ?ForeignKeyRenderer $foreignKeyRenderer = null;
    protected ?CheckRenderer      $checkRenderer      = null;

    /**
     * Reset cached renderer instances so a cloned dialect (e.g. via
     * {@see withBinder()}) gets renderers whose back-reference points at
     * the clone, not the source dialect.
     */
    public function __clone(): void {
        // Common renderers
        $this->binaryExpressionRenderer    = null;
        $this->unaryExpressionRenderer     = null;
        $this->caseExpressionRenderer      = null;
        $this->columnExpressionRenderer    = null;
        $this->columnReferenceRenderer     = null;
        $this->valueExpressionRenderer     = null;
        $this->parameterExpressionRenderer = null;
        $this->uuidInputExpressionRenderer  = null;
        $this->uuidOutputExpressionRenderer = null;
        $this->rawExpressionRenderer       = null;
        $this->functionExpressionRenderer  = null;
        $this->groupingExpressionRenderer  = null;
        $this->existsExpressionRenderer    = null;
        $this->subqueryExpressionRenderer  = null;
        $this->simpleIdentifierRenderer    = null;
        $this->tableReferenceRenderer      = null;
        $this->orderRenderer               = null;
        $this->joinRenderer                = null;
        $this->windowRenderer              = null;
        $this->windowExpressionRenderer    = null;
        // DML renderers
        $this->selectRenderer = null;
        $this->insertRenderer = null;
        $this->updateRenderer = null;
        $this->deleteRenderer = null;
        $this->setRenderer    = null;
        $this->cteRenderer    = null;
        $this->mergeRenderer  = null;
        // DDL renderers
        $this->tableRenderer      = null;
        $this->columnRenderer     = null;
        $this->viewRenderer       = null;
        $this->sequenceRenderer   = null;
        $this->indexRenderer      = null;
        $this->schemaRenderer     = null;
        $this->primaryKeyRenderer = null;
        $this->uniqueKeyRenderer  = null;
        $this->foreignKeyRenderer = null;
        $this->checkRenderer      = null;
    }

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

    public function renderCte(Cte $component): string {
        return $this->cteRenderer()->render($component);
    }

    public function renderMerge(Merge $component): string {
        return $this->mergeRenderer()->render($component);
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

    public function renderSchema(Schema $component): string {
        return $this->schemaRenderer()->render($component);
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

    public function renderCaseExpression(CaseExpression $component): string {
        return $this->caseExpressionRenderer()->render($component);
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

    public function renderParameterExpression(ParameterExpression $component): string {
        return $this->parameterExpressionRenderer()->render($component);
    }

    public function renderUuidInputExpression(UuidInputExpression $component): string {
        return $this->uuidInputExpressionRenderer()->render($component);
    }

    public function renderUuidOutputExpression(UuidOutputExpression $component): string {
        return $this->uuidOutputExpressionRenderer()->render($component);
    }

    public function renderRawExpression(RawExpression $component): string {
        return $this->rawExpressionRenderer()->render($component);
    }

    public function renderFunctionExpression(FunctionExpression $component): string {
        return $this->functionExpressionRenderer()->render($component);
    }

    public function renderGroupingExpression(GroupingExpression $component): string {
        return $this->groupingExpressionRenderer()->render($component);
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

    public function renderWindow(Window $component): string {
        return $this->windowRenderer()->render($component);
    }

    public function renderWindowExpression(WindowExpression $component): string {
        return $this->windowExpressionRenderer()->render($component);
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

    protected function cteRenderer(): CteRenderer {
        return $this->cteRenderer ??= new CteRenderer($this);
    }

    protected function mergeRenderer(): MergeRenderer {
        return $this->mergeRenderer ??= new MergeRenderer($this);
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

    protected function schemaRenderer(): SchemaRenderer {
        return $this->schemaRenderer ??= new SchemaRenderer($this);
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

    protected function caseExpressionRenderer(): CaseExpressionRenderer {
        return $this->caseExpressionRenderer ??= new CaseExpressionRenderer($this);
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

    protected function parameterExpressionRenderer(): ParameterExpressionRenderer {
        return $this->parameterExpressionRenderer ??= new ParameterExpressionRenderer($this);
    }

    protected function uuidInputExpressionRenderer(): UuidInputExpressionRenderer {
        return $this->uuidInputExpressionRenderer ??= new UuidInputExpressionRenderer($this);
    }

    protected function uuidOutputExpressionRenderer(): UuidOutputExpressionRenderer {
        return $this->uuidOutputExpressionRenderer ??= new UuidOutputExpressionRenderer($this);
    }

    protected function rawExpressionRenderer(): RawExpressionRenderer {
        return $this->rawExpressionRenderer ??= new RawExpressionRenderer($this);
    }

    protected function functionExpressionRenderer(): FunctionExpressionRenderer {
        return $this->functionExpressionRenderer ??= new FunctionExpressionRenderer($this);
    }

    protected function groupingExpressionRenderer(): GroupingExpressionRenderer {
        return $this->groupingExpressionRenderer ??= new GroupingExpressionRenderer($this);
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

    protected function windowRenderer(): WindowRenderer {
        return $this->windowRenderer ??= new WindowRenderer($this);
    }

    protected function windowExpressionRenderer(): WindowExpressionRenderer {
        return $this->windowExpressionRenderer ??= new WindowExpressionRenderer($this);
    }
}
