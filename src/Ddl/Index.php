<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * DDL Index builder.
 *
 * Builds `CREATE INDEX` and `DROP INDEX` statements. The MariaDB dialect
 * needs the parent table to emit `DROP INDEX name ON table`, so `table` is
 * preserved on DROP-mode indexes too.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Index implements ExpressionInterface {

    public const string MODE_CREATE = 'CREATE';
    public const string MODE_DROP   = 'DROP';

    public private(set) string $mode = self::MODE_CREATE;
    public private(set) bool $ifExists = false;
    public private(set) bool $cascade = false;

    /**
     * @param string $name Index name.
     * @param string $table Table the index belongs to.
     * @param array<int, string> $columns Column names included in the index.
     * @param bool $unique Whether the index is unique.
     */
    public function __construct(
        public private(set) string $name,
        public private(set) string $table = '',
        public private(set) array $columns = [],
        public private(set) bool $unique = false
    ) {}

    public static function create(string $name): static {
        return new static($name);
    }

    public static function drop(string $name): static {
        $index = new static($name);
        $index->mode = self::MODE_DROP;
        return $index;
    }

    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    public function table(string $table): static {
        $this->table = $table;
        return $this;
    }

    public function columns(array $columns): static {
        $this->columns = $columns;
        return $this;
    }

    public function unique(): static {
        $this->unique = true;
        return $this;
    }

    /** `IF EXISTS` guard for `DROP INDEX`. */
    public function ifExists(bool $ifExists = true): static {
        $this->ifExists = $ifExists;
        return $this;
    }

    /** `CASCADE` modifier (DROP). */
    public function cascade(): static {
        $this->cascade = true;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderIndex($this);
    }

    public function toSql(Dialect $dialect): string {
        return $dialect->renderIndex($this);
    }
}
