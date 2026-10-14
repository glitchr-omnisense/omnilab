<?php

namespace Omnilab\Tests;

use Omnilab\Config;
use Omnilab\Model\Domain;
use Omnilab\Model\Entity;
use Omnilab\Model\Identifier;
use Omnilab\Model\Identifiers;
use Omnilab\Model\Property;
use Omnilab\Source\SourceFactory;
use Omnilab\Source\SourceInterface;

/**
 * Builds StubSources. Options: "name", "reads" (scheme names), "down", and
 * "entities": each a list of identifier keys (its own first), a label, and
 * properties as key => value, the source being the name.
 *
 *   ['name' => 'chebi', 'reads' => ['chebi'], 'entities' => [[['chebi:27732', 'wikidata:Q60235'], 'caffeine', ['charge' => 0]]]]
 */
final class StubSourceFactory extends SourceFactory
{
    protected function populate(Config $c): void
    {
        $c->defaults(['omnilab.factory_name' => 'stub', 'omnilab.required_options' => ['name'], 'reads' => [], 'entities' => [], 'down' => false]);
    }

    protected function build(Config $c): SourceInterface
    {
        $entities = array_map(static function (array $e) use ($c): Entity {
            [$keys, $label, $properties] = $e + [2 => []];
            $ids = array_map(static fn (string $key) => Identifier::parse($key) ?? Identifier::of(...explode(':', $key, 2)), $keys);
            $props = [];
            foreach ($properties as $key => $value) {
                $props[] = new Property($key, ucfirst(str_replace('_', ' ', $key)), $value, $c['name']);
            }

            return new Entity($ids[0], $label, Domain::CHEMISTRY, 'compound', properties: $props, identifiers: new Identifiers(\array_slice($ids, 1)), source: $c['name']);
        }, $c['entities']);

        return new StubSource($c['name'], $entities, array_map(static fn (string $s) => Identifier::scheme($s), $c['reads']), (bool) $c['down']);
    }
}
