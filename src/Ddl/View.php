<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\Enum\CheckOption;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dml\Select;

/**
 * DDL View builder.
 *
 * Builds SQL CREATE VIEW statements using a fluent interface.
 * Supports OR REPLACE, TEMPORARY, IF NOT EXISTS, optional column lists,
 * and WITH [CASCADED|LOCAL] CHECK OPTION.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class View implements ExpressionInterface {

    public private(set) bool $orReplace = false;
    public private(set) bool $temporary = false;
    public private(set) bool $ifNotExists = false;

    /** @var string[] Optional explicit column name list. */
    public private(set) array $columns = [];

    /** @var Select|null The SELECT statement that defines the view. */
    public private(set) ?Select $query = null;

    public private(set) bool $withCheckOption = false;
    public private(set) ?CheckOption $checkOption = null;

    public function __construct(public private(set) string $name) {}

    public static function create(string $name): static {
        return new static($name);
    }

    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    public function orReplace(bool $orReplace = true): static {
        $this->orReplace = $orReplace;
        return $this;
    }

    public function temporary(): static {
        $this->temporary = true;
        return $this;
    }

    public function ifNotExists(): static {
        $this->ifNotExists = true;
        return $this;
    }

    public function columns(string ...$columns): static {
        $this->columns = $columns;
        return $this;
    }

    public function query(Select $query): static {
        $this->query = $query;
        return $this;
    }

    public function withCheckOption(?CheckOption $option = null): static {
        $this->withCheckOption = true;
        $this->checkOption = $option;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderView($this);
    }

    /**
     * Render this view with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderView($this);
    }
}
