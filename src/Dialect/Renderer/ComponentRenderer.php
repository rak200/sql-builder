<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Dialect\Renderer;

/**
 * Marker interface for component renderers.
 *
 * Each concrete renderer declares its own `render(SpecificComponent): string`
 * method. PHP does not support contravariant generic parameters, so the
 * interface is intentionally empty — it exists to tag renderer classes and
 * to allow type-checked composition inside a {@see \Rak200\SqlBuilder\Dialect\Dialect}.
 *
 * @package Rak200\SqlBuilder\Dialect\Renderer
 * @author rak200 <rak.ricardo@windowslive.com>
 */
interface ComponentRenderer {}
