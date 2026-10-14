<?php

namespace Omnilab;

use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifiers;
use Omnilab\Model\Property;
use Omnilab\Source\Page;

/**
 * The entities several sources describe, made one: identifiers, synonyms
 * and sources united; each field (label, description, image...) from the
 * source preferred for it; every property kept with the source that gave
 * it, the preferred source's first when two give the same key.
 *
 * Two entities are the same thing when they share an identifier, unless one
 * carries, in the scheme of the other's own id, a different value: two
 * PubChem compounds with two CIDs stay two, whatever CAS number they share
 * (a salt and its base often do).
 *
 * Preferences name, for a field or a property key, the sources in the order
 * they are trusted; "default" orders the others. A source not listed comes
 * after those listed, in the order the entities were given:
 *
 *   new Merger(['default' => ['pubchem', 'chebi'], 'description' => ['chebi'], 'molecular_weight' => ['pubchem']]);
 */
final class Merger
{
    /** The fields taken whole from the preferred source that has them. */
    private const FIELDS = ['label', 'kind', 'description', 'url', 'image', 'time', 'place'];

    /** @var array<string, list<string>> */
    private readonly array $preferences;

    /** @param array<string, list<string>> $preferences field or property key => sources, "default" for the others */
    public function __construct(array $preferences = [])
    {
        $this->preferences = $preferences + ['default' => []];
    }

    /**
     * Each source's entities, grouped by the thing they describe.
     *
     * @param iterable<Entity>|Page ...$lists
     *
     * @return list<Entity> in the order each thing was first seen
     */
    public function merge(iterable ...$lists): array
    {
        $entities = [];
        foreach ($lists as $list) {
            foreach ($list as $entity) {
                $entities[] = $entity;
            }
        }

        $parent = array_keys($entities);
        $find = static function (int $i) use (&$parent): int {
            while ($parent[$i] !== $i) {
                $i = $parent[$i] = $parent[$parent[$i]];
            }

            return $i;
        };
        $members = array_map(static fn (int $i) => [$i], array_keys($entities));
        $index = [];
        foreach ($entities as $i => $entity) {
            foreach ($entity->identifiers()->keys() as $key) {
                if (isset($index[$key]) && ($root = $find($index[$key])) !== ($mine = $find($i))) {
                    $cluster = array_map(static fn (int $j) => $entities[$j], $members[$root]);
                    $own = array_map(static fn (int $j) => $entities[$j], $members[$mine]);
                    if (!$this->conflicts($cluster, $own)) {
                        [$keep, $drop] = $root < $mine ? [$root, $mine] : [$mine, $root];
                        $parent[$drop] = $keep;
                        array_push($members[$keep], ...$members[$drop]);
                        unset($members[$drop]);
                    }
                }
                $index[$key] ??= $i;
            }
        }
        ksort($members);

        return array_values(array_map(fn (array $cluster) => $this->combine(...array_map(static fn (int $j) => $entities[$j], $cluster)), $members));
    }

    /** Whether the Merger takes two entities for the same thing. */
    public function same(Entity $a, Entity $b): bool
    {
        return 1 === \count($this->merge([$a], [$b]));
    }

