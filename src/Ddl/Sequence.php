<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use InvalidArgumentException;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\RawExpression;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * DDL Sequence builder.
 *
 * Builds SQL CREATE SEQUENCE and ALTER SEQUENCE statements using a fluent
 * interface. Use {@see nextVal()} to obtain a NEXTVAL expression suitable as
 * a column DEFAULT.
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Sequence implements ExpressionInterface {

    public private(set) bool $alterMode = false;
    public private(set) bool $ifNotExists = false;
    public private(set) ?int $start = null;
    public private(set) ?int $increment = null;
    public private(set) ?int $minValue = null;
    public private(set) bool $noMinValue = false;
    public private(set) ?int $maxValue = null;
    public private(set) bool $noMaxValue = false;
    public private(set) ?int $cache = null;
    public private(set) bool $noCache = false;
    public private(set) ?bool $cycle = null;
    public private(set) ?int $restart = null;
    public private(set) bool $restartDefault = false;

    public function __construct(public private(set) string $name) {}

    public static function create(string $name): static {
        return new static($name);
    }

    public static function alter(string $name): static {
        $sequence = new static($name);
        $sequence->alterMode = true;
        return $sequence;
    }

    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    public function ifNotExists(bool $ifNotExists = true): static {
        $this->ifNotExists = $ifNotExists;
        return $this;
    }

    public function startWith(int $start): static {
        $this->start = $start;
        return $this;
    }

    public function incrementBy(int $increment): static {
        if ($increment === 0) {
            throw new InvalidArgumentException('Sequence increment cannot be zero.');
        }
        $this->increment = $increment;
        return $this;
    }

    public function minValue(int $minValue): static {
        $this->minValue = $minValue;
        $this->noMinValue = false;
        return $this;
    }

    public function noMinValue(): static {
        $this->noMinValue = true;
        $this->minValue = null;
        return $this;
    }

    public function maxValue(int $maxValue): static {
        $this->maxValue = $maxValue;
        $this->noMaxValue = false;
        return $this;
    }

    public function noMaxValue(): static {
        $this->noMaxValue = true;
        $this->maxValue = null;
        return $this;
    }

    public function cache(int $cache): static {
        if ($cache < 1) {
            throw new InvalidArgumentException('Sequence cache must be at least 1.');
        }
        $this->cache = $cache;
        $this->noCache = false;
        return $this;
    }

    public function noCache(): static {
        $this->noCache = true;
        $this->cache = null;
        return $this;
    }

    public function cycle(): static {
        $this->cycle = true;
        return $this;
    }

    public function noCycle(): static {
        $this->cycle = false;
        return $this;
    }

    public function restart(?int $value = null): static {
        $this->ensureAlterMode();

        if ($value === null) {
            $this->restartDefault = true;
            $this->restart = null;
        } else {
            $this->restart = $value;
            $this->restartDefault = false;
        }

        return $this;
    }

    /**
     * Return a {@see RawExpression} with NEXTVAL for this sequence.
     */
    public function nextVal(): RawExpression {
        return Expression::raw(sprintf(
            "NEXTVAL('%s')",
            Dialect::default()->quoteIdentifier($this->name)
        ));
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderSequence($this);
    }

    /**
     * Render this sequence with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderSequence($this);
    }

    private function ensureAlterMode(): void {
        if (!$this->alterMode) {
            throw new InvalidArgumentException('This method is only available in alter mode. Use Sequence::alter().');
        }
    }
}
