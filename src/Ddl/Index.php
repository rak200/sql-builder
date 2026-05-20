<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * DDL Index builder.
 *
 * Builds SQL index definitions for table columns with support for unique indexes.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Index implements ExpressionInterface {

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

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderIndex($this);
    }

    /**
     * Render this index with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderIndex($this);
    }
}
