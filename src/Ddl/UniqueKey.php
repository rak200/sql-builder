<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * DDL Unique constraint builder.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class UniqueKey extends Constraint {

    /** @var bool|null Tri-state NULLS DISTINCT modifier (null = unspecified, true = DISTINCT, false = NOT DISTINCT). */
    public private(set) ?bool $nullsDistinct = null;

    /**
     * @param string $name Name of the unique constraint.
     * @param string[] $columns Column names that must be unique.
     */
    public function __construct(string $name = '', public private(set) array $columns = []) {
        parent::__construct($name);
    }

    public static function create(?string $name = null): static {
        return new static($name ?? '');
    }

    public function columns(array $columns): static {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Emit `NULLS DISTINCT` (Postgres 15+ default — multiple NULLs are distinct).
     */
    public function nullsDistinct(): static {
        $this->nullsDistinct = true;
        return $this;
    }

    /**
     * Emit `NULLS NOT DISTINCT` — Postgres 15+ allows at most one NULL.
     */
    public function nullsNotDistinct(): static {
        $this->nullsDistinct = false;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderUniqueKey($this);
    }
}
