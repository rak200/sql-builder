<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Ddl;

use Rak200\SqlBuilder\Common\Enum\CheckOption;
use Rak200\SqlBuilder\Common\Expression;
use Rak200\SqlBuilder\Common\ExpressionInterface;
use Rak200\SqlBuilder\Dml\Select;
use Rak200\SqlBuilder\Utils\StringUtils;
use InvalidArgumentException;
use function sprintf;

/**
 * DDL View builder.
 *
 * Builds SQL CREATE VIEW statements using a fluent interface.
 * Supports OR REPLACE, TEMPORARY, IF NOT EXISTS, optional column lists,
 * and WITH [CASCADED|LOCAL] CHECK OPTION.
 *
 * Usage example:
 * ```php
 * $view = View::create('active_users')
 *     ->orReplace()
 *     ->columns('id', 'name', 'email')
 *     ->query(Select::create()->from('users')->where(...))
 *     ->withCheckOption(CheckOption::CASCADED);
 * ```
 *
 * @package Rak200\SqlBuilder\Ddl
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 */
class View implements ExpressionInterface {

    /** @var bool $orReplace Add OR REPLACE to CREATE VIEW */
    private bool $orReplace = false;

    /** @var bool $temporary Create a session-scoped temporary view */
    private bool $temporary = false;

    /** @var bool $ifNotExists Add IF NOT EXISTS guard to CREATE VIEW */
    private bool $ifNotExists = false;

    /** @var string[] $columns Optional explicit column name list */
    private array $columns = [];

    /** @var Select|null $query The SELECT statement that defines the view */
    private ?Select $query = null;

    /** @var bool $withCheckOption Whether to append WITH CHECK OPTION */
    private bool $withCheckOption = false;

    /** @var CheckOption|null $checkOption Qualifier for WITH CHECK OPTION */
    private ?CheckOption $checkOption = null;

    /**
     * @param string $name View name.
     */
    public function __construct(private string $name) {}

    /**
     * Create a new CREATE VIEW builder.
     *
     * @param string $name View name.
     * @return static
     */
    public static function create(string $name): static {
        return new static($name);
    }

    /**
     * Set the view name.
     *
     * @param string $name View name.
     * @return static
     */
    public function name(string $name): static {
        $this->name = $name;
        return $this;
    }

    /**
     * Add OR REPLACE so the view is overwritten if it already exists.
     *
     * Cannot be combined with {@see ifNotExists()}.
     *
     * @param bool $orReplace
     * @return static
     */
    public function orReplace(bool $orReplace = true): static {
        $this->orReplace = $orReplace;
        return $this;
    }

    /**
     * Create the view as a TEMPORARY (session-scoped) view.
     *
     * @return static
     */
    public function temporary(): static {
        $this->temporary = true;
        return $this;
    }

    /**
     * Add IF NOT EXISTS so the statement is skipped when the view already exists.
     *
     * Cannot be combined with {@see orReplace()}.
     *
     * @return static
     */
    public function ifNotExists(): static {
        $this->ifNotExists = true;
        return $this;
    }

    /**
     * Set an explicit column name list for the view.
     *
     * When provided, must match the number of columns returned by the SELECT query.
     *
     * @param string ...$columns Column names.
     * @return static
     */
    public function columns(string ...$columns): static {
        $this->columns = $columns;
        return $this;
    }

    /**
     * Set the SELECT query that defines the view body.
     *
     * @param Select $query The SELECT statement.
     * @return static
     */
    public function query(Select $query): static {
        $this->query = $query;
        return $this;
    }

    /**
     * Append WITH [qualifier] CHECK OPTION to the view definition.
     *
     * Called without arguments emits plain `WITH CHECK OPTION`.
     * Pass {@see CheckOption::CASCADED} or {@see CheckOption::LOCAL} for an explicit qualifier.
     *
     * @param CheckOption|null $option Optional qualifier.
     * @return static
     */
    public function withCheckOption(?CheckOption $option = null): static {
        $this->withCheckOption = true;
        $this->checkOption = $option;
        return $this;
    }

    /**
     * {@inheritdoc}
     * @throws InvalidArgumentException
     */
    public function __toString(): string {
        if ($this->query === null) {
            throw new InvalidArgumentException('A SELECT query must be provided via query() for CREATE VIEW.');
        }

        if ($this->orReplace && $this->ifNotExists) {
            throw new InvalidArgumentException('OR REPLACE and IF NOT EXISTS are mutually exclusive.');
        }

        $orReplace   = $this->orReplace   ? ' OR REPLACE'   : '';
        $temporary   = $this->temporary   ? ' TEMPORARY'    : '';
        $ifNotExists = $this->ifNotExists ? ' IF NOT EXISTS' : '';

        $sql = sprintf(
            'CREATE%s%s VIEW%s "%s"',
            $orReplace,
            $temporary,
            $ifNotExists,
            Expression::quoteIdentifier($this->name)
        );

        $sql .= $this->buildColumnList();
        $sql .= ' AS ' . $this->query;
        $sql .= $this->buildCheckOption();

        return $sql;
    }

    /**
     * Build the optional column list fragment.
     *
     * @return string Space-prefixed `("col1", "col2")` or empty string.
     */
    private function buildColumnList(): string {
        return StringUtils::join(
            array_map(fn(string $col) => sprintf('"%s"', $col), $this->columns),
            ', ', ' (', ')'
        );
    }

    /**
     * Build the WITH [qualifier] CHECK OPTION fragment.
     *
     * @return string Space-prefixed clause or empty string.
     */
    private function buildCheckOption(): string {
        if (!$this->withCheckOption) {
            return '';
        }

        $qualifier = $this->checkOption !== null ? ' ' . $this->checkOption->value : '';
        return sprintf(' WITH%s CHECK OPTION', $qualifier);
    }

}
