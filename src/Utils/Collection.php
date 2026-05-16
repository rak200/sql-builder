<?php

declare(strict_types=1);

namespace Rak200\SqlBuilder\Utils;

use Rak200\Caster\Contracts\ToArray;
use InvalidArgumentException;
use T_Object;

/**
 * Collection class
 * This class implements the Iterator, ArrayAccess, and Countable interfaces.
 * It provides a way to store and manipulate a collection of objects.
 * @package Rak200\SqlBuilder\Utils
 * @author Ricardo Augusto Küstner <rak.ricardo@windowslive.com>
 * @template T_Key of int|string
 * @template T_Object of object
 */
class Collection implements \Iterator, \ArrayAccess, \Countable, ToArray {

    private null|int|string $position = null;

    /**
     * Constructor for the Collection class.
     *
     * @param class-string<T_Object> $type Optional type description for the collection (e.g., 'User', 'Product').
     * @param T_Object[] $items Initial items to populate the collection, indexed by T_Key.
     * @throws InvalidArgumentException
     */
    public function __construct(private string $type = 'mixed', private array $items = []) {
        $this->checkType($items);
    }

    /**
     * Validates the type of each item
     * @param T_Object[] $items
     * @throws InvalidArgumentException
     * @return void
     */
    private function checkType(array $items): void {
        if ($this->type === 'mixed') {
            return; // No type checking for mixed collections
        }
        if (!array_all($items, fn ($item) => is_a($item, $this->type, true))) {
            throw new InvalidArgumentException(sprintf(
                'All items in the collection must be instances of %s. Invalid item found: %s',
                $this->type,
                var_export($items, true)
            ));
        }
    }

    // Iterator methods
    /**
     * {@inheritDoc}
     * @return T_Object
     */
    public function current(): object {
        return current($this->items);
    }

    /**
     * {@inheritDoc}
     * @return T_Key
     */
    public function key(): int|string {
        return key($this->items);
    }

    /**
     * {@inheritDoc}
     * @return void
     */
    public function next(): void {
        next($this->items);
        $this->position = key($this->items);
    }

    /** {@inheritDoc} */
    public function rewind(): void {
        reset($this->items);
        $this->position = key($this->items);
    }

    /** {@inheritDoc} */
    public function valid(): bool {
        return key($this->items) !== null;
    }

    // ArrayAccess methods
    /**
     * {@inheritDoc}
     * @param T_Key $offset
     * @return bool
     */
    public function offsetExists(mixed $offset): bool {
        return isset($this->items[$offset]);
    }

    /**
     * {@inheritDoc}
     * @param T_Key $offset
     * @return T_Object
     */
    public function offsetGet(mixed $offset): object {
        return $this->items[$offset] ?? null;
    }

    /**
     * {@inheritDoc}
     * @param T_Key $offset
     * @param T_Object $value
     * @throws InvalidArgumentException
     */
    public function offsetSet(mixed $offset, mixed $value): void {
        $this->checkType([$value]);
        if ($offset === null) {
            $this->items[] = $value;
        } else {
            $this->items[$offset] = $value;
        }
    }

    /**
     * {@inheritDoc}
     * @param T_Key $offset
     * @return void
     */
    public function offsetUnset(mixed $offset): void {
        unset($this->items[$offset]);
    }

    // Countable method
    /** {@inheritDoc} */
    public function count(): int {
        return count($this->items);
    }

    // Additional utility methods
    /**
     * add an item to the collection.
     * @param T_Key $offset
     * @param T_Object $item
     * @throws InvalidArgumentException
     */
    public function add(int|string $offset, object $item): void {
        $this->checkType([$item]);
        $this->items[$offset] = $item;
    }

    /**
     * remove an item from the collection.
     * @param T_Key $offset
     */
    public function remove(int|string $offset): void {
        unset($this->items[$offset]);
    }

    /**
     * get an item from the collection.
     * @param T_Key $offset
     * @return ?T_Object
     */
    public function get(int|string $offset): ?object {
        return $this->items[$offset] ?? null;
    }

    /** {@inheritDoc} */
    public function toArray(): array {
        return $this->items;
    }
}
