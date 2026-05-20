<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer\Ddl;

use InvalidArgumentException;
use Rak200\SqlBuilder\Ddl\Sequence;
use Rak200\SqlBuilder\Dialect\Dialect;
use Rak200\SqlBuilder\Dialect\Renderer\ComponentRenderer;
use Rak200\SqlBuilder\Utils\StringUtils;

/**
 * Renders a {@see Sequence} as `CREATE`, `ALTER` or `DROP SEQUENCE`.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer\Ddl
 * @author rak200 <rak.ricardo@windowslive.com>
 */
class SequenceRenderer implements ComponentRenderer {

    public function __construct(protected Dialect $dialect) {}

    public function render(Sequence $component): string {
        return match ($component->mode) {
            Sequence::MODE_CREATE => $this->renderCreate($component),
            Sequence::MODE_ALTER  => $this->renderAlter($component),
            Sequence::MODE_DROP   => $this->renderDrop($component),
            default               => throw new InvalidArgumentException(
                "Unsupported sequence mode: {$component->mode}"
            ),
        };
    }

    protected function renderCreate(Sequence $component): string {
        $ifNotExists = $component->ifNotExists ? ' IF NOT EXISTS' : '';

        return sprintf(
            'CREATE SEQUENCE%s "%s"%s',
            $ifNotExists,
            $this->dialect->quoteIdentifier($this->dialect->resolveTableName($component->name)),
            $this->renderOptions($component)
        );
    }

    protected function renderAlter(Sequence $component): string {
        $options = $this->renderOptions($component);
        $restart = $this->renderRestart($component);

        if ($options === '' && $restart === '') {
            throw new InvalidArgumentException('No ALTER SEQUENCE options specified.');
        }

        return sprintf(
            'ALTER SEQUENCE "%s"%s%s',
            $this->dialect->quoteIdentifier($this->dialect->resolveTableName($component->name)),
            $options,
            $restart
        );
    }

    protected function renderDrop(Sequence $component): string {
        $ifExists = $component->ifExists ? ' IF EXISTS' : '';
        $sql = sprintf(
            'DROP SEQUENCE%s "%s"',
            $ifExists,
            $this->dialect->quoteIdentifier($this->dialect->resolveTableName($component->name))
        );

        if ($component->cascade) {
            $sql .= ' CASCADE';
        } elseif ($component->restrict) {
            $sql .= ' RESTRICT';
        }

        return $sql;
    }

    protected function renderOptions(Sequence $component): string {
        $parts = [];

        if ($component->start !== null) {
            $parts[] = 'START WITH ' . $component->start;
        }
        if ($component->increment !== null) {
            $parts[] = 'INCREMENT BY ' . $component->increment;
        }
        if ($component->minValue !== null) {
            $parts[] = 'MINVALUE ' . $component->minValue;
        } elseif ($component->noMinValue) {
            $parts[] = 'NO MINVALUE';
        }
        if ($component->maxValue !== null) {
            $parts[] = 'MAXVALUE ' . $component->maxValue;
        } elseif ($component->noMaxValue) {
            $parts[] = 'NO MAXVALUE';
        }
        if ($component->cache !== null) {
            $parts[] = 'CACHE ' . $component->cache;
        } elseif ($component->noCache) {
            $parts[] = 'NO CACHE';
        }
        if ($component->cycle !== null) {
            $parts[] = $component->cycle ? 'CYCLE' : 'NO CYCLE';
        }

        return StringUtils::join($parts, ' ', ' ');
    }

    protected function renderRestart(Sequence $component): string {
        if ($component->restart !== null) {
            return ' RESTART WITH ' . $component->restart;
        }
        if ($component->restartDefault) {
            return ' RESTART';
        }
        return '';
    }
}
