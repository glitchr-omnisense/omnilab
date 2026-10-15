<?php

namespace Omnilab\Source;

/**
 * One page of entities (search(), occurrences()) or of products
 * (products()). $next is the cursor of the following page (pass it as the
 * query's cursor), null on the last one.
 *
 * @template T of \Omnilab\Model\Entity|\Omnilab\Model\Product
 *
 * @implements \IteratorAggregate<int, T>
 */
final readonly class Page implements \IteratorAggregate, \Countable
{
    /**
     * @param list<T>  $items
     * @param int|null $total how many the whole query matches, when the source says
     */
    public function __construct(
        public array $items = [],
        public ?string $next = null,
        public ?int $total = null,
    ) {
    }

    public function isLast(): bool
    {
        return null === $this->next;
    }

    /** @return T|null */
    public function first(): mixed
    {
        return $this->items[0] ?? null;
    }

    public function getIterator(): \ArrayIterator
    {
        return new \ArrayIterator($this->items);
    }

    public function count(): int
    {
        return \count($this->items);
    }
}
