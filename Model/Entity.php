<?php

namespace Omnilab\Model;

/**
 * A thing science names and describes: a compound, a gene, a protein, a
 * species, a star, an asteroid, an earthquake... as one source knows it,
 * or as the Merger assembled it from several ($sources).
 *
 * $id is the identifier the source keeps it under; $identifiers holds every
 * identifier known for it, its own first, then the cross-references to the
 * other bases (ChEBI, CAS, Wikidata...) that the Collector follows. A
 * property says which source it came from: after a merge, two sources'
 * values for one key are both kept, the preferred first.
 *
 * $related names other things it is tied to, by relation: a protein's
 * organism (ncbi.taxon:9606), its structures (pdb:1A3N), the ligands it
 * binds (chebi:60344). They are not its identifiers: the Collector does not
 * follow them, and the Merger does not take two entities for one thing
 * because they share them.
 *
 * $time and $place are set for what happens somewhere at a moment (an
 * earthquake, an eruption, an observation) or sits somewhere (a station).
 */
final readonly class Entity
{
    /**
     * @param string|null    $kind       the source's word for what it is: "compound", "protein", "species", "galaxy", "earthquake"
     * @param list<string>   $synonyms   the other names it goes by
     * @param list<Property> $properties
     * @param list<string>   $sources    the sources merged into this record
     * @param array<string, Identifiers> $related other things it is tied to, by relation ("organism", "structure", "ligand"...)
     */
    public function __construct(
        public Identifier $id,
        public string $label,
        public Domain $domain = Domain::OTHER,
        public ?string $kind = null,
        public array $synonyms = [],
        public ?string $description = null,
        public array $properties = [],
        public Identifiers $identifiers = new Identifiers(),
        public ?string $url = null,
        public ?string $image = null,
        public ?\DateTimeImmutable $time = null,
        public Point|BBox|null $place = null,
        public ?string $source = null,
        public array $sources = [],
        public array $related = [],
    ) {
    }

    /** A copy with some fields changed: $entity->with(['description' => $text]). */
    public function with(array $changes): self
    {
        return new self(...array_merge(get_object_vars($this), $changes));
    }

    /** Every identifier, its own included, each once. */
    public function identifiers(): Identifiers
    {
        return Identifiers::of($this->id)->merge($this->identifiers);
    }

    /** The first value of a scheme: $compound->identifier(Scheme::CAS) is "58-08-2". */
    public function identifier(Scheme $scheme): ?string
    {
        return $this->identifiers()->value($scheme);
    }

    /** The identifiers of the things tied to it by a relation: $protein->related('organism')->value(Scheme::NCBI_TAXON) is "9606". */
    public function related(string $relation): Identifiers
    {
        return $this->related[$relation] ?? new Identifiers();
    }

    /** The property of that key from the preferred source; null when none is known. */
    public function property(string $key): ?Property
    {
        foreach ($this->properties as $property) {
            if ($property->key === $key) {
                return $property;
            }
        }

        return null;
    }

    /** @return list<Property> every source's property of that key, the preferred first */
    public function properties(string $key): array
    {
        return array_values(array_filter($this->properties, static fn (Property $p) => $p->key === $key));
    }

    /** A property's value: $compound->value('molecular_formula') is "C8H10N4O2", ->value('molecular_weight') a Quantity. */
    public function value(string $key): Quantity|string|int|float|bool|null
    {
        return $this->property($key)?->value;
    }

    /** @return list<string> the keys known, each once, in the order first given */
    public function keys(): array
    {
        return array_values(array_unique(array_map(static fn (Property $p) => $p->key, $this->properties)));
    }
}
