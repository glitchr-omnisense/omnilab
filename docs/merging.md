# Following cross-references, merging

## The Collector

```php
$collector = new Collector($registry, new Merger());

$caffeine = $collector->collect('pubchem', 'caffeine');            // one source, then the others
$caffeine = $collector->collect('pubchem', 'caffeine', depth: 2);  // and what those gave, in turn
$caffeine = $collector->follow($entity, 1, ['chebi', 'wikidata']); // an entity at hand, into the sources named
$found = $collector->search(new Query('caffeine'));                 // every source that searches, merged
$collector->incomplete;                                             // the sources skipped, and why
```

`collect()` asks the first source for the entity, then offers each other configured source with
`Capability::ENTITY` the entity's identifiers, one after the other, until one names a thing the
source knows. A source that cannot read a scheme says so before any call
(`NotSupportedException`): only the identifiers of the schemes it reads cost a call. A source
found nothing for is not asked again; a source that read none of the identifiers may be offered
the next round's. With `depth: 2`, the identifiers the second round brought (a ChEBI entry's
Wikidata item, say) are offered to the sources not asked yet.

PubChem → ChEBI → Wikidata is the path the family was drawn for. With the three configured,
caffeine from PubChem (as answered on 2026-10-15):

```php
$caffeine = $collector->collect('pubchem', 'caffeine');
$caffeine->sources;                       // ['pubchem', 'chebi', 'wikidata']
$caffeine->properties('molecular_weight'); // 194.19 g/mol [pubchem], 194.194 g/mol [chebi]
$caffeine->properties('monoisotopic_mass');// 194.08037557 Da [pubchem], 194.08038 Da [chebi]
$caffeine->properties('is_a');            // purine alkaloid [chebi], trimethylxanthine [chebi]
$caffeine->properties('melting_point');   // 234 °C [wikidata], 235 °C [wikidata]
$caffeine->property('density');           // 1.23 g/cm³ [wikidata]
$caffeine->identifier(Scheme::KEGG);      // C07481 (PubChem's synonyms); ChEBI and Wikidata add D00528
```

Nine calls: four of PubChem (the CID, the properties, the synonyms, the description); ChEBI is
offered the CID first, refuses it without a call, then reads the InChIKey (a search, then the
entry); Wikidata reads the CID (`haswbstatement:P662=2519`, then the item, then the label of its
class). ChEBI 2.0 gives no PubChem CID nor Wikidata item for caffeine: the InChIKey and the CID,
which PubChem gave, are what joins them. About eight seconds, PubChem's throttle and Wikidata's
answer (260 kB) for most of it.

A source that is down or rate limited is skipped and named in `$incomplete`; the others still
answer. Do not cache an answer while `$incomplete` is not empty.

## The Merger

```php
$merger = new Merger(['default' => ['pubchem', 'chebi'], 'description' => ['chebi'], 'molecular_weight' => ['pubchem']]);
$entities = $merger->merge($pubchemPage, $chebiList);   // lists or pages: one entity per thing
$one = $merger->combine($a, $b, $c);                     // several that describe one thing, no check
$merger->same($a, $b);
```

**Two entities are the same thing** when they share an identifier, unless one carries, in the
scheme of the other's own id, a different value: two PubChem compounds with two CIDs stay two,
whatever CAS number they share - a salt and its base often do.

**Each field from its preferred source**: `label`, `kind`, `description`, `url`, `image`, `time`
and `place` come from the first entity, in the order of the sources' preference for that field,
that has one; `domain` from the first that is not `other`; `id` and `source` from the first in the
`default` order. A source not listed comes after those listed, in the order the entities were
given.

**Every property kept, with its source**: a key given by two sources keeps both properties, the
one of the source preferred for that key (or the `default` order) first: `property($key)` gives
it, `properties($key)` both. A property without a source takes its entity's.

**United**: identifiers (each once, in the `default` order of the sources), synonyms (each once
whatever its case, the label left out), sources.
