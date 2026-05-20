<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use InvalidArgumentException;
use Rak200\SqlBuilder\Common\Enum\CheckOption;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dml\Select;

/**
 * DDL View builder.
 *
 * Builds `CREATE VIEW` and `DROP VIEW` statements. CREATE supports
 * `OR REPLACE`, `TEMPORARY`, `IF NOT EXISTS`, explicit column lists and
 * `WITH [CASCADED|LOCAL] CHECK OPTION`. DROP supports `IF EXISTS` and
 * `CASCADE`/`RESTRICT`.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class View implements ExpressionInterface {

    public const string MODE_CREATE = 'CREATE';
    public const string MODE_DROP   = 'DROP';

    public private(set) string $mode = self::MODE_CREATE;

    public private(set) bool $orReplace = false;
    public private(set) bool $temporary = false;
    public private(set) bool $ifNotExists = false;

    /** @var string[] Optional explicit column name list. */
    public private(set) array $columns = [];

    /** @var Select|null The SELECT statement that defines the view. */
    public private(set) ?Select $query = null;

    public private(set) bool $withCheckOption = false;
    public private(set) ?CheckOption $checkOption = null;

    public private(set) bool $ifExists = false;
    public private(set) bool $cascade = false;
    public private(set) bool $restrict = false;

    public function __construct(public private(set) string $name) {}

    public static function create(string $name): static {
        return new static($name);
    }

    public static function drop(string $name): static {
        $view = new static($name);
        $view->mode = self::MODE_DROP;
        return $view;
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

    /**
     * `IF EXISTS` guard for `DROP VIEW`.
     */
    public function ifExists(bool $ifExists = true): static {
        $this->ifExists = $ifExists;
        return $this;
    }

    /** `CASCADE` modifier (DROP). Clears any prior `RESTRICT`. */
    public function cascade(): static {
        $this->cascade  = true;
        $this->restrict = false;
        return $this;
    }

    /** `RESTRICT` modifier (DROP). Clears any prior `CASCADE`. */
    public function restrict(): static {
        $this->restrict = true;
        $this->cascade  = false;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderView($this);
    }

    public function toSql(Dialect $dialect): string {
        return $dialect->renderView($this);
    }
}
