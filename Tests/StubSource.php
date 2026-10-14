<?php

namespace Omnilab\Tests;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\UnavailableException;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Scheme;
use Omnilab\Source\Capability;
use Omnilab\Source\Page;
use Omnilab\Source\ProductQuery;
use Omnilab\Source\Query;
use Omnilab\Source\SeriesQuery;
use Omnilab\Source\SourceInterface;

/**
 * A source that knows the entities it is given, under every identifier of
 * the schemes it reads, refuses the others before any "call", and logs what
 * it is asked.
 */
final class StubSource implements SourceInterface
{
    /** @var list<string> */
    public static array $log = [];

    /**
     * @param list<Entity> $entities
     * @param list<Scheme> $schemes  what it reads
     */
    public function __construct(
        private readonly string $name,
        private readonly array $entities = [],
        private readonly array $schemes = [],
        private readonly bool $down = false,
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function capabilities(): array
    {
        return [Capability::ENTITY, Capability::SEARCH, Capability::CROSS_REFERENCES];
    }

    public function entity(Identifier|string $id): ?Entity
    {
        $id = $id instanceof Identifier ? $id : (Identifier::parse($id) ?? throw NotSupportedException::identifier($this->name, $id));
        if (!\in_array($id->scheme, $this->schemes, true)) {
            throw NotSupportedException::identifier($this->name, (string) $id);
        }
        self::$log[] = $this->name.' '.$id;
        if ($this->down) {
            throw new UnavailableException($this->name, 'down');
        }
        foreach ($this->entities as $entity) {
            if ($entity->identifiers()->has($id)) {
                return $entity;
            }
        }

        return null;
    }

    public function search(Query $query): Page
    {
        self::$log[] = $this->name.' search '.$query->text;
        if ($this->down) {
            throw new UnavailableException($this->name, 'down');
        }

        return new Page(array_values(array_filter($this->entities, static fn (Entity $e) => str_contains(strtolower($e->label), strtolower($query->text)))));
    }

    public function series(SeriesQuery $query): array
    {
        throw NotSupportedException::operation($this->name, 'serve series');
    }

    public function products(ProductQuery $query): Page
    {
        throw NotSupportedException::operation($this->name, 'list products');
    }
}
