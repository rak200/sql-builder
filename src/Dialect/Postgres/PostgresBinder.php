<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Postgres;

use Rak200\SqlBuilder\Prepared\Binder;

/**
 * PostgreSQL parameter binder.
 *
 * Emits `$N` numbered placeholders. Reuses the same `$N` for repeated
 * positional keys (PostgreSQL's wire protocol supports this natively, so
 * the value is stored once and the placeholder text repeats).
 *
 * Named placeholders (`:name`) are inherited from {@see Binder} unchanged.
 *
 * @package Rak200\SqlBuilder\Dialect\Postgres
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class PostgresBinder extends Binder {

    /** @var array<int, string> Cache of `index → "$N"` for positional reuse. */
    private array $indexPlaceholders = [];

    /** Running count of distinct positional values bound. */
    private int $positional = 0;

    /** {@inheritdoc} */
    public function bind(mixed $value, int|string|null $key = null): string {
        if (is_string($key)) {
            return parent::bind($value, $key);
        }
        if (is_int($key)) {
            if (!array_key_exists($key, $this->indexPlaceholders)) {
                $this->positional++;
                $this->values[]                  = $value;
                $this->indexPlaceholders[$key] = '$' . $this->positional;
            }
            return $this->indexPlaceholders[$key];
        }
        return $this->bindFresh($value);
    }

    /** {@inheritdoc} */
    protected function bindFresh(mixed $value): string {
        $this->positional++;
        $this->values[] = $value;
        return '$' . $this->positional;
    }
}
