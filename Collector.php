<?php

namespace Omnilab;

use Omnilab\Exception\NotSupportedException;
use Omnilab\Exception\UnavailableException;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Source\Capability;
use Omnilab\Source\Query;
use Omnilab\Source\SourceInterface;

/**
 * One thing from every source that knows it: the entity one source gives,
 * then its cross-references followed into the other sources configured -
 * PubChem's ChEBI and Wikidata ids offered to a ChEBI and a Wikidata
 * source - and all of it merged, each property with the source it came from.
 *
 *   $caffeine = $collector->collect('pubchem', 'caffeine');
 *   $caffeine->sources;          // ['pubchem', 'chebi', 'wikidata']: those that answered
 *   $collector->incomplete;      // ['chebi' => 'unreachable: ...'] the sources skipped: do not cache that answer
 *
 * A source is offered each identifier in turn until one it reads names a
 * thing it knows: a source that cannot read a scheme says so before any
 * call (NotSupportedException), so only the schemes it reads cost a call.
 * With $depth 2, what the second round found is followed in turn, into the
 * sources not asked yet. A source that is down or rate limited is skipped
 * and named in $incomplete; the others still answer.
 */
final class Collector
{
    /** @var array<string, string> the sources skipped during the last collect(), follow() or search(), and why */
    public array $incomplete = [];

    public function __construct(
        private readonly Registry $registry,
        private readonly Merger $merger = new Merger(),
    ) {
    }

    /**
     * The entity a source gives, completed by the others; null when that source does not know it.
     *
     * @param list<string>|null $sources the configured sources to follow into; null: all
     */
    public function collect(string $source, Identifier|string $id, int $depth = 1, ?array $sources = null): ?Entity
    {
        $this->incomplete = [];
        $entity = $this->registry->get($source)->entity($id);

        return null === $entity ? null : $this->walk($entity, $depth, $sources);
    }

    /**
     * An entity completed by the sources that read its cross-references.
     *
     * @param list<string>|null $sources the configured sources to follow into; null: all
     */
    public function follow(Entity $entity, int $depth = 1, ?array $sources = null): Entity
    {
        $this->incomplete = [];

        return $this->walk($entity, $depth, $sources);
    }

    /** @param list<string>|null $sources */
    private function walk(Entity $entity, int $depth, ?array $sources): Entity
    {
        $found = [$entity];
        $asked = array_fill_keys([...$entity->sources, ...array_filter([$entity->source])], true);
        $offered = [];
        $frontier = [$entity];
        for ($round = 0; $round < $depth && $frontier; ++$round) {
            $identifiers = [];
            foreach ($frontier as $known) {
                foreach ($known->identifiers() as $identifier) {
                    if (!isset($offered[$identifier->key()])) {
                        $identifiers[$identifier->key()] = $identifier;
                    }
                }
            }
            $offered += $identifiers;
            $frontier = [];
            foreach ($this->candidates($sources, $asked) as $name => $candidate) {
                $answer = $this->ask($name, $candidate, array_values($identifiers));
                if (false === $answer) {
                    continue;
                }
                $asked[$name] = true;
                if (null !== $answer) {
                    $found[] = $frontier[] = $answer;
                }
            }
        }

        return $this->merger->combine(...$found);
    }

    /**
     * Every source that searches, asked the same query, their answers merged.
     *
     * @param list<string>|null $sources the configured sources to ask; null: all those that search
     *
     * @return list<Entity>
     */
    public function search(Query $query, ?array $sources = null): array
    {
        $this->incomplete = [];
        $lists = [];
        foreach ($this->candidates($sources, [], Capability::SEARCH) as $name => $source) {
            try {
                $lists[] = $source->search($query);
            } catch (UnavailableException|NotSupportedException $e) {
                $this->incomplete[$name] = $e->getMessage();
            }
        }

        return $this->merger->merge(...$lists);
    }

    /**
     * The sources configured (or named) that can be asked, those already asked left out.
     *
     * @param list<string>|null   $names
     * @param array<string, true> $asked
     *
     * @return array<string, SourceInterface>
     */
    private function candidates(?array $names, array $asked, Capability $capability = Capability::ENTITY): array
    {
        $candidates = [];
        foreach ($names ?? $this->registry->names() as $name) {
            if (isset($asked[$name])) {
                continue;
            }
            $source = $this->registry->get($name);
            if (\in_array($capability, $source->capabilities(), true)) {
                $candidates[$name] = $source;
            }
        }

        return $candidates;
    }

    /**
     * The first entity a source knows under one of the identifiers; null
     * when it read some and knew none, or could not be reached (it is not
     * asked again); false when it read none (it may be offered others in
     * the next round).
     *
     * @param list<Identifier> $identifiers
     */
    private function ask(string $name, SourceInterface $source, array $identifiers): Entity|false|null
    {
        $read = false;
        foreach ($identifiers as $identifier) {
            try {
                $entity = $source->entity($identifier);
            } catch (NotSupportedException) {
                continue;
            } catch (UnavailableException $e) {
                $this->incomplete[$name] = $e->getMessage();

                return null;
            }
            $read = true;
            if (null !== $entity) {
                return $entity;
            }
        }

        return $read ? null : false;
    }
}
