<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Common\RawExpression;
use Rak200\SqlBuilder\Utils\StringUtils;
use InvalidArgumentException;

/**
 * DDL Sequence builder.
 *
 * Builds SQL CREATE SEQUENCE and ALTER SEQUENCE statements using a fluent interface.
 * Supports start value, increment, min/max bounds, caching, and cycling.
 * Use {@see nextVal()} to obtain a NEXTVAL expression suitable as a column DEFAULT.
 *
 * Usage example:
 * ```php
 * $seq = Sequence::create('order_id_seq')
 *     ->startWith(1000)
 *     ->incrementBy(1)
 *     ->noMaxValue()
 *     ->cache(20)
 *     ->cycle();
 *
 * $table = Table::create('orders')
 *     ->column(Column::create('id', 'BIGINT')->nullable(false)->sequence($seq));
 *
 * echo $seq;   // CREATE SEQUENCE "order_id_seq" ...
 * echo $table; // CREATE TABLE "orders" ("id" BIGINT NOT NULL DEFAULT NEXTVAL('order_id_seq'))
 * ```
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
class Sequence implements ExpressionInterface {

    /** @var bool $alterMode Whether this builder is in ALTER SEQUENCE mode */
    private bool $alterMode = false;

    /** @var bool $ifNotExists Add IF NOT EXISTS guard to CREATE SEQUENCE */
    private bool $ifNotExists = false;

    /** @var int|null $start START WITH value */
    private ?int $start = null;

    /** @var int|null $increment INCREMENT BY step */
    private ?int $increment = null;

    /** @var int|null $minValue Explicit MINVALUE lower bound */
    private ?int $minValue = null;

    /** @var bool $noMinValue Emit NO MINVALUE when true */
    private bool $noMinValue = false;

    /** @var int|null $maxValue Explicit MAXVALUE upper bound */
    private ?int $maxValue = null;

    /** @var bool $noMaxValue Emit NO MAXVALUE when true */
    private bool $noMaxValue = false;

    /** @var int|null $cache Number of pre-allocated values */
    private ?int $cache = null;

    /** @var bool $noCache Emit NO CACHE when true */
    private bool $noCache = false;

    /** @var bool|null $cycle null = not set, true = CYCLE, false = NO CYCLE */
    private ?bool $cycle = null;

    /** @var int|null $restart RESTART WITH value for ALTER mode */
    private ?int $restart = null;

    /** @var bool $restartDefault Emit RESTART without a value in ALTER mode */
    private bool $restartDefault = false;

    /**
     * @param string $name Sequence name.
     */
    public function __construct(private string $name) {}

    /**
     * Create a new CREATE SEQUENCE builder.
     *
     * @param string $name Sequence name.
     * @return static
     */
    public static function create(string $name): static {
        return new static($name);
    }

    /**
     * Create a new ALTER SEQUENCE builder.
     *
     * @param string $name Sequence name.
     * @return static
     */
    public static function alter(string $name): static {
        $sequence = new static($name);
        $sequence->alterMode = true;
        return $sequence;
    }

    /**
     * Set the sequence name.
     *
     * @param string $name Sequence name.
     * @return static
     */
    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    /**
     * Add IF NOT EXISTS guard to the CREATE SEQUENCE statement.
     *
     * @param bool $ifNotExists
     * @return static
     */
    public function ifNotExists(bool $ifNotExists = true): static {
        $this->ifNotExists = $ifNotExists;
        return $this;
    }

    /**
     * Set the START WITH value.
     *
     * @param int $start The first value the sequence will generate.
     * @return static
     */
    public function startWith(int $start): static {
        $this->start = $start;
        return $this;
    }

    /**
     * Set the INCREMENT BY step.
     *
     * @param int $increment Positive for ascending, negative for descending.
     * @throws InvalidArgumentException If increment is zero.
     * @return static
     */
    public function incrementBy(int $increment): static {
        if ($increment === 0) {
            throw new InvalidArgumentException('Sequence increment cannot be zero.');
        }
        $this->increment = $increment;
        return $this;
    }

    /**
     * Set an explicit MINVALUE lower bound.
     *
     * @param int $minValue Minimum value the sequence can generate.
     * @return static
     */
    public function minValue(int $minValue): static {
        $this->minValue = $minValue;
        $this->noMinValue = false;
        return $this;
    }

    /**
     * Emit NO MINVALUE, relying on the database default lower bound.
     *
     * @return static
     */
    public function noMinValue(): static {
        $this->noMinValue = true;
        $this->minValue = null;
        return $this;
    }

    /**
     * Set an explicit MAXVALUE upper bound.
     *
     * @param int $maxValue Maximum value the sequence can generate.
     * @return static
     */
    public function maxValue(int $maxValue): static {
        $this->maxValue = $maxValue;
        $this->noMaxValue = false;
        return $this;
    }

    /**
     * Emit NO MAXVALUE, relying on the database default upper bound.
     *
     * @return static
     */
    public function noMaxValue(): static {
        $this->noMaxValue = true;
        $this->maxValue = null;
        return $this;
    }

    /**
     * Set the number of sequence values to pre-allocate (CACHE).
     *
     * @param int $cache Must be at least 1.
     * @throws InvalidArgumentException If cache is less than 1.
     * @return static
     */
    public function cache(int $cache): static {
        if ($cache < 1) {
            throw new InvalidArgumentException('Sequence cache must be at least 1.');
        }
        $this->cache = $cache;
        $this->noCache = false;
        return $this;
    }

    /**
     * Emit NO CACHE to disable pre-allocation of sequence values.
     *
     * @return static
     */
    public function noCache(): static {
        $this->noCache = true;
        $this->cache = null;
        return $this;
    }

    /**
     * Emit CYCLE so the sequence wraps around when it reaches its bound.
     *
     * @return static
     */
    public function cycle(): static {
        $this->cycle = true;
        return $this;
    }

    /**
     * Emit NO CYCLE so the sequence raises an error on overflow.
     *
     * @return static
     */
    public function noCycle(): static {
        $this->cycle = false;
        return $this;
    }

    /**
     * Emit RESTART [WITH value] in ALTER mode to reset the sequence counter.
     *
     * @param int|null $value New current value; omit to restart at the original START WITH value.
     * @throws InvalidArgumentException If not in ALTER mode.
     * @return static
     */
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
     * Return a RawExpression with NEXTVAL for this sequence.
     *
     * The returned expression is suitable as a column DEFAULT, e.g.:
     * `Column::create('id', 'BIGINT')->sequence($seq)` internally calls this.
     *
     * @return RawExpression
     */
    public function nextVal(): RawExpression {
        return Expression::raw(sprintf("NEXTVAL('%s')", Expression::quoteIdentifier($this->name)));
    }

    /**
     * Convert the sequence definition to a SQL statement.
     *
     * @return string CREATE SEQUENCE or ALTER SEQUENCE statement.
     */
    public function __toString(): string {
        return $this->alterMode ? $this->buildAlterSql() : $this->buildCreateSql();
    }

    /**
     * Build the CREATE SEQUENCE SQL statement.
     *
     * @return string
     */
    private function buildCreateSql(): string {
        $ifNotExists = $this->ifNotExists ? ' IF NOT EXISTS' : '';

        return sprintf(
            'CREATE SEQUENCE%s "%s"%s',
            $ifNotExists,
            Expression::quoteIdentifier($this->name),
            $this->buildOptions()
        );
    }

    /**
     * Build the ALTER SEQUENCE SQL statement.
     *
     * @return string
     * @throws InvalidArgumentException If no options or RESTART clause are specified.
     */
    private function buildAlterSql(): string {
        $options = $this->buildOptions();
        $restart = $this->buildRestart();

        if ($options === '' && $restart === '') {
            throw new InvalidArgumentException('No ALTER SEQUENCE options specified.');
        }

        return sprintf(
            'ALTER SEQUENCE "%s"%s%s',
            Expression::quoteIdentifier($this->name),
            $options,
            $restart
        );
    }

    /**
     * Build the shared options fragment used by both CREATE and ALTER.
     *
     * @return string Space-prefixed options string, or empty string if none are set.
     */
    private function buildOptions(): string {
        $parts = [];

        if ($this->start !== null) {
            $parts[] = 'START WITH ' . $this->start;
        }
        if ($this->increment !== null) {
            $parts[] = 'INCREMENT BY ' . $this->increment;
        }
        if ($this->minValue !== null) {
            $parts[] = 'MINVALUE ' . $this->minValue;
        } elseif ($this->noMinValue) {
            $parts[] = 'NO MINVALUE';
        }
        if ($this->maxValue !== null) {
            $parts[] = 'MAXVALUE ' . $this->maxValue;
        } elseif ($this->noMaxValue) {
            $parts[] = 'NO MAXVALUE';
        }
        if ($this->cache !== null) {
            $parts[] = 'CACHE ' . $this->cache;
        } elseif ($this->noCache) {
            $parts[] = 'NO CACHE';
        }
        if ($this->cycle !== null) {
            $parts[] = $this->cycle ? 'CYCLE' : 'NO CYCLE';
        }

        return StringUtils::join($parts, ' ', ' ');
    }

    /**
     * Build the RESTART clause for ALTER SEQUENCE.
     *
     * @return string
     */
    private function buildRestart(): string {
        if ($this->restart !== null) {
            return ' RESTART WITH ' . $this->restart;
        }
        if ($this->restartDefault) {
            return ' RESTART';
        }
        return '';
    }

    /**
     * Ensure the builder is in ALTER mode.
     *
     * @throws InvalidArgumentException If not in ALTER mode.
     */
    private function ensureAlterMode(): void {
        if (!$this->alterMode) {
            throw new InvalidArgumentException('This method is only available in alter mode. Use Sequence::alter().');
        }
    }
}