    /**
     * One entity from several that describe the same thing (the Collector
     * found them through each other's identifiers): no check is made.
     */
    public function combine(Entity ...$entities): Entity
    {
        if (!$entities) {
            throw new \InvalidArgumentException('Nothing to combine.');
        }
        if (1 === \count($entities)) {
            $entity = $entities[0];

            return $entity->sources ? $entity : $entity->with(['sources' => array_values(array_filter([$entity->source]))]);
        }

        $default = $this->ordered($entities, 'default');
        $values = [];
        foreach (self::FIELDS as $field) {
            foreach ($this->ordered($entities, $field) as $entity) {
                $value = $entity->{$field};
                if (null !== $value && '' !== $value) {
                    $values[$field] = $value;
                    break;
                }
            }
        }
        $domain = Domain::OTHER;
        foreach ($this->ordered($entities, 'domain') as $entity) {
            if (Domain::OTHER !== $entity->domain) {
                $domain = $entity->domain;
                break;
            }
        }

        $identifiers = new Identifiers();
        $synonyms = [];
        $sources = [];
        foreach ($default as $entity) {
            $identifiers = $identifiers->merge($entity->identifiers());
            array_push($synonyms, $entity->label, ...$entity->synonyms);
            array_push($sources, ...($entity->sources ?: array_filter([$entity->source])));
        }
        $label = $values['label'] ?? $default[0]->label;

        return new Entity(
            id: $default[0]->id,
            label: $label,
            domain: $domain,
            kind: $values['kind'] ?? null,
            synonyms: array_values(array_filter(self::unique($synonyms), static fn (string $s) => strtolower($s) !== strtolower($label))),
            description: $values['description'] ?? null,
            properties: $this->properties($entities),
            identifiers: $identifiers,
            url: $values['url'] ?? null,
            image: $values['image'] ?? null,
            time: $values['time'] ?? null,
            place: $values['place'] ?? null,
            source: $default[0]->source,
            sources: array_values(array_unique($sources)),
        );
    }

    /**
     * Every property, by key in the order first given, each key's from its
     * preferred source first; a property given twice alike (same key, value
     * and source) once.
     *
     * @param list<Entity> $entities
     *
     * @return list<Property>
     */
    private function properties(array $entities): array
    {
        $keys = [];
        foreach ($this->ordered($entities, 'default') as $entity) {
            foreach ($entity->properties as $property) {
                $keys[$property->key] = true;
            }
        }
        $properties = [];
        foreach (array_keys($keys) as $key) {
            $seen = [];
            foreach ($this->ordered($entities, isset($this->preferences[$key]) ? $key : 'default') as $entity) {
                foreach ($entity->properties($key) as $property) {
                    $id = $property->source.'|'.$property;
                    if (!isset($seen[$id])) {
                        $seen[$id] = true;
                        $properties[] = null === $property->source && null !== $entity->source ? $property->with(['source' => $entity->source]) : $property;
                    }
                }
            }
        }

        return $properties;
    }

    /**
     * Whether two groups describe two things: one carries, in the scheme of
     * an id of the other, values none of which is that id.
     *
     * @param list<Entity> $a
     * @param list<Entity> $b
     */
    private function conflicts(array $a, array $b): bool
    {
        foreach ([[$a, $b], [$b, $a]] as [$owners, $others]) {
            foreach ($owners as $owner) {
                foreach ($others as $other) {
                    $values = $other->identifiers()->values($owner->id->scheme);
                    if ($values && !\in_array($owner->id->value, $values, true)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * The entities in the order a field trusts their sources.
     *
     * @param list<Entity> $entities
     *
     * @return list<Entity>
     */
    private function ordered(array $entities, string $field): array
    {
        $order = $this->preferences[$field] ?? $this->preferences['default'];
        $rank = static function (Entity $e) use ($order): int {
            $ranks = array_map(static fn (?string $s) => false === ($r = array_search($s, $order, true)) ? \PHP_INT_MAX : $r, $e->sources ?: [$e->source]);

            return min($ranks);
        };
        $positions = array_keys($entities);
        usort($positions, static fn (int $x, int $y) => [$rank($entities[$x]), $x] <=> [$rank($entities[$y]), $y]);

        return array_map(static fn (int $i) => $entities[$i], $positions);
    }

    /**
     * @param list<string> $values
     *
     * @return list<string> each once, compared without case
     */
    private static function unique(array $values): array
    {
        $unique = [];
        foreach ($values as $value) {
            if ('' !== $value) {
                $unique[strtolower($value)] ??= $value;
            }
        }

        return array_values($unique);
    }
}
