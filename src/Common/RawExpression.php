<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Common;

/**
 * Raw SQL expression that is passed through without quoting or escaping.
 *
 * @package Rak200\SqlBuilder\Common
 * @author rak200 <rak.ricardo@windowslive.com>
 */
final class RawExpression extends Expression {

    /**
     * @param string $sql Raw SQL string to embed verbatim.
     */
    public function __construct(private string $sql) {}

    /** {@inheritdoc} */
    public function __toString(): string {
        return $this->sql . $this->aliasToSql();
    }
}
