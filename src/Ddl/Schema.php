<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use InvalidArgumentException;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dialect\Dialect;

/**
 * DDL Schema builder.
 *
 * Builds `CREATE SCHEMA`, `DROP SCHEMA` and `ALTER SCHEMA ... RENAME TO`
 * statements. Schemas are a first-class namespace inside a database on
 * PostgreSQL; on MariaDB/MySQL they are interchangeable with databases, so
 * the {@see \Rak200\SqlBuilder\Dialect\MariaDb\MariaDbDialect} emits
 * `CREATE DATABASE` / `DROP DATABASE` and rejects schema-only options
 * (AUTHORIZATION, RESTRICT, RENAME TO).
 *
 * Usage example:
 * ```php
 * echo Schema::create('reporting')->ifNotExists()->authorization('analytics');
 * // CREATE SCHEMA IF NOT EXISTS `reporting` AUTHORIZATION `analytics`
 *
 * echo Schema::drop('legacy')->ifExists()->cascade();
 * // DROP SCHEMA IF EXISTS `legacy` CASCADE
 *
 * echo Schema::alter('old_name')->renameTo('new_name');
 * // ALTER SCHEMA `old_name` RENAME TO `new_name`
 * ```
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class Schema implements ExpressionInterface {

    public const string MODE_CREATE = 'CREATE';
    public const string MODE_DROP   = 'DROP';
    public const string MODE_ALTER  = 'ALTER';

    public private(set) string $mode = self::MODE_CREATE;
    public private(set) bool $ifNotExists = false;
    public private(set) bool $ifExists = false;
    public private(set) ?string $authorization = null;
    public private(set) bool $cascade = false;
    public private(set) bool $restrict = false;
    public private(set) ?string $renameTo = null;

    /** @param string $name Schema name. */
    public function __construct(public private(set) string $name) {}

    /**
     * Create a new CREATE SCHEMA builder.
     */
    public static function create(string $name): static {
        return new static($name);
    }

    /**
     * Create a new DROP SCHEMA builder.
     */
    public static function drop(string $name): static {
        $schema = new static($name);
        $schema->mode = self::MODE_DROP;
        return $schema;
    }

    /**
     * Create a new ALTER SCHEMA builder.
     */
    public static function alter(string $name): static {
        $schema = new static($name);
        $schema->mode = self::MODE_ALTER;
        return $schema;
    }

    /** Rename the schema (does not change the rendered statement type). */
    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    /**
     * Add `IF NOT EXISTS` to a CREATE SCHEMA statement.
     */
    public function ifNotExists(bool $ifNotExists = true): static {
        $this->ifNotExists = $ifNotExists;
        return $this;
    }

    /**
     * Add `IF EXISTS` to a DROP SCHEMA statement.
     */
    public function ifExists(bool $ifExists = true): static {
        $this->ifExists = $ifExists;
        return $this;
    }

    /**
     * Set the owner via `AUTHORIZATION` on CREATE SCHEMA (PostgreSQL).
     */
    public function authorization(string $owner): static {
        $this->authorization = $owner;
        return $this;
    }

    /**
     * Append `CASCADE` to DROP SCHEMA. Clears any prior `RESTRICT`.
     */
    public function cascade(): static {
        $this->cascade = true;
        $this->restrict = false;
        return $this;
    }

    /**
     * Append `RESTRICT` to DROP SCHEMA. Clears any prior `CASCADE`.
     */
    public function restrict(): static {
        $this->restrict = true;
        $this->cascade = false;
        return $this;
    }

    /**
     * Rename the schema in ALTER mode.
     *
     * @throws InvalidArgumentException If not in ALTER mode.
     */
    public function renameTo(string $newName): static {
        if ($this->mode !== self::MODE_ALTER) {
            throw new InvalidArgumentException('renameTo() is only available in ALTER mode. Use Schema::alter().');
        }
        $this->renameTo = $newName;
        return $this;
    }

    /** {@inheritdoc} */
    public function __toString(): string {
        return Dialect::default()->renderSchema($this);
    }

    /**
     * Render this schema statement with a specific dialect.
     */
    public function toSql(Dialect $dialect): string {
        return $dialect->renderSchema($this);
    }
}
