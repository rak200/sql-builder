<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Prepared;

/**
 * Default parameter binder.
 *
 * Emits positional `?` placeholders without wire-level reuse — every
 * occurrence is a fresh `?` and a fresh entry in the values array, even
 * when the caller declares the same logical key twice. This matches the
 * MariaDB/MySQL prepared-statement protocol.
 *
 * Named placeholders (`:name`) are reusable on every dialect because PDO
 * emulates them; the binder caches the placeholder per name and stores the
 * value once.
 *
 * Subclasses (e.g. {@see \Rak200\SqlBuilder\Dialect\Postgres\PostgresBinder})
 * override {@see bind()} to add wire-level positional reuse where the
 * underlying driver supports it.
 *
 * @package Rak200\SqlBuilder\Prepared
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Binder {

    /** @var array<int|string, mixed> Bound values keyed by position (list) or name. */
    protected array $values = [];

    /** @var array<string, string> Cache of `name → ":name"` for reuse. */
    protected array $namedPlaceholders = [];

    /**
     * Bind a value with optional reuse key.
     *
     * - String key  → `:name`; reused across the SQL, single entry per name.
     * - Int key     → fresh `?` per occurrence on the default binder (no
     *                 wire-level reuse on MariaDB/MySQL); value appended at
     *                 every call.
     * - Null key    → anonymous (Bind Mode for `ValueExpression`); fresh
     *                 placeholder per occurrence.
     *
     * @param mixed $value Default value associated with the placeholder.
     * @param int|string|null $key Reuse key; null = anonymous.
     * @return string Placeholder to emit in the SQL.
     */
    public function bind(mixed $value, int|string|null $key = null): string {
        if (is_string($key)) {
            if (!array_key_exists($key, $this->namedPlaceholders)) {
                $this->values[$key]            = $value;
                $this->namedPlaceholders[$key] = ':' . $key;
            }
            return $this->namedPlaceholders[$key];
        }
        return $this->bindFresh($value);
    }

    /**
     * Append a fresh positional value and return its placeholder.
     *
     * Overridden by dialect-specific subclasses to change the placeholder
     * shape (e.g. `$N` on Postgres).
     *
     * @param mixed $value The value to append.
     * @return string The placeholder to emit.
     */
    protected function bindFresh(mixed $value): string {
        $this->values[] = $value;
        return '?';
    }

    /**
     * The accumulated parameter array, ready to hand to PDO.
     *
     * @return array<int|string, mixed>
     */
    public function values(): array {
        return $this->values;
    }
}
